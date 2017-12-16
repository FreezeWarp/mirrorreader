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

error_reporting(E_ALL);
$store = '/Library/';
ini_set('display_errors', 'On');
require('aviewerConfiguration.php');
require('aviewerFunctions.php');
ini_set('pcre.backtrack_limit' , 1000000000);


if (!isset($_GET['url'])) { // No URL specified.
    $fileScan = scandir($store); // Read the directory and return each file in the form of an array.
    $data = '';

    foreach ($fileScan AS $domain) { // List each of the stored domains.
        if (ArchiveReader::isSpecial($domain)) continue; // Don't show ".", "..", etc.

        if (is_dir("{$store}/{$domain}") || substr($domain, -3, 3) == 'zip') { // Only show ZIPed files and directories.
            $domainNoZip = aviewer_stripZip($domain); // Domains can be zipped initially, so remove them if needed.
            $data .= "<a href=\"{$me}?url={$domainNoZip}/\">{$domainNoZip}</a><br />";
        }
    }

    echo aviewer_basicTemplate($data, 'Choose a Domain');
}

else { // URL specified
    $file = ArchiveReaderFactory($_GET['url']);

    if (isset($_GET['type'])) $file->setFileType($_GET['type']);
    if ($file->error) {
        die(aviewer_basicTemplate('Error: ' . $file->error . ': "' . $file->getFileStore() . '" (URL: "' . $_GET['url'] . '" => ' . $file->getFile() . '")'));
    }

    // Get proper configuration.

    /*  if (!aviewer_inCache($urlParts['host'])) {
        $storeScan = scandir($store); // Scan the directory that stores offline domains.
        if (in_array($urlParts['host'], $storeScan)) { // Check to see if the domain is in the store.
          symlink("{$store}/{$urlParts['host']}", "{$config['cacheStore']}/{$urlParts['host']}") or die(aviewer_basicTemplate("Could not create symlink. Are directory permissions set correctly?<br /><br />Source: {$store}/{$urlParts['host']}/<br />Link Destination: {$config['cacheStore']}/{$urlParts['host']}/", '<span class="error">Error</span>')); // Note, because I couldn't figure it out: symlink params can not contain end slashes
        }
        elseif (in_array($urlParts['host'] . '.zip', $storeScan)) {
          $zip = new ZipArchive;

          echo aviewer_basicTemplate('Loading archive. This may take a moment...<br />', 'Processing...', 1);
          aviewer_flush();

          if ($zip->open("{$store}/$urlParts[host].zip") === TRUE) {
            echo aviewer_basicTemplate('Unzipping. This may take a few moments...<br />', '', 2);
            aviewer_flush();
            $zip->extractTo($config['cacheStore']);
            $zip->close();

            die(aviewer_basicTemplate("Archive Loaded. <a href=\"{$me}?url={$url}\">Redirecting.</a><script type=\"text/javascript\">window.location.reload();</script>", '', 2));
          }
          else {
            die('Zip Extraction Failed.');
          }
        }
        else { // The domain isn't in the store.
          if ($config['passthru'] || $passthru) {
            header('Location: ' . $url); // Note: This redirects to the originally embedded URL (thus, we aren't touching it at all).
            die(aviewer_basicTemplate("<a href=\"$url\">Redirecting.</a>"));
          }
          else {
            echo aviewer_basicTemplate('Domain not found: "' . $urlParts['host'] . '"');
            die();
          }
        }
      }*/



    if ($file->isDir || isset($_GET['showDir'])) { // Allow (minimal) directory viewing. TODO: zips
        $data = '';

        foreach (scandir($file->dirPath) AS $fileName) { // List each one.
            if (ArchiveReader::isSpecial($fileName)) continue; // Don't show ".", "..", etc.

            $data .= "<a href=\"{$file->scriptDir}?url=" . $file->getFile() . "/{$fileName}\">$fileName</a><br />";
        }

        echo aviewer_basicTemplate($data, "Directory \"{$file->getFile()}\"");
    }
    else {
        $file->echoContents();
    }
}
?>
