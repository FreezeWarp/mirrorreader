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
header('Content-Type: text/plain');

// Require Configuration Files
require(__DIR__ . '/vendor/autoload.php');
require('config.php');

// Define logs
$log = new \Monolog\Logger('FileNameFixer');
$log->pushHandler(new \Monolog\Handler\StreamHandler('FileNameFixer.log', \Monolog\Logger::INFO));
$log->pushHandler(new \Monolog\Handler\StreamHandler('php://output', \Monolog\Logger::INFO));

// Allow Unlimited Execution Time
$time = microtime(true);
ob_end_flush();
set_time_limit(0);

// Get $_GET
$resource = $_GET['resource'];
$protocol = $_GET['protocol'] ?? 'http';
$path = realpath(\MirrorReader\Processor::$store . $resource);

// Get All Objects in Path
$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);

// Register Our Domain Reader for the Resource (used for configuration info)
$domainReader = new \MirrorReader\Processor($protocol . '://' . $resource);

foreach($objects AS $name => $object) {
    $log->debug('Considering path', [$name]);


    if (strpos($name, "data:") !== false) {
        $log->info('Skipping data path', [$name]);
        continue;
    }


    // If the given object is a directory, but not a file, but a 1-appended file _does_ exist, and an index file does _not_ exist for the directory,
    // move the 1-appended file into the directory as its index file.
    if (is_dir($name)
        && !is_file($name)
        && is_file($name . '1')
        && !is_file($name . '/index.html')) {

        $log->info('Preparing to move 1-appended file', ["{$name}1", "{$name}/index.html"]);

        if (empty($_GET['trial']) && rename($name . '1', $name . '/index.html')) {
            $log->notice('Renamed 1-appended file', ["{$name}1", "{$name}/index.html"]);
        }
        else {
            $log->error('Failed to rename path', ["{$name}1", "{$name}/index.html"]);
        }

    }


    // If the given object is a file, and a 1-appended directory exists,
    // move that file into the directory and rename the directory without the 1 suffix.
    elseif (is_file($name) && is_dir("{$name}1")) {

        $log->info('Preparing to rename file into 1-appended directory', [$name, "{$name}1/index.html"]);

        if (empty($_GET['trial'])) {
            if (rename($name, "{$name}1/index.html")) {
	            $log->notice('Renamed file into 1-appended directory', [$name, "{$name}1/index.html"]);
            } else {
                $log->error('Failed to rename file into 1-appended directory', [$name, "{$name}1/index.html"]);
            }
        }


        $log->info('Preparing to rename 1-appended directory to regular directory', ["{$name}1", $name]);

        if (empty($_GET['trial'])) {
            if (rename("{$name}1", $name)) {
	            $log->notice('Renamed 1-appended directory to regular directory ', ["{$name}1", $name]);
	        } else {
                $log->error('Failed to rename 1-appended directory to regular directory ', ["{$name}1", $name]);
	        }
        }

    }


    // If the given object contains an ignored GET in its file name, remove it.
    elseif (preg_match('/(?|&)(' . implode('|', $domainReader->config['ignoreGETs']) . ')(=.*?)(&|$)/', $name)) {

        $newName = preg_replace_callback('/(\?|&)(' . implode('|', $domainReader->config['ignoreGETs']) . ')(=.*?)(&|$|\.)/', function($match) {
            return ($match[4] === '&' ? $match[1] : $match[4]);
        }, $name);

        $log->info('Preparing to rename file with ignored GET parameters', [$name, $newName]);

        if (empty($_GET['trial'])) {
	        if (rename($name, $newName)) {
		        $log->notice('Renamed file with ignored GET parameters', [$name, $newName]);
	        } else {
                $log->error('Failed to rename file with ignored GET parameters', [$name, $newName]);
	        }
	    }

    }
}