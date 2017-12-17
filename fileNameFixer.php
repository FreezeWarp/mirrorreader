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

// Get $_GET
$resource = $_GET['resource'];
$protocol = $_GET['protocol'] ?? 'http';
$path = realpath('/Library/' . $resource);

$_GET['start'] = ($_GET['start'] ?? 0);
$_GET['end'] = ($_GET['end'] ?? 300);

// Open Success, Fail Files for Logging
$successFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.successes.txt', "a") or die("Unable to open write file!");
$failFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.errors.txt', "a") or die("Unable to open write file!");
$writeFile = fopen("php://output", "w");

// Get All Objects in Path
$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);

// Register Our Domain Reader for the Resource (used for configuration info)
$domainReader = new \MirrorReader\Processor($protocol . '://' . $resource);


// Start Processing
fwrite($writeFile, '<table>');

foreach($objects AS $name => $object) {
    if (strpos($name, "data:") !== false) continue;


    // If the given object is a directory, but not a file, but a 1-appended file _does_ exist, and an index file does _not_ exist for the directory,
    // move the 1-appended file into the directory as its index file.
    if (is_dir($name)
        && !is_file($name)
        && is_file($name . '1')
        && !is_file($name . '/index.html')) {

        if (rename($name . '1', $name . '/index.html')) {
            fwrite($writeFile, "Renamed file {$name}1 to {$name}/index.html<br />");
            fwrite($successFile, "Renamed file {$name}1 to {$name}/index.html\n");
        }
        else
            die('Rename failed.');

    }


    // If the given object is a file, and a 1-appended directory exists,
    // move that file into the directory and rename the directory without the 1 suffix.
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


    // If the given object contains an ignored GET in its file name, remove it.
    elseif (preg_match('/(?|&)(' . implode('|', $domainReader->config['ignoreGETs']) . ')(=.*?)(&|$)/', $name)) {
        $newName = preg_replace_callback('/(\?|&)(' . implode('|', $domainReader->config['ignoreGETs']) . ')(=.*?)(&|$|\.)/', function($match) {
            return ($match[4] === '&' ? $match[1] : $match[4]);
        }, $name);

        rename($name, $newName) or die("Could not rename $name to $newName");

        fwrite($writeFile,  "Renamed $name to $newName<br />");
        fwrite($successFile, "Renamed $name to $newName\n");

        $name = $newName;
    }
}

fwrite($writeFile, '</table>Time: ' . (microtime(true) - $time) . '<br /><br /><br />');
fclose($writeFile);


// Continue with More Objects
if (!isset($_GET['stop']))
    echo '<script type="text/javascript">window.location = "./fileNameFixer.php?protocol=' . $_GET['protocol'] . '&resource=' . $_GET['resource'] . '&start=' . ($_GET['start'] + ($_GET['end'] - $_GET['start'])) . '&end=' . ($_GET['end'] + ($_GET['end'] - $_GET['start'])) . '";</script>';
?>
