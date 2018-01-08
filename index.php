<?php
/*
   Copyright 2011 Joseph T. Parsons

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/


/* Current support notes:
 * General Pathing
   * Handles absolute and relative paths (incl. "../" and "./" directories)
   * Handles paths including domains without issue
   * Handles most pathing quirks -- e.g. starting with //, ?, #, etc.
 * HTML
   * Handles <base> tags
   * Handles all common attributes, e.g. <a href>, <script src>, <link href>, <img src>, and so-on.
   * Handles <img srcset>, <source>
   * Handles <video poster>
   * Handles <body background>, <table background>, <tr background>, <td background>
 * CSS
   * Handles URL()
 * Javascript
   * Is capable of varying degrees of file detection in strings.
 * Misc
   * Detects & removes a small handful of behaviours that are no longer fully supported on modern browsers, e.g. IE comment hacks.
   * Supports ZIP files if they are properly encoded (no single "root" directory, etc.)
   * Handles mirror writer's somewhat annoying behaviour of creating directories with an appended 1 if a file of that name already existed. (Not supported in ZIPs yet, however.)

 * Future Plans:
 * I'd like to enable better viewing of files in the browser directly. For instance, viewing ZIPs.
 * Searching is essential, but is going to be difficult. It would definitely be some form of a keyword search, probably connected to an SQLlite database.

 * The patcher script:
 * The patcher script exists to complete/fix/cleanup MirrorWriter mirrors. It's not perfect, but because it relies on the MirrorReader class's parsing of things, it is guaranteed to know if there's a file MirrorReader is trying to access but doesn't have mirrored.
 * Of particular note, and its original reason for existence, is that it can parse out srcset attributes that are otherwise ignored by heritrix. */


// For now, report all errors.
error_reporting(E_ALL);
ini_set('display_errors', 'On');

// Require Configuration Files
require(__DIR__ . '/vendor/autoload.php');
require('config.php');

// Allow Crazy Regular Expressions (TODO: probably rewrite so we don't need them. I have observed this causing script execution to take more than 30 seconds.)
ini_set('pcre.backtrack_limit' , 1000000000);


/*********************************************
 *** DISPLAY DOMAIN LIST, IF NOT URL GIVEN ***
 **** ONLY .rar, .zip FILES WILL BE SHOWN ***
 *********************************************/

if (!isset($_GET['url'])) {
    ?>
    <style>
        body {
            padding: 20px;
        }
    </style>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.3/css/bootstrap.min.css" integrity="sha384-Zug+QiDoJOrZ5t4lssLdxGhVrurbmBWopoEl+M6BdEfwnCJZtKxi1KgxUyJq13dy" crossorigin="anonymous">
    <h1>Choose a Domain</h1><hr />
    <?php
    $fileScan = scandir(\MirrorReader\Processor::$store);

    foreach ($fileScan AS $domain) {
        if (substr($domain, -4, 4) == '.zip' || substr($domain, -4, 4) == '.rar') {
            $domainNoZip = substr($domain, 0, -4);

            echo "<a href=\"" . MirrorReader\Processor::getLocalPath("http://" . $domainNoZip) . "\">{$domainNoZip}</a><br />";
        }
    }

}


/*********************************************
 *** DISPLAY GIVEN URL, IF AVAILABLE *********
 *********************************************/

else {
    \MirrorReader\Factory::registerShutdownFunction();
    \MirrorReader\ZipFactory::registerShutdownFunction();
    \MirrorReader\RarFactory::registerShutdownFunction();
    $file = \MirrorReader\Factory::get($_GET['url']);

    if (isset($_GET['type'])) $file->setFileType($_GET['type']);

    if ($file->isDir || isset($_GET['showDir'])) { // Allow (minimal) directory viewing. TODO: zips
        echo '<style>
            body {
                padding: 20px;
            }
        </style>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.3/css/bootstrap.min.css" integrity="sha384-Zug+QiDoJOrZ5t4lssLdxGhVrurbmBWopoEl+M6BdEfwnCJZtKxi1KgxUyJq13dy" crossorigin="anonymous">
        <h1>Directory: ' . $file->getFile() . '</h1><hr />';

        foreach (scandir(rtrim(dirname($file->getFileStore()), '/') . '/') AS $fileName) { // List each one.
            if (\MirrorReader\Processor::isSpecial($fileName)) continue; // Don't show ".", "..", etc.

            echo "<a href=\"" . $file->getLocalPath($file->getFile() . $fileName) . "\">$fileName</a><br />";
        }
    }
    elseif ($file->error) {
        die('Error: ' . $file->error . ': "' . $file->getFileStore() . '" (URL: "' . $_GET['url'] . '" => ' . $file->getFile() . '")');
    }
    else {
        $file->echoContents();
    }
}
?>
