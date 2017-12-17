<?php

namespace MirrorReader;

use \Exception;
use \DOMDocument;
use \RecursiveIteratorIterator;

class Processor {
    /**
     * @var string The file URL.
     */
    private $file;

    /**
     * @var string The location of the file on-disk.
     */
    private $fileStore;

    /**
     * @var array A parse of this file's information (from parse_url())
     */
    private $fileParts;

    /**
     * @var string The detected file type for this file; may be automatically detected in getFileType(), or manually set by setFileType().
     */
    private $fileType = null;

    /**
     * @var string A base URL, if any, associated with this object. Typically a result of finding a <base /> tag.
     */
    private $baseUrl = null;

    /**
     * @var string The last error encountered in processing.
     */
    public $error = "";

    /**
     * @var bool True if the passed file appears to be a directory; false if it appears to be a file (or otherwise).
     */
    public $isDir = false;

    /**
     * @var callable A function that will be used to format this object's URL. Typically used with the spider.php program to automatically download URLs encountered by the ArchiveReader.
     */
    public $formatUrlCallback = null;

    /**
     * @var string The location of the current file prior to finding any 301 redirects.
     */
    public $fileStore301less;

    /**
     * @var array Configuration data for the current site; this will be a merge of the global configuration data and any domain-specific configuration information found in the $domainConfiguration static class variable.
     */
    public $config;

    /**
     * @var string The HTTP host.
     */
    public static $host = "";

    /**
     * @var string Where library files are located on the file system.
     */
    public static $store = "";

    /**
     * @var array Configuration for all domains that can be processed for this script.
     */
    public static $domainConfiguration = [
        "default" => []
    ];

    /**
     * @var array A list of supported extensions.
     */
    private static $archiveFormats = [
        'rar', // rar is fairly fast here; for large sites, it is really the only reasonable option (install from https://github.com/esminis/php_pecl_rar for PHP 7)
        'zip', // zip is slow here, but has potential for improvement (it currently using get_file_contents to check if a file exists, which has huge unecessary overhead)
        'tar.gz', // tar.gz is even slower here, and is unlikely to improve
        'tar.bz2' // tar.bz2 is even slower here, and is unlikely to improve
    ];


    function __construct($file) {
        $this->setFile($file);
    }


    public function setFile($file)
    {

        //if (stripos($file, 'http:') !== 0 && stripos($file, 'https:') !== 0 && stripos($file, 'mailto:') !== 0 && stripos($file, 'ftp:') !== 0) { // Domain Not Included, Add It
        //    $file = 'http://' . $file;
        //}

        $fileParts = parse_url($file);
        $fileParts['path'] = $fileParts['path'] ?? '';
        $fileParts['scheme'] = ($fileParts['scheme'] ?? 'http') ?: 'http';

        //while (strpos($fileParts['path'], '//') !== false)
        //    $fileParts['path'] = str_replace('//', '/', $fileParts['path']); // Get rid of excess slashes.

        if (isset($fileParts['query']))
            $fileParts['path'] .= '?' . $fileParts['query'];


        $fileDirs = explode('/', $fileParts['path']);
        $fileDirsNew = [];
        foreach ($fileDirs AS $fileDir) {
            if (strlen($fileDir) > 255) {
                foreach (str_split($fileDir, 255) AS $fileDir) $fileDirsNew[] = $fileDir;
            }
            else {
                $fileDirsNew[] = $fileDir;
            }
        }
        $fileParts['pathStore'] = implode('/', $fileDirsNew);

        $fileParts['dir'] = $this->filePart($fileParts['path'], 'dir');
        $fileParts['file'] = $this->filePart($fileParts['path'], 'file');

        if (empty($fileParts['host'])) {
            $this->error = 'Could not process host: ' . $file;
        }
        else {
            $this->file = "{$fileParts['scheme']}://{$fileParts['host']}{$fileParts['path']}";
            $this->fileParts = $fileParts;

            $this->config = array_merge(self::$domainConfiguration['default'], isset(self::$domainConfiguration[$this->fileParts['host']]) ? self::$domainConfiguration[$this->fileParts['host']] : []);

            /* Handle $this->config Redirects */
            if (isset($this->config['redirect'])) {
                foreach ($this->config['redirect'] AS $find => $replace) { //echo $find, "\n", $replace; die();
                    if (strpos($this->file, $find) !== false) {
                        return $this->setFile(str_replace($find, $replace, $this->file));
                    }
                }
            }

            if (!is_dir(self::$store . "{$this->fileParts['host']}/")) {
                $archiveFound = false;

                foreach (self::$archiveFormats AS $format) {
                    if (is_file(self::$store . "{$this->fileParts['host']}.$format")) {
                        $archiveFound = true;

                        // If the file is part of a rar or zip archive, use a special format for it.
                        if ($format === 'rar' || $format === 'zip') {
                            $this->setFileStore("{$format}://" . $this->formatUrlGET(self::$store . $this->fileParts['host'] . ".{$format}#" . ltrim($this->fileParts['pathStore'], '/')));
                        }

                        // Otherwise, use the phar wrapper (which supports gz and bz2).
                        else {
                            $this->setFileStore("phar://" . $this->formatUrlGET(self::$store . $this->fileParts['host'] . ".{$format}/" . ltrim($this->fileParts['pathStore'], '/')));
                        }

                        break;
                    }
                }

                if (!$archiveFound) {
                    $this->error = 'Domain not found: ' . $this->getFileStore();
                    return;
                }
            }
            else {
                $this->setFileStore($this->formatUrlGET(self::$store . $this->fileParts['host'] . $this->fileParts['pathStore']));
            }


            if (!$this->fileExists($this->getFileStore())) {
                /* Check to see if the file exists when formatUrlGET is not run
                 * (This will probably be removed, since it was my own bug that introduced this possible issue.) */
                if ($this->fileExists(self::$store . $this->fileParts['host'] . $this->fileParts['pathStore'])) {
                    $this->setFileStore(self::$store . $this->fileParts['host'] . $this->fileParts['pathStore'], true);
                }

                else {
                    $this->error = 'File not found: ' . $this->getFileStore();
                }
            }
        }
    }


    public function setFileStore($fileStore, $is301 = false) {
        $fileStore301less = $fileStore;

        $this->error = false;

        if (!$this->fileExists($fileStore) && $this->config['301mode'] == 'dir') { // Oh God, is this going to be weird...
            /* MirrorWriter assumes that urlencoded files should be decoded. Strictly speaking, this isn't necessarily wise -- there are cases where this behaviour can confuse things, for instance having %2f (/) in a filename.
             * Still, we will support the behaviour without assuming it by catching it here. (Does not catch the 301 "1"-appended behaviour, though we generally recommend renaming those directories anyway. */
            if ($this->fileExists(urldecode($fileStore))) {
                $this->setFileStore(urldecode($fileStore));
                return;
            }


            /* 301 nonsense */
            $dirParts = explode('/', $this->fileParts['path']); // Start by breaking up the directory into individual folders.
            $is301 = false; // We need to set this to true once a substitution has occured, otherwise we'll never stop redirecting.

            foreach ($dirParts AS $index => $part) { // After doing that, we'll build an array containing only unique directories.
                if (!$part) unset($dirParts[$index]);
            }
            $dirParts = array_values($dirParts);

            foreach ($dirParts AS $index => &$part) { // Next, we run through the array we just created, both reading and making modifications to the mirror array we just created in which the directories will be changed to the "1" version if it exists.
                $path = implode('/', array_slice($dirParts, 0, $index + 1)); // First, we create the normal path.
                $array301 = array_slice($dirParts, 0, $index); // Then, we create the modified path.
                array_push($array301, $dirParts[$index] . 1); // "
                $path301 = implode('/', $array301);  // "

                if (count($dirParts)-1 === $index && is_file(self::$store . $this->fileParts['host'] . '/' . $path)) {
                    if (substr($this->fileParts['path'], -1, 1) === '/') {
                        $part .= '1/index.html';
                    }

                    else {
                        break;
                    }
                }
                elseif ($this->isDir(self::$store . $this->fileParts['host'] . '/' . $path)) {
                    continue;
                }
                elseif ($this->isDir(self::$store . $this->fileParts['host'] . '/' . $path301)) {
                    $is301 = true;
                    $part .= "1";
                }
                else {
                    $this->error = 'Could not resolve directory';
                    break;
                }
            }

            $path301 = implode('/', $dirParts); // And, finally, we implode the modified path and will use it as the 301 path.

            if (file_exists($this->formatUrlGET(self::$store . $this->fileParts['host'] . '/' . $path301))) {
                $fileStore = $this->formatUrlGET(self::$store . $this->fileParts['host'] . '/' . $path301);
            }
        }


        if ($this->isDir($fileStore) || substr($fileStore, -1, 1) === '/') {
            $this->isDir = true;

            foreach ($this->config['homeFiles'] AS $homeFile) {
                if ($this->isFile(rtrim($fileStore, '/') . '/' . $homeFile)) {
                    $fileStore = rtrim($fileStore, '/') . '/' . $homeFile;
                    $fileStore301less = rtrim($fileStore301less, '/') . '/' . $homeFile;

                    $this->isDir = false;
                    break;
                }
            }

            if ($this->isDir) {
                $fileStore = rtrim($fileStore, '/') . '/index.html';
                $fileStore301less = rtrim($fileStore301less, '/') . '/index.html';
            }
        }

        $this->fileStore = $fileStore;
        $this->fileStore301less = $fileStore301less;
    }

    static public function fileExists($file) {
        return self::isFile($file) || self::isDir($file);
    }

    static public function isFile($file) {
        if (substr($file, 0, 6) === 'zip://') {
            return (bool) @file_get_contents(self::getRarName($file));
        }
        elseif (substr($file, 0, 6) === 'rar://') {
            return file_exists(self::getRarName($file));
        }
        else {
            return file_exists($file);
        }
    }

    static public function isDir($file) {
        if (substr($file, 0, 6) === 'zip://') {
            return substr($file, -1, 1) === '#' || (bool) @scandir(self::getRarName($file));
        }
        elseif (substr($file, 0, 6) === 'rar://') {
            return (bool) @scandir(self::getRarName($file));
        }
        else {
            return is_dir($file);
        }
    }

    static public function getFileContents($file) {
        if (in_array(substr($file, 0, 6), ['zip://', 'rar://'])) {
            $file = self::getRarName($file);
        }

        return @file_get_contents($file);
    }

    public static function getMimeType($file) {
        if (in_array(substr($file, 0, 6), ['zip://', 'rar://'])) {
            $file = self::getRarName($file);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);

        return $mimeType;
    }

    public static function getRarName($file) {
        list($a, $b) = explode('#', $file);
        return $a . '#' . urlencode(ltrim($b, '/'));
    }

    public function getFile() {
        return $this->file;
    }

    public function getFileStore() {
        return $this->fileStore;
    }

    public function setFileType($fileType) {
        $this->fileType = $fileType;
    }

    public function getFileType() {
        if (!$this->fileType) {
            $fileFileParts = explode('.', $this->fileParts['file']);
            $fileFileExt = $fileFileParts[count($fileFileParts) - 1];

            switch ($fileFileExt) { // Attempt to detect file type by extension.
                case 'html': case 'htm': case 'shtml': case 'php': return 'html';  break;
                case 'css':                                        return 'css';   break;
                case 'js':                                         return 'js';    break;
                default:                                           return 'other'; break;
            }
        }

        return $this->fileType;
    }

    public function getScriptPath() {
        return self::$host . $_SERVER['PHP_SELF'];
    }

    public function getContents() {
        if ($this->error) {
            throw new Exception('Cannot getContents() when an error has been triggered: ' . $this->error);
        }

        $contents = self::getFileContents($this->getFileStore());

        // \xEF, \xBB, \xBF are byte-order markers; pretty rare, but can cause problems if not handled.
        if ($this->getFileType() === 'other'
            && preg_match('/^(\s|\xEF|\xBB|\xBF)*(\<\!\-\-|\<\!DOCTYPE|\<html|\<head)/i', $contents)) $this->fileType = 'html';

        switch ($this->getFileType()) {
            case 'html': return $this->processHtml($contents);       break; // TODO: charset
            case 'css':  return $this->processCSS($contents);        break;
            case 'js':   return $this->processJavascript($contents); break;
            default:     return $contents;                           break;
        }
    }


    /**
     * Output the contents of the current file, preceeded by the correct content-type haeader and charset.
     */
    public function echoContents() {
        $contents = $this->getContents();

        switch ($this->getFileType()) {
            case 'html': header('Content-type: text/html' . '; charset=auto');       break; // TODO: charset
            case 'css':  header('Content-type: text/css' . '; charset=' . mb_detect_encoding($contents, 'auto'));        break;
            case 'js':   header('Content-type: text/javascript' . '; charset=' . mb_detect_encoding($contents, 'auto')); break;
            default:
                header('Content-type: ' . self::getMimeType($this->getFileStore()));
                break;
        }

        echo $contents;
    }


    static public function isSpecial($file) {
        return $file === '.' || $file === '..' || $file === '~';
    }

    static public function isZip(string $file) {
        return substr($file, 0, 7) === "phar://"
            || substr($file, 0, 6) === "zip://"
            || substr($file, 0, 6) === "rar://";
    }

    static public function filePart($file, $filePart) { // Obtain the parent directory of a file or directory by analysing its string value. This will not operate on the directory or file itself.
        $file = str_replace('//', '/', $file);
        $fileParts = explode('/', $file);

        //  foreach ($fileParts AS $id => $part) { // Remove all empty elements.
        //    if ($part === '') unset($fileParts[$id]); // Oh my god, that was the dumbest bug: without the strict typecheck, 0 can't be a directory.
        //  }

        // Note: Because of the previous foreach loop, the array index may be corrupted (e.g. the array will be {0 = ele, 2 = ele}), thus making array_pop and array_push the only possible means of removing the last/first element of the array (as opposed to the count method that may be faster).
        switch($filePart) {
            case 'file' :
                return (string) array_pop($fileParts);
                break;

            case 'dir':
                array_pop($fileParts);
                return (string) implode('/', $fileParts);
                break;

            case 'root':
                return (string) array_shift($fileParts);
                break;

            case 'nonroot':
                array_shift($fileParts);
                return (string) implode('/', $fileParts);
                break;

            default:
                throw new Exception('Missing second parameter in $this->filePart call.');
                break;
        }
    }

    public function formatUrlGET($url) {
        $url = preg_replace_callback('/(\?|&)(' . implode('|', $this->config['ignoreGETs']) . ')(=.*?)(&|$|\.)/', function ($match) {
            return ($match[4] === '&' ? $match[1] : $match[4]);
        }, $url);

        // Catches get parameters in files with recognised extensions (TODO: all?) and directories.
        $url = preg_replace_callback('/(^|\/|\.(' . implode('|', $this->config['recognisedExtensions']) . '))\?(([^"\&\<\>\?= ]+)(=([^"\&\<\>\? ]*)|)(\&([^"\&\<\>\? ]+)(=([^"\&\<\>\?= ]*))*)*)/', function ($m) {
            // $m = [
            // 1 - file extension
            // 3 - first GET argument (e.g. "hi" in "lol.txt?hi=mom&bye=dad)
            // 4 - first GET value, including equals, and possibly empty (e.g. "=mom")
            // 5 - the rest
            return ($m[1] === "/" || $m[1] === "" ? $m[1] . "index" : "") . $m[3] . ($m[1] === "/" || $m[1] === "" ? ".html" : $m[1]);
        }, $url);

        // Catches GET parameters in files without extensions.
        $url = preg_replace_callback('/\?(([^"\&\<\>\?= ]+)(=([^"\&\<\>\? ]*)|)(\&([^"\&\<\>\? ]+)(=([^"\&\<\>\?= ]*))*)*)/', function ($m) {
            return $m[1];
        }, $url);

        return $url;
    }

    public function formatUrl($url, $skipRelative = false) {
        $url = html_entity_decode(trim($url));
        //$url = str_replace('\\', '/', $url); // Browsers also do this before even making HTTP requests, though I'm not sure of the exact rules.


        if (strpos($url, $this->getScriptPath()) === 0) // Url was already formatted.
            return $url;

        if (stripos($url, 'data:') === 0) // Do not process data: URIs
            return $url;

        if (stripos($url, 'javascript:') === 0) // Do not process javascript: URIs
            return 'javascript:' . $this->processJavascript(substr($url, 11));

        if (strpos($url, '#') === 0) // Hashes can be left alone, when they are on their own.
            return $url;

        if (strpos($url, '?') === 0)
            $url = strtok($this->fileParts['path'], '?') . $url;

        if (strpos($url, '//') === 0)
            $url = $this->fileParts['scheme'] . ':' . $url;

        else {
            if (stripos($url, 'http:') !== 0 && stripos($url, 'https:') !== 0 && stripos($url, 'mailto:') !== 0 && stripos($url, 'ftp:') !== 0) { // Domain Included
                if (strpos($url, '/') !== 0) { // Absolute Path
                    $urlDirectoryLocal = $this->fileParts['dir'];

                    while (substr($url, 0, 2) === './') {
                        $url = substr($url, 2);
                    }

                    while (substr($url, 0, 3) === '../') {
                        $url = substr($url, 3);

                        if ($urlDirectoryLocal) $urlDirectoryLocal = $this->filePart($urlDirectoryLocal, 'dir'); // One case has been found where "../" is used at the root level. The browser, as a result, treats it as a "./" instead. ...I had no fricken clue this was even possible.
                    }

                    $url = "$urlDirectoryLocal/{$url}";
                }

                $url = ($this->baseUrl ?: ($this->fileParts['scheme'] . '://' . $this->fileParts['host'])) . (substr($url, 0, 1) === '/' ? $url : "/$url");
            }
        }

        $urlObject = Factory::get($url);

        // A format URL callback exists; use it to return the formatted URL.
        if (function_exists($this->formatUrlCallback)) {
            $function = $this->formatUrlCallback;
            return $function($urlObject->getFile(), $this->getFile());
        }

        // The URL has passthru mode enabled; return the original path unaltered..
        elseif ($urlObject->config['passthru'])
            return $urlObject->getFile();

        // Normal mode: append the URL to a the $_GET['url'] parameter of our script.
        else
            return $this->getScriptPath() . '?url=' . urlencode($urlObject->getFile()) . (isset($urlObject->fileParts['fragment']) ? '#' . $urlObject->fileParts['fragment'] : '');
    }


    private function hackFormatUrlAnywhere($contents) {
        if (in_array('suspectDomainAnywhere', $this->config['scriptHacks'])) {
            return preg_replace_callback(
                '/((https?\:\/\/)(?!(localhost|(www\.)?youtube\.com))[^ "\']*(\/|\.(' . implode('|', $this->config['recognisedExtensions']) . '))(\?(([^"\'\&\<\>\?= ]+)(=([^"\'\&\<\>\?= ]*))(\&([^"\'\&\<\>\?= ]+)(=([^"\'\&\<\>\?= ]*))*)*))*)/',
                function($m) {
                    return $this->formatUrl($m[0]);
                },
                $contents
            ); // In some cases, the URL may be dropped directly in. This is an unreliable method of trying to replace it with the equvilent index.php script, and is only used with the eccentric method, since this is rarely used when string-dropped.
        }

        return $contents;
    }



    /**
     * Rewrites HTML. This uses DOMDocument, and can handle most bad HTML. (No guarentees, but no cases have yet been found where it screws up.)
     * @global string $this->config
     * @param string $contents
     * @return string
     */
    function processHtml($contents) {
        /* HTML Replacement, if enabled */
        if (isset($this->config['htmlReplacePre'])) {
            foreach ($this->config['htmlReplacePre'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }


        /* Base URL Detection */
        preg_match_all('/\<base href="(.+?)"\>/is', $contents, $baseMatch);
        if (isset($baseMatch[1][0])) {
            $this->baseUrl = $baseMatch[1][0];
            $contents = preg_replace('/\<base href="(.+?)"\>/is', '', $contents);
        }


        /* Remove Various Comment Nonsense */
        // The horrible if IE hack.
        $contents = preg_replace('/\<\!--\[if([a-zA-Z0-9 ]+)\\]\>.+?\<\!\[endif\]--\>/is', '', $contents);

        $contents = str_replace('--!>', '-->', $contents); // Alter Improper Comment Form. This is known to break things.
        $contents = str_replace('//-->', '-->', $contents); // Alter Improper Comment Form. This may break things (it is less clear.)


        /* Remove All Scripts Hack, if Enabled
         * (very useful for a small number of sites, like Wikia and many silly news sites) */
        if (in_array('removeAll', $this->config['scriptHacks'])) {
            // If any body-level elements are in a <noscript> tag in the head, it will cause problems when we un-noscript them. This has been observed with, e.g., tracking beacons on Wikia.
            $contents = preg_replace('/\<noscript[^\>]*\>(.*?)\<\/noscript\>(.*?)\<\/head\>(.*?)\<body([^\>]*)\>/is', '$2</head>$3<body$4><noscript>$1</noscript>', $contents);

            // Remove the relevant <noscript> tags.
            $contents = preg_replace('/\<noscript[^\>]*\>(.*?)\<\/noscript\>/is', '$1', $contents);

            // Remove all script tags.
            $contents = preg_replace('/\<script[^\>]*\>(.*?)\<\/script\>/is', '', $contents);
        }

        /*if (in_array('removeAll', $this->config['scriptHacks'])) {
            $noscriptList = $doc->getElementsByTagName("noscript");

            for ($i = 0; $i < $noscriptList->length; $i++) {
                $fragment = $doc->createDocumentFragment();
                while ($noscriptList->item($i)->childNodes->length > 0) {
                    $fragment->appendChild($noscriptList->item($i)->childNodes->item(0));
                }
                $noscriptList->item($i)->parentNode->replaceChild($fragment, $noscriptList->item($i));
            }
        }*/


        /* Fix Missing HTML Elements */
        // Hack to ensure there's an opening head tag if there's a closing head tag (...yes, that happens).
        if (!preg_match('/<head( [^\>]*]|)>/', $contents))
            $contents = preg_replace('/<html(.*?)>/ism', '<html$1><head>', $contents);

        // Hack to ensure HTML tags are present (they sorta need to be
        /*if (strpos($contents, '<html>') === false)
            $contents = '<html>' . $contents;
        if (strpos($contents, '</html>') === false)
            $contents = $contents . '</html>';*/


        /* Hack to remove HTML comments from <style> tags, for the same reason */
        //$contents = preg_replace('/\<style([^\>]*?)\>(.*?)\<\!\-\-(.*?)\-\-\>(.*?)\<\/style\>/is', '<style$1>$2$3$4</style>', $contents);

        /* Hack to move <style> tags into <head> if they are in <body>, since it will cause problems with loadHtml. */
//        $matches = [];
//  if (preg_match('/\<body([^\>]*)\>(.*?)\<style([^\>]*)\>(.*?)\<\/style\>/is', $contents, $matches)) { // This particular regex avoids catastrophic backtracking as much as I personally know how to
//    $contents = preg_replace('/\<body([^\>]*)\>(.*?)\<style([^\>]*)\>(.*?)\<\/style\>/is', '<body$1>$2', $contents);
//    $contents = str_replace('</head>', "<style{$matches[3]}>$matches[4]</style></head>", $contents);
//  }

        $contents = $this->hackFormatUrlAnywhere($contents);

        // The loadHTML call below is known to mangle Javascript in some rare but significant situations. This is a lazy workaround (make sure to run it after processing scripts fully, of-course).
        $contents = preg_replace_callback('/<script([^\>]*)>(.*?)<\/script>/is', function($m) {
            return '<script' . $m[1] . '>' . ($m[2] ? 'eval(atob("' . base64_encode($this->processJavascript($m[2], true)) . '"))' : '') . '</script>';
        }, $contents);

        // Believe it or not, I have encountered this is in the wild, and it breaks DomDocument. A specific case for now; should be generalised.
        $contents = preg_replace('/\<\!DOCTYPE HTML PUBLIC \\\\"(.+)\\\\"\>/', '<!DOCTYPE HTML PUBLIC "$1">', $contents);

        $contents = "<?xml encoding=\"utf-8\" ?>" . $contents;

        libxml_use_internal_errors(true); // Stop the loadHtml call from spitting out a million errors.
        $doc = new DOMDocument(); // Initiate the PHP DomDocument.
        $doc->preserveWhiteSpace = false; // Don't worry about annoying whitespace.
        $doc->substituteEntities = false;
        $doc->formatOutput = false;
        $doc->recover = true; // We may need to set this behind a flag. Some... incredibly broken websites seem to benefit heavily from it, but I think it is also capable of breaking non-broken websites.
        $doc->loadHTML($contents, LIBXML_HTML_NODEFDTD); // Load the HTML.

        // Process LINK tags
        $linkList = $doc->getElementsByTagName('link');
        for ($i = 0; $i < $linkList->length; $i++) {
            if ($linkList->item($i)->hasAttribute('href')) {
                if ($linkList->item($i)->getAttribute('type') == 'text/css' || $linkList->item($i)->getAttribute('rel') == 'stylesheet') {
                    $linkList->item($i)->setAttribute('href', $this->formatUrl($linkList->item($i)->getAttribute('href')) . '&type=css');
                }
                else {
                    $linkList->item($i)->setAttribute('href', $this->formatUrl($linkList->item($i)->getAttribute('href')));
                }
            }
        }


        // Process SCRIPT tags.
        $scriptList = $doc->getElementsByTagName('script');
        $scriptDrop = array();
        for ($i = 0; $i < $scriptList->length; $i++) {
            if ($scriptList->item($i)->hasAttribute('src')) {
                $scriptList->item($i)->setAttribute('src', $this->formatUrl($scriptList->item($i)->getAttribute('src')) . '&type=js');
            }
        }
        foreach ($scriptDrop AS $drop) {
            $drop->parentNode->removeChild($drop);
        }


        // Process STYLE tags.
        $styleList = $doc->getElementsByTagName('style');
        for ($i = 0; $i < $styleList->length; $i++) {
            $styleList->item($i)->nodeValue = htmlentities($this->processCSS($styleList->item($i)->nodeValue, true));
        }


        // Process IMG, VIDEO, AUDIO, IFRAME tags
        foreach (array('img', 'video', 'audio', 'source', 'frame', 'iframe', 'applet') AS $ele) {
            $imgList = $doc->getElementsByTagName($ele);
            for ($i = 0; $i < $imgList->length; $i++) {
                foreach (array_merge(['src', 'poster'], $this->config['customSrcAttributes']) AS $srcAttr) {
                    if ($imgList->item($i)->hasAttribute($srcAttr)) {
                        $imgList->item($i)->setAttribute($srcAttr, $this->formatUrl($imgList->item($i)->getAttribute($srcAttr)));
                    }
                }

                if ($imgList->item($i)->hasAttribute('srcset')) {
                    $srcList = explode(',', $imgList->item($i)->getAttribute('srcset'));

                    foreach ($srcList as &$srcPair) {
                        if (strstr($srcPair, ' ') === false) continue;

                        list($srcFile, $srcSize) = explode(' ', trim($srcPair));
                        $srcFile = $this->formatUrl(trim($srcFile));
                        $srcPair = implode(' ', [$srcFile, $srcSize]);
                    }
                    $imgList->item($i)->setAttribute('srcset', implode(', ', $srcList));
                }
            }
        }


        // Process A, AREA (image map) tags
        foreach (array('a', 'area') AS $ele) {
            $aList = $doc->getElementsByTagName($ele);
            for ($i = 0; $i < $aList->length; $i++) {
                if ($aList->item($i)->hasAttribute('href')) {
                    $aList->item($i)->setAttribute('href', $this->formatUrl($aList->item($i)->getAttribute('href')));
                }
            }
        }


        /*$formList = $doc->getElementsByTagName('form');
        for ($i = 0; $i < $formList->length; $i++) {
            if ($formList->item($i)->hasAttribute('action')) {
                if (!$formList->item($i)->hasAttribute('method') || strtolower($formList->item($i)->getAttribute('method')) === 'get') {
                    $actionParts = parse_url($this->formatUrl($formList->item($i)->getAttribute('action')));

                    $formList->item($i)->setAttribute('action', $actionParts['scheme'] . '://' . $actionParts['host'] . $actionParts['path']);

                    $queryParts = [];
                    parse_str($actionParts['query'], $queryParts);

                    foreach ($queryParts AS $partName => $partValue) {
                        $partElement = $doc->createElement("input");
                        $partElement->setAttribute("type", "hidden");
                        $partElement->setAttribute("name", $partName);
                        $partElement->setAttribute("value", $partValue);

                        $formList->item($i)->appendChild($partElement);
                    }
                }
            }
        }*/


        // Process meta-refresh headers that may in some cases automatically redirect a page, similar to <a href>.
        // <meta http-equiv="Refresh" content="5; URL=http://www.google.com/index">
        $metaList = $doc->getElementsByTagName('meta');
        for ($i = 0; $i < $metaList->length; $i++) {
            if ($metaList->item($i)->hasAttribute('http-equiv')
                && $metaList->item($i)->hasAttribute('content')
                && strtolower($metaList->item($i)->getAttribute('http-equiv')) == 'refresh') {
                $metaList->item($i)->setAttribute('content', preg_replace_callback('/^(.*)url=([^ ;]+)(.*)$/is', function($m) {
                    return $m[1] . 'url=' . $this->formatUrl($m[2]) . $m[3];
                }, $metaList->item($i)->getAttribute('content')));
            }
        }


        // Process BODY, TABLE, TD, and TH tags w/ backgrounds. TABLE, TD & TH do support the background tag, but it was an extension of both Netscape and IE way back, and today most browsers still recognise it and will add a background image as appropriate, so... we have to support it.
        if (in_array('backgroundHack', $this->config['htmlHacks'])) {
            foreach (array('body', 'table', 'td', 'th') AS $ele) {
                $aList = $doc->getElementsByTagName($ele);
                for ($i = 0; $i < $aList->length; $i++) {
                    if ($aList->item($i)->hasAttribute('background')) {
                        $aList->item($i)->setAttribute('background', $this->formatUrl($aList->item($i)->getAttribute('background')));
                    }
                }
            }
        }


        // Process Option Links; some sites will store links in OPTION tags and then use Javascript to link to them. Thus, if the hack is enabled, we will try to cope.
        if (in_array('selectHack', $this->config['htmlHacks'])) {
            $optionList = $doc->getElementsByTagName('option');
            for ($i = 0; $i < $optionList->length; $i++) {
                if ($optionList->item($i)->hasAttribute('value')) {
                    $optionValue = $optionList->item($i)->getAttribute('value');
                    if (filter_var($optionValue, FILTER_VALIDATE_URL) || preg_match('/.*(\.(htm|html|shtml|php|asp)|\/)$/', $optionValue)) {
                        $optionList->item($i)->setAttribute('value', $this->formatUrl($optionValue));
                    }
                }
            }
        }


        // This formats style and javascript attributes; it is safe, but we disable by default for performance reasons.
        // Performance may be reasonable when combined with effective caching, however.
        if (in_array('dirtyAttributes', $this->config['htmlHacks'])) {
            $docAll = new RecursiveIteratorIterator(
                new RecursiveDOMIterator($doc),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach($docAll as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE) {
                    if ($node->hasAttribute('style')) {
                        $node->setAttribute('style', str_replace("\n", '', $this->processCSS($node->getAttribute('style'), true)));
                    }

                    foreach (array('onclick', 'onmouseover', 'onmouseout', 'onfocus', 'onblur', 'onchange', 'onsubmit') as $att) {
                        if ($node->hasAttribute($att)) {
                            if (in_array('removeAll', $this->config['scriptHacks'])) {
                                $node->removeAttribute($att);
                            }
                            else {
                                $node->setAttribute($att, str_replace("\n", '', $this->processJavascript($node->getAttribute($att), true)));
                            }
                        }
                    }
                }
            }
        }


        if (isset($this->config['htmlReplacePost'])) {
            foreach ($this->config['htmlReplacePost'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }


        return $doc->saveHTML(); // Return the updated data.
    }


    /**
     * Rewrites Javascript
     * @global string $this->config
     * @global string $urlParts
     * @param string $contents&&
     * @return string
     */
    function processJavascript($contents, $inline = false) {
        if (isset($this->config['jsReplacePre'])) {
            foreach ($this->config['jsReplacePre'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }

        //$contents = preg_replace('/\/\*(.*?)\*\//is', '', $contents); // Removes comments.

        if (in_array('removeAll', $this->config['scriptHacks'])) {
            return "";
        }

        if (in_array('suspectFileAnywhere', $this->config['scriptHacks'])) { // Convert anything that appears to be a suspect file. Because of the nature of this, there is a high chance stuff will break if enabled. But, it allows some sites to work properly that otherwise wouldn't.
            $contents = preg_replace_callback('/(([a-zA-Z0-9\_\-\/]+)(\.(' . implode('|', $this->config['recognisedExtensions']) . ')))([^a-zA-Z0-9])/i', function($m) {
                return $this->formatUrl($m[1]) . $m[5];
            }, $contents); // Note that if the extension is followed by a letter or integer, it is possibly a part of a JavaScript property, which we don't want to convert
        }
        elseif (in_array('suspectFileString', $this->config['scriptHacks'])) { // Convert strings that contain files ending with suspect extensions.
            $contents = preg_replace_callback('/("|\')(([a-zA-Z0-9\_\-\/]+)\.(' . implode('|', $this->config['recognisedExtensions']) . ')(\?(([^"\&\<\>\?= ]+)(=([^"\&\<\>\? ]*)|)(\&([^"\&\<\>\? ]+)(=([^"\&\<\>\?= ]*))*)*)|))\1/i', function($m) {
                return $m[1] . $this->formatUrl($m[2]) . $m[1];
            }, $contents);
        }


        /* Remove escaped slashes from JS strings */
        // Using full regex:
        //while (preg_match("/((?<![\\\\])['\"])((?:.(?!(?<![\\\\])\\1))*.?)\\\\\/((?:.(?!(?<![\\\\])\\1))*.?)\\1/is", $contents)) {
        //  $contents = preg_replace("/((?<![\\\\])['\"])((?:.(?!(?<![\\\\])\\1))*.?)\\\\\/((?:.(?!(?<![\\\\])\\1))*.?)\\1/is", "$1$2/$3$1", $contents);
        //}
        /*    start:
            preg_match_all("/([\"'])(?:\\\\\\1|.)*?\\1/is", $contents, $matches, PREG_OFFSET_CAPTURE); //var_dump($matches);

            foreach ($matches[0] AS $match) {
                if (strpos('\/', $match[0]) !== false) {
                    $newString = str_replace('\/', '/', $match[0]);
                    echo $contents = substr_replace($contents, $newString, $match[1], strlen($match[0]));

                    goto start;
                }
            }*/
        //) {
        //    $match[0] = str_replace('\/', '/', $match[0]);
        //}

        // Using capture groups:
//    while(preg_match("/((?<![\\\\])['\"])((?:.(?!(?<![\\\\])\\1))*.?)\\\\\/((?:.(?!(?<![\\\\])\\1))*.?)\\1/is", $contents, $matches, PREG_OFFSET_CAPTURE)) { var_dump($matches);
//        $content = substr_replace('\\/', '/', $matches[0][1], strlen($matches[0][0])); $i++; if ($i > 100) die();
//    }

        if (!$inline)
            $contents = $this->hackFormatUrlAnywhere($contents);

        if (in_array('suspectDirString', $this->config['scriptHacks'])) {
            $contents = preg_replace_callback('/("|\')((\/|)((([a-zA-Z0-9\_\-]+)\/)+))\1/i', function($m) {
                return $m[1] . $this->formatUrl($m[2]) . $m[1];
            }, $contents);
        }

        if (isset($this->config['jsReplacePost'])) {
            foreach ($this->config['jsReplacePost'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }

        return $contents; // Return the updated data.
    }


    /**
     * Process CSS.
     * @param string $contents
     * @return string
     */
    function processCSS($contents, $inline = false) {
        if (isset($this->config['cssReplacePre'])) {
            foreach ($this->config['cssReplacePre'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }

        //$contents = preg_replace('/\/\*(.*?)\*\//is', '', $contents); // Removes comments.

        //if (!$inline)
        //    $contents = $this->hackFormatUrlAnywhere($contents);

        $contents = preg_replace_callback('/url\((\'|"|)(.+?)\\1\)/is', function($m) {
            return 'url(' . $m[1] . $this->formatUrl($m[2]) . $m[1] . ')';
        }, $contents); // CSS images are handled with this.

        if (isset($this->config['cssReplacePost'])) {
            foreach ($this->config['cssReplacePost'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }

        return $contents; // Return the updated data.
    }
}