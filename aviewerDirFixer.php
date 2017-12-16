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
ob_end_flush();
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require('aviewerFunctions.php');
set_time_limit(0);
require_once('aviewerConfiguration.php');
$domainConfiguration['default']['scriptHacks'] = [];
$resource = $_GET['resource'];
$protocol = $_GET['protocol'] ?? 'http';
$match = $_GET['match'] ?? '.*';
$path = realpath('/Library/' . $resource);
//$writeFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.' . microtime() . '.html', "a") or die("Unable to open write file!");
$writeFile = fopen("php://output", "w");
$successFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.successes.txt', "a") or die("Unable to open write file!");
$failFile = fopen('srcSetLog.' . rtrim($resource, '/') . '.errors.txt', "a") or die("Unable to open write file!");

$_GET['start'] = ($_GET['start'] ?? 0);
$_GET['end'] = ($_GET['end'] ?? 300);

function mkdir_index($dirName) {
    global $resource, $writeFile, $match, $successFile, $failFile;
    
    if (!mkdir($dirName, 0777, true)) {
        $baseFile = $dirName;

        while (!is_file($baseFile) && $baseFile !== '') {
            $baseFile = rtrim(dirname($baseFile), '/');
        }

        if ($baseFile !== '') {
            rename($baseFile, $baseFile . '~temp') or die("Could not rename $baseFile to $baseFile~temp");
            mkdir($dirName, 0777, true) or die('Mkdir failed. Temp file leftover: ' . $baseFile . '~temp');
            rename($baseFile . '~temp', $baseFile . '/index.html') or die("Could not rename $baseFile~temp to $baseFile/index.html");

            fwrite($successFile, "$dirName: created $dirName directory, moving in $baseFile as $baseFile/index.html\n");
            
            return true;
        }
        else return false;
    }
}

$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);

$j = 0;
$domainReader = new ArchiveReader($protocol . '://' . $resource);

fwrite($writeFile, '<table>');
foreach($objects AS $name => $object) {
    if (strpos($name, "data:") !== false) continue;

    if (!is_file($name)) {
        if (is_dir($name) && is_file($name . '1') && !is_file($name . '/index.html')) {
            if (rename($name . '1', $name . '/index.html')) {
                fwrite($writeFile, "Renamed file {$name}1 to {$name}/index.html<br />");
                fwrite($successFile, "Renamed file {$name}1 to {$name}/index.html\n");
            }
            else
                die('Rename failed.');
        }

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
}

fwrite($writeFile, '</table>Time: ' . (microtime(true) - $time) . '<br /><br /><br />');
fclose($writeFile);

if (!isset($_GET['stop']))
    echo '<script type="text/javascript">window.location = "aviewerDirFixer.php?protocol=' . $_GET['protocol'] . '&resource=' . $_GET['resource'] . '&start=' . ($_GET['start'] + ($_GET['end'] - $_GET['start'])) . '&end=' . ($_GET['end'] + ($_GET['end'] - $_GET['start'])) . '";</script>';
?>
