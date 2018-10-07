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
     * @var string The file's mime type, for caching purposes.
     */
    public $mimeType = null;

    /**
     * @var string The file's contents, for caching purposes.
     */
    public $contents = null;

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


    /**
     * Get a URL formatted so that it is input to the MirrorReader instance.
     *
     * @param $path
     * @param string $hash
     * @return string
     */
    public static function getLocalPath($path, $hash = '') {
        return dirname(self::getScriptPath()) . '/index.php?url=' . urlencode($path) . ($hash ? '#' . $hash : '');
    }

    /**
     * @return string The web-facing path of the MirrorReader instance.
     */
    public static function getScriptPath() {
        return self::$host . $_SERVER['PHP_SELF'];
    }

    /**
     * Determine if a file or directory exists.
     *
     * @param $file
     * @return bool
     */
    static public function fileExists($file) {
        return self::isDir($file) || self::isFile($file);
    }

    /**
     * Given a file path, get the archive path from it. E.g. /Library/a.zip#path = /Library/a.zip
     *
     * @param $file
     * @return string
     */
    static public function getZipArchivePath($file) {
        return self::$store . explode('#', explode(self::$store, $file)[1])[0];
    }

    /**
     * Normalise a file path to one that is correctly encoded for RAR archives.
     *
     * @param $file
     * @return bool
     */
    public static function getRarName($file) {
        @list($a, $b) = explode('#', $file);

        if (self::isRarPath($file))
            return $a . '#' . urlencode(ltrim($b, '/'));
        elseif (self::isZipPath($file))
            return $a . '#' . ltrim($b, '/');
        else
            return $file;
    }

    /**
     * @param $file
     * @return bool If the path is that of a rar stream, i.e. begins with rar://
     */
    static public function isRarPath($file) : bool {
        return substr($file, 0, 6) === 'rar://';
    }

    /**
     * @param $file
     * @return bool If the path is that of a zip stream, i.e. begins with zip://
     */
    static public function isZipPath($file) : bool {
        return substr($file, 0, 6) === 'zip://';
    }

    /**
     * Check whether a given path both exists on the disk and is a file.
     *
     * @param $file
     * @return bool
     */
    static public function isFile($file) {
        if (self::isZipPath($file)) {
            $zip = ZipFactory::get(self::getZipArchivePath($file));
            return $zip->locateName(explode('#', $file)[1]) !== false;
        }
        elseif (self::isRarPath($file)) {
            $zip = RarFactory::get(self::getZipArchivePath($file));
            return @$zip->getEntry(explode('#', $file)[1]) !== false;
        }
        else {
            return file_exists($file);
        }
    }

    /**
     * Check whether a given path both exists on the disk and is a directory.
     *
     * @param $file
     * @return bool
     */
    static public function isDir($file) {
        if (self::isZipPath($file)) {
            if (substr($file, -1, 1) === '#') return true; // The path ends with a #, and thus refers to the root of the archive

            $zip = ZipFactory::get(self::getZipArchivePath($file));
            return $zip->locateName(explode('#', $file)[1] . "/") !== false;
        }
        elseif (self::isRarPath($file)) {
            if (substr($file, -1, 1) === '#') return true; // The path ends with a #, and thus refers to the root of the archive

            $zip = RarFactory::get(self::getZipArchivePath($file));
            return @$zip->getEntry(explode('#', $file)[1] . "/") !== false;
        }
        else {
            return is_dir($file);
        }
    }

    /**
     * Get the contents of a file.
     *
     * @param $file
     * @return bool
     */
    static public function getFileContents($file) {
        if (self::isZipPath($file)) {
            $zip = ZipFactory::get(self::getZipArchivePath($file));
            return $zip->getFromName(explode('#', $file)[1]);
        }
        elseif (self::isRarPath($file)) {
            $zip = RarFactory::get(self::getZipArchivePath($file));
            return stream_get_contents(@$zip->getEntry(explode('#', $file)[1])->getStream());
        }
        else {
            return @file_get_contents($file);
        }
    }


    function __construct($file) {
        $this->setFile($file);
    }


    /**
     * Set the file this Processor instance is working on.
     *
     * @param $file
     * @throws Exception
     */
    public function setFile($file)
    {

        $fileParts = parse_url($file);
        $fileParts['path'] = $fileParts['path'] ?? '';
        $fileParts['scheme'] = ($fileParts['scheme'] ?? 'http') ?: 'http';

        //while (strpos($fileParts['path'], '//') !== false)
        //    $fileParts['path'] = str_replace('//', '/', $fileParts['path']); // Get rid of excess slashes.

        if (substr($file, -1, 1) === '?') // Leave a ? in the path if it's at the end of the URL.
            $fileParts['path'] .= '?';
        elseif (isset($fileParts['query']))
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
            foreach ($this->config['redirect'] AS $find => $replace) {
                if (strpos($this->file, $find) !== false) {
                    return $this->setFile(str_replace($find, $replace, $this->file));
                }
            }

            /* Look for the domain in the store */
            if (self::isDir(self::$store . "{$this->fileParts['host']}/")) { // Look for the domain in a directory
                $this->setFileStore($this->formatUrlGET(self::$store . $this->fileParts['host'] . $this->fileParts['pathStore']));
            }
            else { // No directory found
                $archiveFound = false;

                /* Look for the domain in an archive file */
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


            if (!self::fileExists($this->getFileStore())) {
                /* Check to see if the file exists when formatUrlGET is not run
                 * (This will probably be removed, since it was my own bug that introduced this possible issue.) */
                /*if (self::fileExists(self::$store . $this->fileParts['host'] . $this->fileParts['pathStore'])) {
                    $this->setFileStore(self::$store . $this->fileParts['host'] . $this->fileParts['pathStore'], true);
                }

                else {*/
                    $this->error = 'File not found: ' . $this->getFileStore();
                //}
            }
        }
    }


    /**
     * Set the location of the file this processor instance is working on.
     *
     * @param $fileStore
     * @param bool $is301
     * @throws Exception
     */
    private function setFileStore($fileStore, $is301 = false) {
        $fileStore301less = $fileStore;

        $this->error = false;

        if (!self::fileExists($fileStore)) { // Oh God, is this going to be weird...
            /* MirrorWriter assumes that urlencoded files should be decoded. Strictly speaking, this isn't necessarily wise -- there are cases where this behaviour can confuse things, for instance having %2f (/) in a filename.
             * Still, we will support the behaviour without assuming it by catching it here. (Does not catch the 301 "1"-appended behaviour, though we generally recommend renaming those directories anyway. */
            if (self::fileExists(urldecode($fileStore))) {
                $this->setFileStore(urldecode($fileStore));
                return;
            }


            /* 301 nonsense */
            if ($this->config['301mode'] == 'dir') {
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

                    if (count($dirParts) - 1 === $index && is_file(self::$store . $this->fileParts['host'] . '/' . $path)) {
                        if (substr($this->fileParts['path'], -1, 1) === '/') {
                            $part .= '1/index.html';
                        }

                        else {
                            break;
                        }
                    }
                    elseif (self::isDir(self::$store . $this->fileParts['host'] . '/' . $path)) {
                        continue;
                    }
                    elseif (self::isDir(self::$store . $this->fileParts['host'] . '/' . $path301)) {
                        $is301 = true;
                        $part .= "1";
                    }
                    else {
                        $this->error = 'Could not resolve directory';
                        break;
                    }
                }

                $path301 = implode('/', $dirParts); // And, finally, we implode the modified path and will use it as the 301 path.

                if (self::fileExists($this->formatUrlGET(self::$store . $this->fileParts['host'] . '/' . $path301))) {
                    $fileStore = $this->formatUrlGET(self::$store . $this->fileParts['host'] . '/' . $path301);
                }
            }
        }

        if (substr($fileStore, -1, 1) === '/' || self::isDir($fileStore)) {
            $this->isDir = true;

            if (substr($this->file, -1, 1) !== '/') {
                return $this->setFile($this->file . '/');
            }

            foreach ($this->config['homeFiles'] AS $homeFile) {
                if ($this->isFile(self::getRarName(rtrim($fileStore, '/') . '/' . $homeFile))) {
                    $fileStore = self::getRarName(rtrim($fileStore, '/') . '/' . $homeFile);
                    $fileStore301less = self::getRarName(rtrim($fileStore301less, '/') . '/' . $homeFile);

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

    /**
     * @return string {@see Processor#file}
     */
    public function getFile() {
        return $this->file;
    }

    /**
     * @return string {@see Processor#fileStore}
     */
    public function getFileStore() {
        return $this->fileStore;
    }

    /**
     * @param $fileType {@see Processor#fileType}
     */
    public function setFileType($fileType) {
        $this->fileType = $fileType;
    }

    /**
     * @return string {@see Processor#file}
     */
    public function getFileType() {
        if (!$this->fileType) {
            $fileFileParts = explode('.', $this->fileParts['file']);
            $fileFileExt = $fileFileParts[count($fileFileParts) - 1];

            switch ($fileFileExt) { // Attempt to detect file type by extension.
                case 'html': case 'htm': case 'shtml':
                case 'php':  case 'asp': case 'aspx':
                    return $this->fileType = 'html';
                    break;

                case 'css': return $this->fileType = 'css';   break;
                case 'js':  return $this->fileType = 'js';    break;
                default:    return $this->fileType = 'other'; break;
            }
        }

        return $this->fileType;
    }

    /**
     * Detect the mimetype of a file. This result will be cached to the object.
     *
     * @param $file
     * @return bool
     */
    public function getMimeType() {
        if ($this->mimeType)
            return $this->mimeType;

        $file = $this->getFileStore();

        if (self::isZipPath($file) || self::isRarPath($file)) {
            $file = self::getRarName($file);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $mimeType = @finfo_file($finfo, $file);
        finfo_close($finfo);

        return $this->mimeType = $mimeType;
    }


    /**
     * @return string The contents of a file, transformed according to the filetype.
     */
    public function getContents() {

        if ($this->error)
            throw new Exception('Cannot getContents() when an error has been triggered: ' . $this->error);

        $contents = self::getFileContents($this->getFileStore());

        // \xEF, \xBB, \xBF are byte-order markers; pretty rare, but can cause problems if not handled.
        if ($this->getFileType() === 'other'
            && preg_match('/^(\s|\xEF|\xBB|\xBF)*(\<\!\-\-|\<\!DOCTYPE|\<html|\<head)/i', $contents)) $this->fileType = 'html';

        switch ($this->getFileType()) {
            case 'html': return $this->contents = $this->processHtml($contents);       break;
            case 'css':  return $this->contents = $this->processCSS($contents);        break;
            case 'js':   return $this->contents = $this->processJavascript($contents); break;
            default:     return $this->contents = $contents;                           break;
        }
    }


    /**
     * Output the contents of the current file, preceeded by the correct content-type header and charset.
     */
    public function echoContents() {
        $contents = $this->getContents();

        switch ($this->getFileType()) {
            case 'html': header('Content-type: text/html' . '; charset=auto');       break; // TODO: charset
            case 'css':  header('Content-type: text/css' . '; charset=' . mb_detect_encoding($contents, 'auto'));        break;
            case 'js':   header('Content-type: text/javascript' . '; charset=' . mb_detect_encoding($contents, 'auto')); break;
            default:
                header('Content-type: ' . $this->getMimeType());
                break;
        }

        echo $contents;
    }


    /**
     * @return bool True if the input $file is a special file (., .., or ~), false otherwise.
     */
    static public function isSpecial($file) {
        return $file === '.' || $file === '..' || $file === '~';
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


    /**
     * Remove ignored GETs form a URL.
     *
     * @param $url
     * @return string
     */
    public function removeBannedGET($url) {
        return preg_replace_callback('/(\?|&)(' . implode('|', $this->config['ignoreGETs']) . ')(=.*?)(&|$|\.)/', function ($match) {
            return ($match[4] === '&' ? $match[1] : $match[4]);
        }, $url);
    }


    /**
     * Transform the GET parameters of a URL.
     *
     * @param $url
     * @return string
     */
    public function formatUrlGET($url) {
        $url = $this->removeBannedGET($url);

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


    /**
     * Transform a URL into one passed into our script path as a $_GET['url'] argument.
     *
     * @param $url string The URL to transform.
     * @return string The URL, transformed.
     */
    public function formatUrl($url) {
        $url = html_entity_decode(trim($url));
        //$url = str_replace('\\', '/', $url); // Browsers also do this before even making HTTP requests, though I'm not sure of the exact rules.


        if (strpos($url, self::getScriptPath()) === 0) // Url was already formatted.
            return $url;

        if (stripos($url, 'data:') === 0) // Do not process data: URIs
            return $url;

        if (stripos($url, 'javascript:') === 0) // Process javascript: URIs as Javascript.
            return 'javascript:' . $this->processJavascript(substr($url, 11), true);

        if (strpos($url, '#') === 0) // Hashes can be left alone, when they are on their own.
            return $url;

        if (strpos($url, '?') === 0) // A query string relative to the current path.
            $url = strtok($this->fileParts['path'], '?') . $url;

        if (strpos($url, '//') === 0) // No protocol, which means use whatever the current protocol is.
            $url = $this->fileParts['scheme'] . ':' . $url;

        else {
            if (stripos($url, 'http:') !== 0 && stripos($url, 'https:') !== 0 && stripos($url, 'mailto:') !== 0 && stripos($url, 'ftp:') !== 0) { // Domain Included
                if (substr($url, 0, 1) !== '/') { // Relative Path
                    $urlDirectoryLocal = $this->fileParts['dir'];

                    while (substr($url, 0, 2) === './') {
                        $url = substr($url, 2);
                    }

                    while (substr($url, 0, 3) === '../') {
                        $url = substr($url, 3);

                        if ($urlDirectoryLocal) $urlDirectoryLocal = self::filePart($urlDirectoryLocal, 'dir'); // One case has been found where "../" is used at the root level. The browser, as a result, treats it as a "./" instead. ...I had no fricken clue this was even possible.
                    }

                    $url = "$urlDirectoryLocal/{$url}";
                }

                $url = ($this->baseUrl ?: ($this->fileParts['scheme'] . '://' . $this->fileParts['host'])) . (substr($url, 0, 1) === '/' ? $url : "/$url");
            }
        }


        $urlParts = parse_url($url);

        // A format URL callback exists; use it to return the formatted URL.
        if (is_callable($this->formatUrlCallback)) {
            return ($this->formatUrlCallback)($url, $this->getFile());
        }

        // The URL has passthru mode enabled; return the original path unaltered..
        elseif (@array_merge(@self::$domainConfiguration['default'], @self::$domainConfiguration[$urlParts['host']])['passthru'])
            return $url;

        // Normal mode: append the URL to a the $_GET['url'] parameter of our script.
        else
            return self::getLocalPath($this->removeBannedGET(explode('#', $url)[0]), @explode('#', $url)[1]);
    }


    /**
     * In some cases, the URL may be dropped directly in a file. This will find and replace all apparent URLs (those that contain a valid domain and end with a recognised extension), though it is unreliable.
     * Enable this hack with 'suspectDomainAnywhere' in the 'scriptHacks' config.
     *
     * @param $contents string The content to search through.
     * @return string A string containing formatted URLs.
     */
    private function hackFormatUrlAnywhere($contents) {
        if (in_array('suspectDomainAnywhere', $this->config['scriptHacks'])) {
            return preg_replace_callback(
                '/((https?\:\/\/)(?!(localhost|(www\.)?youtube\.com))[^ "\']*(\/|\.(' . implode('|', $this->config['recognisedExtensions']) . '))(\?(([^"\'\&\<\>\?= ]+)(=([^"\'\&\<\>\?= ]*))(\&([^"\'\&\<\>\?= ]+)(=([^"\'\&\<\>\?= ]*))*)*))*)/',
                function($m) {
                    return $this->formatUrl($m[0]);
                },
                $contents
            );
        }

        return $contents;
    }



    /**
     * Parses and rewrites HTML. This uses DOMDocument, and can handle most bad HTML.
     *
     * @param string $contents The HTML to parse for archive display.
     * @return string A string containing parsed HTML.
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

        // Alter Improper Comment Form. This is known to break things.
        $contents = str_replace('--!>', '-->', $contents);
        $contents = str_replace('//-->', '-->', $contents); // This one may not actually break things.

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


        /* TODO: form processing
        $formList = $doc->getElementsByTagName('form');
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
     * Parsess and rewrites Javascript using the enabled config['scriptHacks']. (If no script hacks are used, Javascript will not typically be altered.)
     *
     * @global string $this->config
     * @global string $urlParts
     * @param string $contents&&
     * @return string
     */
    function processJavascript($contents, $inline = false) {
        // If enabled, perform find-replace on Javascript as configured.
        if (isset($this->config['jsReplacePre'])) {
            foreach ($this->config['jsReplacePre'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }


        // If removeAll is enabled, return an empty string.
        if (in_array('removeAll', $this->config['scriptHacks']))
            return "";


        // Remove comments.
        if (in_array('removeComments', $this->config['scriptHacks'])) {
            $contents = preg_replace('/\/\*.*?\*\//s', '', $contents);
            $contents = preg_replace('/\/\/.*\$/', '', $contents);
        }

        // If suspectFileAnywhere is enabled, look for files with known extensions in the Javascript body.
        if (in_array('suspectFileAnywhere', $this->config['scriptHacks'])) { // Convert anything that appears to be a suspect file. Because of the nature of this, there is a high chance stuff will break if enabled. But, it allows some sites to work properly that otherwise wouldn't.
            $contents = preg_replace_callback('/(([a-zA-Z0-9\_\-\/]+)(\.(' . implode('|', $this->config['recognisedExtensions']) . ')))([^a-zA-Z0-9])/i', function($m) {
                return $this->formatUrl($m[1]) . $m[5];
            }, $contents); // Note that if the extension is followed by a letter or integer, it is possibly a part of a JavaScript property, which we don't want to convert
        }

        // If suspectFileString is enabled instead, look for files with known extensions in Javascript strings.
        elseif (in_array('suspectFileString', $this->config['scriptHacks'])) { // Convert strings that contain files ending with suspect extensions.
            $contents = preg_replace_callback('/("|\')(([a-zA-Z0-9\_\-\/]+)\.(' . implode('|', $this->config['recognisedExtensions']) . ')(\?(([^"\&\<\>\?= ]+)(=([^"\&\<\>\? ]*)|)(\&([^"\&\<\>\? ]+)(=([^"\&\<\>\?= ]*))*)*)?)?)\1/i', function($m) {
                return $m[1] . $this->formatUrl($m[2]) . $m[1];
            }, $contents);
        }

        // If this is not inline Javascript, use hackFormatUrlAnywhere. (If it is inline, this would have been run anyway.)
        if (!$inline)
            $contents = $this->hackFormatUrlAnywhere($contents);

        // If suspectDirString is used in script hacks, look for directories in Javascript strings. (There is no good way of doing this globally, due to things like regex.)
        if (in_array('suspectDirString', $this->config['scriptHacks'])) {
            $contents = preg_replace_callback('/("|\')((\/|)((([a-zA-Z0-9\_\-]+)\/)+))\1/i', function($m) {
                return $m[1] . $this->formatUrl($m[2]) . $m[1];
            }, $contents);
        }


        // If enabled, perform find-replace on Javascript as configured.
        if (isset($this->config['jsReplacePost'])) {
            foreach ($this->config['jsReplacePost'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }


        // Return the updated data.
        return $contents;
    }


    /**
     * Process CSS.
     * @param string $contents
     * @return string
     */
    function processCSS($contents, $inline = false) {
        // If enabled, perform find-replace on CSS as configured.
        if (isset($this->config['cssReplacePre'])) {
            foreach ($this->config['cssReplacePre'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }

        //$contents = preg_replace('/\/\*(.*?)\*\//is', '', $contents); // Removes comments.

        // Replace url() tags
        $contents = preg_replace_callback('/url\((\'|"|)(.+?)\\1\)/is', function($m) {
            return 'url(' . $m[1] . $this->formatUrl($m[2]) . $m[1] . ')';
        }, $contents); // CSS images are handled with this.

        // If enabled, perform find-replace on CSS as configured.
        if (isset($this->config['cssReplacePost'])) {
            foreach ($this->config['cssReplacePost'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
        }

        return $contents; // Return the updated data.
    }
}