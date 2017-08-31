<?php

/**
 * This is a basic tool that scans an existing archive and tries to fill in any gaps. It can be used in a download gets partially corrupted, or your Heritrix job gets corrupted.
 * It even detects a small number of things Heritrix fails to, e.g. <img srcset>. I use it even on completed Heritrix jobs to ensure a full archive.
 * Additionally, it will clean up the directory structure a bit. MirrorReader handles the 1-suffixed directories fairly well (albeit at the cost of speed), but it does not handle 1-suffixed files, which this does deal with.
 * Also note that it will write 301 redirects where they are expected by the including files, unless the 301 is specified in aviewerConfiguration.php. This is good for archive viewing, but does mean you'll end up with duplicated files. (I find it worthwhile, in any case, to have them located in both places.)
 * Currently searches <a href>, <img src>, and <img srcset>.
 * Does not redownload existing files.
 * Does not check content type, but will only download files matching valid string/regex rules.
 */

$time = microtime(true);
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require('aviewerFunctions.php');
require_once('aviewerConfiguration.php');
$domainConfiguration['default']['scriptHacks'] = [];
$resource = $_GET['resource'];
$protocol = $_GET['protocol'] ?? 'http';
$match = $_GET['match'] ?? '.*';
$path = realpath('/Library/' . $resource);
$ignore = apcu_exists("av_srcset_ignore") ? apcu_fetch("av_srcset_ignore") : [];
//$writeFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.' . microtime() . '.html', "a") or die("Unable to open write file!");
$writeFile = fopen("php://output", "w");
$successFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.successes.txt', "a") or die("Unable to open write file!");
$failFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.errors.txt', "a") or die("Unable to open write file!");

$_GET['start'] = ($_GET['start'] ?? 0);
$_GET['end'] = ($_GET['end'] ?? 300);

function processFile($srcUrl, $lastFile = false) {
    global $resource, $ignore, $writeFile, $match, $successFile, $failFile;

    if (!$srcUrl)
        return;
    if (strpos($srcUrl, '#') === 0)
        return;

    $srcFile = ArchiveReaderFactory($srcUrl);
    $destFile = $srcFile->getFileStore();
//    if (substr($destFile, -1, 1) === '/') {
//        $destFile .= 'index.html';
//    }


    //if (stripos($srcFile->getFile(), "forums.") !== false)
    //    return;
    if (stripos($srcFile->getFile(), "/forums/") !== false)
        return;

    // Domain Exceptions
    if (stripos($srcFile->getFile(), "mediawiki.org/") !== false ||
        stripos($srcFile->getFile(), "facebook.com/") !== false ||
        stripos($srcFile->getFile(), "google.com/") !== false ||
        stripos($srcFile->getFile(), "reddit.com/") !== false ||
        stripos($srcFile->getFile(), "twitter.com/") !== false ||
        stripos($srcFile->getFile(), "tumblr.com/share/") !== false ||
        stripos($srcFile->getFile(), "archive.org/") !== false ||
        stripos($srcFile->getFile(), "scorecardresearch.com/") !== false ||
        stripos($srcFile->getFile(), "pixel.wp.com/") !== false) {
        $status = 'fail [domainban]';
        $color = 'orange';
    }

    // File Exceptions
    elseif (stripos($srcFile->getFile(), "/api.php") !== false) {
        $status = 'fail [fileban]';
        $color = 'orange';
    }

    // Wiki Exceptions
    elseif (stripos($srcFile->getFile(), "/wp-json/") !== false ||
        stripos($srcFile->getFile(), "/oembed/") !== false ||
        stripos($srcFile->getFile(), "/ebay/") !== false ||
        stripos($srcFile->getFile(), "/ebaysearch/") !== false ||
        stripos($srcFile->getFile(), "/amazon/") !== false ||
        stripos($srcFile->getFile(), "/random/") !== false ||
        stripos($srcFile->getFile(), "/feed/") !== false ||
        stripos($srcFile->getFile(), ".msg") !== false ||
        stripos($srcFile->getFile(), "prev_next") !== false ||
        stripos($srcFile->getFile(), "/privmsg.php") !== false ||
        stripos($srcFile->getFile(), "/posting.php") !== false ||
        stripos($srcFile->getFile(), "/xmlrpc.php") !== false ||
        //stripos($srcFile->getFile(), "/w/") !== false ||
        stripos($srcFile->getFile(), "Special:") !== false ||
        stripos($srcFile->getFile(), "Talk:") !== false ||
        stripos($srcFile->getFile(), "User:") !== false) {
        $status = 'fail [wikiban]';
        $color = 'orange';
    }

    // Protocol ban
    elseif (stripos($srcFile->getFile(), "javascript:") !== false ||
        stripos($srcFile->getFile(), "mailto:") !== false ||
        stripos($srcFile->getFile(), "irc:") !== false ||
        stripos($srcFile->getFile(), "/aim:") !== false) {
        $status = 'fail [protocolban]';
        $color = 'orange';
    }

    // GET Ban
    elseif (preg_match("/(&|\\?)(view=next|view=previous|replytocom|advertisehereid=|oldid|mobileaction|veaction=edit|action=pm|action=formcreate|action=edit|action=create|action=history|action=info|action=printpage|action=register|action=lostpw|postingmode=|printable|parent=|redirect)/", $srcFile->getFile()) !== 0) {
        $status = 'fail [bad get]';
        $color = 'orange';
    }
    elseif (preg_match("/$match/", $srcFile->getFile()) !== 1) {
        $status = 'fail [badmatch]';
        $color = 'orange';
    }
    elseif (!$destFile) {
        $status = 'fail [nodest - you may need to create the root dir first]';
        $color = 'orange';
    }
    else {
        if (!is_dir(dirname($destFile))) {
            if (!mkdir(dirname($destFile), 0777, true)) {
                $baseFile = $destFile;

                while (!is_file($baseFile) && $baseFile !== '') {
                    $baseFile = rtrim(dirname($destFile), '/');
                }

                if ($baseFile !== '') {
                    rename($baseFile, $baseFile . '~temp');
                    mkdir($baseFile, 0777, true) or die('Mkdir failed. Temp file leftover: ' . $baseFile . '~temp');
                    rename($baseFile . '~temp', $baseFile . '/index.html');

                    fwrite($successFile, "$destFile: created $baseFile directory, moving in $baseFile as $baseFile/index.html\n");

                    if (!is_dir(dirname($destFile)) && !mkdir(dirname($destFile), 0777, true)) {
                        die('Mkdir failed: ' . dirname($destFile));
                    }
                }
                else {
                    $status = 'fail [direrror]';
                    $color = 'red';
                }
            }
        }


        if (isset($status)) {

        }
        elseif (in_array($srcUrl, $ignore)) {
            $status = 'fail [ignored]';
            $color = 'orange';
        }

        elseif (strlen(basename($destFile)) > 254) {
            $color = 'purple';
            $status = 'toolong';
        }

        elseif (is_file($destFile)
            && filesize($destFile) > 0
            && (filesize($destFile) > 1024
                || (strstr(file_get_contents($destFile), "Moved") === false
                    && strstr(file_get_contents($destFile), "Found") === false))) {

            if ($destFile != $srcFile->fileStore301less && !file_exists(dirname($srcFile->fileStore301less))) {
                if (!is_dir(dirname($srcFile->fileStore301less)) && dirname($destFile) != dirname($srcFile->fileStore301less)) {
                    if (!is_dir(dirname(dirname($srcFile->fileStore301less))))
                        mkdir(dirname(dirname($srcFile->fileStore301less)), 0777, true) or die('Could not create directory: ' . dirname($srcFile->fileStore301less));

                    fwrite($successFile, "$srcUrl: renamed 301 directory " . dirname($destFile) . " to " . dirname($srcFile->fileStore301less) . "\n");
                    rename(dirname($destFile), dirname($srcFile->fileStore301less)) or die("Failed to rename 301 dir " . dirname($destFile) . " to " . dirname($srcFile->fileStore301less));
                }
                else {
                    if (!is_dir(dirname($srcFile->fileStore301less)))
                        mkdir(dirname($srcFile->fileStore301less), 0777, true) or die('Could not create directory: ' . dirname($srcFile->fileStore301less));

                    fwrite($successFile, "$srcUrl: renamed 301 file $destFile to " . $srcFile->fileStore301less . "\n");
                    rename($destFile, $srcFile->fileStore301less) or die("Failed to rename 301 file $destFile to " . $srcFile->fileStore301less);
                }

                $status = 'renamed [' . $srcFile->fileStore301less . ']';
                $color = 'blue';
            }
            else {
                $status = 'exists [' . $srcFile->fileStore301less . ']';
                $color = 'black';
            }

        }

        else {
            $path = fopen($srcUrl, 'r');
            $redirectLocation = false;
            $status = false;
            $headers = [];
            $headerSet = 0;

             foreach ($http_response_header AS $header) {
                if (stripos($header, 'HTTP/') === 0) {
                    $headerSet++;

                    if (strpos($header, '301') !== false || strpos($header, '302') !== false) {
                        $headers[$headerSet]['status'] = 'redirect';
                    }
                    else if (strpos($header, '200') === false) {
                        $headers[$headerSet]['status'] = 'error';
                        $headers[$headerSet]['httpCode'] = $header;
                    }
                    else {
                        $headers[$headerSet]['status'] = 'okay';
                    }
                }

                if (stripos($header, 'location') !== false) {
                    $headers[$headerSet]['location'] = explode(': ', $header)[1];
                }
            }

            if ($headers[$headerSet]['status'] === 'okay') {
                if ($headerSet > 1
                    && $headers[$headerSet - 1]['status'] === 'redirect'
                    && parse_url($headers[$headerSet - 1]['location'], PHP_URL_PATH) !== parse_url($srcUrl, PHP_URL_PATH)) {// Only bother processing path changes. Cross-domain detect is possible, but opens up too many complexities when talking about archiving for me to want to deal with them

                    $redirectLocation = $headers[$headerSet - 1]['location'];

                    echo $srcUrl;
                    var_dump($headers);
                    var_dump($http_response_header);

                    $redirectObject = ArchiveReaderFactory($redirectLocation);

                    if (ArchiveReader::isFile($redirectObject->getFileStore())) {
                        $contents = $redirectObject->getContents();

                        if ($redirectObject->getFileType() === 'html') {
                            file_put_contents($destFile, '<!-- MirrorReader Redirect Page --><html><head><title>Internal Redirect</title><meta http-equiv="refresh" content="0; url=' . htmlspecialchars($redirectLocation) . '"></head><body><center><a href="' . htmlspecialchars($redirectLocation) . '">Follow redirect.</a></center></body></html>');

                            $status = 'redirect file';
                            $color = 'teal';

                            fwrite($successFile, "$srcUrl\t$redirectLocation => $destFile\t$status\n");
                        }
                        else {
                            file_put_contents($destFile, $contents);

                            $status = 'success';
                            $color = 'green';
                        }
                    }

                }
                elseif (!is_file($destFile) && is_dir($destFile) && !file_exists("$destFile/index.html")) {
                    if (file_put_contents("$destFile/index.html", $path) or die("Failed to write to $destFile/index.html")) {
                        $status = 'success [index.html]';
                        $color = 'green';
                    }
                }
                else {
                    if (file_put_contents($destFile, $path)) {
                        $status = 'success';
                        $color = 'green';
                    }
                }
            }
            else {
                $status = 'fail [' . $headers[$headerSet]['httpCode'] . ']';
                $color = 'red';
                $ignore[] = $srcUrl;
            }


            if (!$status) {
                $status = 'fail [unknown]';
                $color = 'red';
            }
        }
    }

    fwrite($writeFile, "<tr style='color:$color;'><td>" . $srcUrl . "</td><td>" . $destFile . "</td><td>" . $status . "</td></tr>");

    if ($color === 'green') {
        usleep(500000);
        fwrite($successFile, "$lastFile\t$srcUrl\t$destFile\t$status\n");

        fwrite($writeFile, "<tr><th colspan=4>$destFile (decended):</th></tr>");
        $file = ArchiveReaderFactory($srcUrl);
        $file->formatUrlCallback = 'processFile';
        if (!$file->error) $file->getContents();
    }
    elseif ($color === 'red') {
        usleep(500000);
        fwrite($failFile, "$lastFile\t$srcUrl\t$destFile\t$status\n");
    }
}

$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);

$j = 0;
$domainReader = new ArchiveReader($protocol . '://' . $resource);
fwrite($writeFile, '<table>');
foreach($objects AS $name => $object) {
    if (strpos($name, "data:") !== false) continue;

    /* TODO: these should be a seperate step entirely */
    if (!is_file($name)) {
        if (is_dir($name) && is_file($name . '1') && !is_file($name . '/index.html'))
            if (rename($name . '1', $name . '/index.html')) {
                fwrite($writeFile, "Renamed file {$name}1 to {$name}/index.html<br />");
                fwrite($successFile, "Renamed file {$name}1 to {$name}/index.html\n");
            }
            else
                die('Rename failed.');

        continue;
    }

    elseif (is_file($name) && is_dir("{$name}1")) {
        rename($name, "{$name}1/index.html") or die("Failed to rename $name to {$name}1/index.html");

        if (rename("{$name}1", $name)) {
            fwrite($writeFile, "Renamed directory {$name}1 to {$name} and moved in index.html.<br />");
            fwrite($successFile, "Renamed directory {$name}1 to {$name} and moved in index.html.\n");
            $name = "{$name}/index.html";
        }
        else
            die("Rename from {$name}1 to $name failed. index.html file was still moved in.");
    }

    elseif (preg_match('/(?|&)(' . implode('|', $domainReader->config['ignoreGETs']) . ')(=.*?)(&|$)/', $name)) {
        $newName = preg_replace_callback('/(\?|&)(' . implode('|', $domainReader->config['ignoreGETs']) . ')(=.*?)(&|$|\.)/', function($match) {
            return ($match[4] === '&' ? $match[1] : $match[4]);
        }, $name);

        rename($name, $newName) or die("Could not rename $name to $newName");

        fwrite($writeFile,  "Renamed $name to $newName<br />");
        fwrite($successFile, "Renamed $name to $newName\n");

        $name = $newName;
    }



    $path_parts = pathinfo($name);
    if (strpos($name, 'File:') === false &&
        isset($path_parts['extension']) &&
        in_array(strtolower($path_parts['extension']), ['mp3', 'mp4', 'mkv', 'ogg', 'oga', 'ogv', 'gif', 'tiff', 'png', 'jpg', 'jpeg', 'zip']))
        continue;
    if (in_array($path_parts['basename'], ['.', '..']))
        continue;

    $j++;

    if ($j <= $_GET['start']) continue;
    if ($j > $_GET['end']) break;

    fwrite($writeFile, "<tr><th colspan=4>$name ($j):</th></tr>");

    $file = ArchiveReaderFactory($protocol . '://' . $resource . str_replace($path, '', $name));
    $file->formatUrlCallback = 'processFile';
    if (!$file->error) $file->getContents();
}

fwrite($writeFile, '</table>Time: ' . (microtime(true) - $time) . '<br /><br /><br />');
fclose($writeFile);
apcu_store("av_srcset_ignore", $ignore);

if (!isset($_GET['stop']))
    echo '<script type="text/javascript">window.location = "aviewerSrcSetScanner.php?protocol=' . $_GET['protocol'] . '&resource=' . $_GET['resource'] . '&match=' . rawurlencode($_GET['match']) . '&start=' . ($_GET['start'] + ($_GET['end'] - $_GET['start'])) . '&end=' . ($_GET['end'] + ($_GET['end'] - $_GET['start'])) . '";</script>';
?>