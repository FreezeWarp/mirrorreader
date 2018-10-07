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
ini_set('display_startup_errors', 'On');

// Require Configuration Files
require(__DIR__ . '/vendor/autoload.php');
require('config.php');
header('Content-Type: text/plain');

// Allow Unlimited Execution Time
set_time_limit(0);

// Get $_GETs
$resource = $_GET['resource'];
$protocol = $_GET['protocol'] ?? 'http';
$match = $_GET['match'] ?? false;
$path = realpath(\MirrorReader\Processor::$store . $resource);

// Disable the script hacks by default, since they are liable to include too many files in our scan.
\MirrorReader\Processor::$domainConfiguration['default']['scriptHacks'] = [];
$domainReader = new \MirrorReader\Processor($protocol . '://' . $resource);

$spider = new \MirrorReader\Spider($resource, $match);

foreach((new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST)) AS $name => $object) {
    // These files were usually downloaded by accident; don't try reading them.
    if (strpos($name, "data:") !== false) {
        \MirrorReader\Logger::getLogger("Spider-{$resource}")->info('Skipping data: URL', [$name]);
        continue;
    }

    // Get the pathinfo
    $path_parts = pathinfo($name);

    \MirrorReader\Logger::getLogger("Spider-{$resource}")->debug('Encountered path', [$name, $path_parts]);

    // Don't read binary files (unless "File:" is in the name, which usually indicates a Wikimedia file)
    if (strpos($name, 'File:') === false &&
        isset($path_parts['extension']) &&
        in_array(strtolower($path_parts['extension']), ['mp3', 'mp4', 'mkv', 'ogg', 'oga', 'ogv', 'gif', 'tiff', 'png', 'jpg', 'jpeg', 'zip'])
    ) {
        \MirrorReader\Logger::getLogger("Spider-{$resource}")->info('Skipping binary file', [$name]);
        continue;
    }

    // Don't read the relative directories.
    if (in_array($path_parts['basename'], ['.', '..']))
        continue;

    // // // What happens now is pretty straight-forward -- we actually read the current file, process it for all links, and submit any links that we don't have data for to the queue.

    // Log the file
    \MirrorReader\Logger::getLogger("Spider-{$resource}")->info('Processing URL', [$name]);

    // Open the Resource Object
    $file = \MirrorReader\Factory::get($protocol . '://' . $resource . str_replace($path, '', $name));

    // Tell the Resource Object to invoke the processFile function whenever it encounters a URL.
    $file->formatUrlCallback = [$spider, 'processFile'];

    // As long as no error occurred when opening, get the file's contents, which should force the processing of all URLs.
    if ($file->error) {
        \MirrorReader\Logger::getLogger("Spider-{$resource}")->warn('Error when processing URL', [$name, $file->error]);
    } else {
        $file->getContents();
    }
}