<?php

/**
 * This is a basic tool that scans an existing archive and tries to fill in any gaps. It can be used in a download gets partially corrupted, or your Heritrix job gets corrupted.
 * It even detects a small number of things Heritrix fails to, e.g. <img srcset>. I use it even on completed Heritrix jobs to ensure a full archive.
 * Additionally, it will clean up the directory structure a bit. MirrorReader handles the 1-suffixed directories fairly well (albeit at the cost of speed), but it does not handle 1-suffixed files, which this does deal with.
 * Also note that it will write 301 redirects where they are expected by the including files, unless the 301 is specified in config.php. This is good for archive viewing, but does mean you'll end up with duplicated files. (I find it worthwhile, in any case, to have them located in both places.)
 * Currently searches <a href>, <img src>, and <img srcset>.
 * Does not redownload existing files.
 * Does not check content type, but will only download files matching valid string/regex rules.
 */


// For now, report all errors.
error_reporting(E_ALL);
ini_set('display_errors', 'On');

// Require Configuration Files
require(__DIR__ . '/vendor/autoload.php');
require('config.php');

// Allow Unlimited Execution Time
$time = microtime(true);
ob_end_flush();
set_time_limit(0);

// Disable the script hacks by default, since they are liable to include too many files in our scan.
\MirrorReader\Processor::$domainConfiguration['default']['scriptHacks'] = [];

// Get $_GETs
$resource = $_GET['resource'];
$protocol = $_GET['protocol'] ?? 'http';
$match = $_GET['match'] ?? '.*';
$path = realpath('/Library/' . $resource);

$_GET['start'] = ($_GET['start'] ?? 0);
$_GET['end'] = ($_GET['end'] ?? 300);

// Find files that we should ignore based on previous runs
$ignore = apcu_exists("av_srcset_ignore_$resource") ? apcu_fetch("av_srcset_ignore_$resource") : [];

// Open Success, Fail Files for Logging
//$writeFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.' . microtime() . '.html', "a") or die("Unable to open write file!");
$writeFile = fopen("php://output", "w");
$successFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.successes.txt', "a") or die("Unable to open write file!");
$failFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.errors.txt', "a") or die("Unable to open write file!");





function processFile($srcUrl, $lastFile = false) {
    global $resource, $ignore, $writeFile, $match, $successFile, $failFile;

    if (!$srcUrl)
        return;
    if (strpos($srcUrl, '#') === 0)
        return;


    //if (stripos($srcFile->getFile(), "forums.") !== false)
    //    return;
    if (stripos($srcUrl, "/forums/") !== false)
        return;

    // Domain Exceptions
    if (stripos($srcUrl, "wikipedia.org/") !== false ||
        stripos($srcUrl, "youtube.com/") !== false ||
        stripos($srcUrl, "mediawiki.org/") !== false ||
        stripos($srcUrl, "facebook.com/") !== false ||
        stripos($srcUrl, "reddit.com/") !== false ||
        stripos($srcUrl, "twitter.com/") !== false ||
        stripos($srcUrl, "tumblr.com/share/") !== false ||
        stripos($srcUrl, "archive.org/") !== false ||
        stripos($srcUrl, "scorecardresearch.com/") !== false ||
        stripos($srcUrl, "pixel.wp.com/") !== false) {
        $status = 'fail [domainban]';
        $color = 'orange';
    }

    // File Exceptions
    elseif (stripos($srcUrl, "/api.php") !== false) {
        $status = 'fail [fileban]';
        $color = 'orange';
    }

    // Wiki Exceptions
    elseif (stripos($srcUrl, "/wp-json/") !== false ||
        stripos($srcUrl, "/oembed/") !== false ||
        stripos($srcUrl, "/ebay/") !== false ||
        stripos($srcUrl, "/ebaysearch/") !== false ||
        stripos($srcUrl, "/amazon/") !== false ||
        stripos($srcUrl, "/random/") !== false ||
        stripos($srcUrl, "/feed/") !== false ||
        stripos($srcUrl, ".msg") !== false ||
        stripos($srcUrl, "prev_next") !== false ||
        stripos($srcUrl, "/privmsg.php") !== false ||
        stripos($srcUrl, "/posting.php") !== false ||
        stripos($srcUrl, "/xmlrpc.php") !== false ||
        stripos($srcUrl, "Special:") !== false ||
        stripos($srcUrl, "Talk:") !== false ||
        stripos($srcUrl, "User:") !== false) {
        $status = 'fail [wikiban]';
        $color = 'orange';
    }

    // Protocol ban
    elseif (stripos($srcUrl, "javascript:") !== false ||
        stripos($srcUrl, "mailto:") !== false ||
        stripos($srcUrl, "irc:") !== false ||
        stripos($srcUrl, "/aim:") !== false) {
        $status = 'fail [protocolban]';
        $color = 'orange';
    }

    // GET Ban
    elseif (preg_match("/(&|\\?)(p=|sort=|do=add|do=sendtofriend|do=getinfo|do=markread|week=|view=next|view=previous|replytocom|advertisehereid=|oldid|mobileaction|veaction=edit|action=pm|action=formcreate|action=edit|action=create|action=history|action=info|action=printpage|action=register|action=lostpw|postingmode=|printable|parent=|redirect)/", $srcUrl) !== 0) {
        $status = 'fail [bad get]';
        $color = 'orange';
    }
    elseif (preg_match("/(newreply|sendmessage|newthread|cron|external|private|printthread|register|search|showpost)\.php/", $srcUrl) !== 0) {
        $status = 'fail [bad page]';
        $color = 'orange';
    }
    elseif (preg_match("/\/clientscript\//", $srcUrl) !== 0) {
        $status = 'fail [bad clientscript]';
        $color = 'orange';
    }
    elseif (preg_match("/$match/", $srcUrl) !== 1) {
        $status = 'fail [badmatch]';
        $color = 'orange';
    }
    else {
        $srcFile = \MirrorReader\Factory::get($srcUrl);
        $destFile = $srcFile->getFileStore();

        if (!$destFile) {
            $status = 'fail [nodest - you may need to create the root dir first]';
            $color = 'orange';
        }
        else {
            if (!is_dir(dirname($destFile))) {
                if (!\MirrorReader\MkdirIndex::execute(dirname($destFile))) {
                    $status = 'fail [direrror]';
                    $color = 'red';
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
                            mkdir(dirname(dirname($srcFile->fileStore301less)), 0777, true) or die('Could not create directory: ' . dirname(dirname($srcFile->fileStore301less)));

                        rename(dirname($destFile), dirname($srcFile->fileStore301less)) or die("Failed to rename 301 dir " . dirname($destFile) . " to " . dirname($srcFile->fileStore301less));
                        fwrite($successFile, "$srcUrl: renamed 301 directory " . dirname($destFile) . " to " . dirname($srcFile->fileStore301less) . "\n");
                    }
                    else {
                        if (!is_dir(dirname($srcFile->fileStore301less)))
                            mkdir(dirname($srcFile->fileStore301less), 0777, true) or die('Could not create directory: ' . dirname($srcFile->fileStore301less));

                        rename($destFile, $srcFile->fileStore301less) or die("Failed to rename 301 file $destFile to " . $srcFile->fileStore301less);
                        fwrite($successFile, "$srcUrl: renamed 301 file $destFile to " . $srcFile->fileStore301less . "\n");
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

                        $redirectObject = \MirrorReader\Factory::get($redirectLocation);

                        if (\MirrorReader\Processor::isFile($redirectObject->getFileStore())) {
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

                        elseif (file_put_contents($destFile, $path)) {
                            $status = 'success';
                            $color = 'green';
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
                    $ignore[] = $srcUrl;
                }
            }
        }
    }


    fwrite($writeFile, "<tr style='color:$color;'><td>" . $srcUrl . "</td><td>" . ($destFile ?? "") . "</td><td>" . $status . "</td></tr>");

    // If the file was successfully processed, log it and recurse
    if ($color === 'green') {
        // Sleep for the given seconds before continuing
        usleep(500000);

        // Write to Files
        fwrite($successFile, "$lastFile\t$srcUrl\t$destFile\t$status\n");
        fwrite($writeFile, "<tr><th colspan=4>$destFile (decended):</th></tr>");

        // Open the Resource Object
        $file = \MirrorReader\Factory::get($srcUrl);

        // Tell the Resource Object to invoke the processFile function whenever it encounters a URL.
        $file->formatUrlCallback = 'processFile';

        // As long as no error occurred when opening, get the file's contents, which should force the processing of all URLs.
        if (!$file->error)
            $file->getContents();
    }

    // If the file was not successfully processed, log it.
    elseif ($color === 'red') {
        usleep(10000000);
        fwrite($failFile, "$lastFile\t$srcUrl\t$destFile\t$status\n");
    }
}

$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);

$j = 0;
$domainReader = new \MirrorReader\Processor($protocol . '://' . $resource);

fwrite($writeFile, '<table>');

foreach($objects AS $name => $object) {
    // These files were usually downloaded by accident; don't try reading them.
    if (strpos($name, "data:") !== false)
        continue;

    // Get the pathinfo
    $path_parts = pathinfo($name);

    // Don't read binary files (unless "File:" is in the name, which usually indicates a Wikimedia file)
    if (strpos($name, 'File:') === false &&
        isset($path_parts['extension']) &&
        in_array(strtolower($path_parts['extension']), ['mp3', 'mp4', 'mkv', 'ogg', 'oga', 'ogv', 'gif', 'tiff', 'png', 'jpg', 'jpeg', 'zip']))
        continue;

    // Don't read the relative directories.
    if (in_array($path_parts['basename'], ['.', '..']))
        continue;

    // Skip Ahead/End
    $j++;

    if ($j <= $_GET['start']) continue;
    if ($j > $_GET['end']) break;

    // Log the file
    fwrite($writeFile, "<tr><th colspan=4>$name ($j):</th></tr>");

    // Open the Resource Object
    $file = \MirrorReader\Factory::get($protocol . '://' . $resource . str_replace($path, '', $name));

    // Tell the Resource Object to invoke the processFile function whenever it encounters a URL.
    $file->formatUrlCallback = 'processFile';

    // As long as no error occurred when opening, get the file's contents, which should force the processing of all URLs.
    if (!$file->error)
        $file->getContents();
}

fwrite($writeFile, '</table>Time: ' . (microtime(true) - $time) . '<br /><br /><br />');
fclose($writeFile);

apcu_store("av_srcset_ignore_$resource", $ignore);

if (!isset($_GET['stop']))
    echo '<script type="text/javascript">window.location = "./spider.php?protocol=' . $_GET['protocol'] . '&resource=' . $_GET['resource'] . '&match=' . rawurlencode($_GET['match'] ?? '') . '&start=' . ($_GET['start'] + ($_GET['end'] - $_GET['start'])) . '&end=' . ($_GET['end'] + ($_GET['end'] - $_GET['start'])) . '";</script>';
?>
