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

/* A basic overview of this file; note that it is still in early alpha and what-not:
1. We define all functions.
2. We check to see if a URL for lookup is specified.
 2a. If not, we list all stored URLs.
 2b. If it is, we:
  2bi. Check to see if it is defined in the cache. If it is, we get it and display it.
  2bii. If not, we determine whether it stored compressed. If so, we uncompress it, otherwise we link it. */

/* Additionally, a few other thoughts:
 * There are far simpler ways one can go about doing what this script does -- displaying a website archive. However, there are certain limitations to these other methods. The best example is with Javascript. Normally, sites that store URLs in Javascript are going to have trouble displaying in an archive reader. Here, however, a number of hacks are used that detect and fix these issues. Technically, one could also simulate this entire process by simply changing the document base. However, even when I tried just doing this, I found that a number of problems are apt to crop up. At the very least, sites that include their domain in their links aren't going to display using this method. Ultimately, there is no truly simple way of fixing this . What I did is the hard, and perhaps uneccessary, way. It also allows for customisations that simply can't be done in any simpler way, like rewriting file names and providing (eventually) UserStyles and UserScripts-like hacks. Is there are a simpler way? Yes. Is there a more customisable and fool-proof way? There simply isn't..
 * Configuration can be set per-domain (it's wonderful), making a greater array of features possible. The configuration directives are subject to change (and passthru in particular will likely be removed.) The most common toggle is "scriptEccentric", and is a great place to start with some websites, as it will potentially fix the JavaScripts on these sites. It is also liable to break them.
 * A part of the goal of this is to have a small size, and not be some bloated mess. Ideally, this should never exceed 1,000 lines, sans configuration. As a result, it will be limited in some features.
 * Finally, it is currently not that well optimised. It's getting there, though. Hopefully, it will support ZIP seeks, use less regex, 
 */

require('aviewerConfiguration.php');
require('aviewerFunctions.php');
error_reporting(E_ALL);
$data = '';

$url = isset($_GET['url']) ? (string) urldecode($_GET['url']) : false; // Get the URL to display from GET. Note: URL must be functional in a web browser for it to be parsed. (In other words, broken URLs, like "http:google.com" are not fixed in the script. This is an archive viewer, not a Frankenstein machine.)
$fileType = isset($_GET['type']) ? (string) $_GET['type'] : false; // Get the URL to display from GET.
$me = $_SERVER['PHP_SELF']; // This file.

if ($url === false) { // No URL specified.
  $fileScan = scandir($store); // Read the directory and return each file in the form of an array.
  
  $data = '';

  foreach ($fileScan AS $domain) { // List each of the stored domains.
    if (aviewer_isSpecial($domain)) continue; // Don't show ".", "..", etc.
    
    if (is_dir("{$store}/{$domain}") || substr($domain, -3, 3) == 'zip') { // Only show ZIPed files and directories.
      $domainNoZip = aviewer_stripZip($domain); // Domains can be zipped initially, so remove them if needed.
      $data .= "<a href=\"{$me}?url={$domainNoZip}/{$homeFile}\">{$domainNoZip}</a><br />";
    }
  }

  echo aviewer_basicTemplate($data, 'Choose a Domain');
}

else { // URL specified
  if (stripos($url, 'http:') !== 0 && stripos($url, 'https:') !== 0 && stripos($url, 'mailto:') !== 0 && stripos($url, 'ftp:') !== 0) { // Domain Not Included, Add It
    $url = 'http://' . $url;
  }

  $urlParts = parse_url($url);
  while(strpos($urlParts['path'], '//') !== false) $urlParts['path'] = str_replace('//', '/', $urlParts['path']); // Get rid of excess slashes.

  $urlParts['dir'] = aviewer_filePart($urlParts['path'], 'dir');
  $urlParts['file'] = aviewer_filePart($urlParts['path'], 'file');

  // Get proper configuration.
  if (isset($domainConfiguration[$urlParts['host']])) $config = array_merge($domainConfiguration['default'], $domainConfiguration[$urlParts['host']]);
  else $config = $domainConfiguration['default'];

  /* Handle $config Redirects */
  if (isset($config['redirect'])) {
    foreach ($config['redirect'] AS $find => $replace) {
      if (strpos($urlParts['host'] . $urlParts['path'], $find) === 0) {
        $newLocation = str_replace($find, $replace, $urlParts['host'] . $urlParts['path']);
        header("Location: {$me}?url={$newLocation}");
        die(aviewer_basicTemplate("<a href=\"{$me}?url={$newLocation}\">Redirecting.</a>"));
      }
    }
  }

  if (!aviewer_inCache($urlParts['host'])) {
    $storeScan = scandir($store); // Scan the directory that stores offline domains.
    if (in_array($urlParts['host'], $storeScan)) { // Check to see if the domain is in the store.
      symlink("{$store}/{$urlParts[domain]}", "{$config[cacheStore]}/{$urlParts[domain]}");
    }
    elseif (in_array($urlDomain . '.zip', $storeScan)) {
      $zip = new ZipArchive;

      echo aviewer_basicTemplate('Loading archive. This may take a moment...<br />', 'Processing...', 1);
      aviewer_flush();

      if ($zip->open("{$store}/$urlParts[domain].zip") === TRUE) {
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
      if ($config['passthru'] || $_GET['passthru']) {
        header('Location: ' . $url); // Note: This redirects to the originally embedded URL (thus, we aren't touching it at all).
        die(aviewer_basicTemplate("<a href=\"$url\">Redirecting.</a>"));
      }
      else {
        echo aviewer_basicTemplate('Domain not found: "' . $urlParts['host'] . '"');
        die();
      }
    }

    /* TODO: Uncompress */
  }
  
  $absPath = $config['cacheStore'] . $urlParts['host'] . $urlParts['path'];

  if (is_dir($absPath)) { // Allow (minimal) directory viewing.
    if (is_file("{$absPath}/{$config[homeFile]}")) { // Automatically redirect to the home/index file if it exists in the directory.
      header("Location: {$me}?url=$urlParts[path]/{$config[homeFile]}");
      die(aviewer_basicTemplate("<a href=\"{$me}?url={$urlParts[path]}/{$config[homeFile]}\">Redirecting</a>"));
    }
    else {
      $dirFiles = scandir($absPath); // Get all files.
      
      $data = '';

      foreach ($dirFiles AS $file) { // List each one.
        if (aviewer_isSpecial($file)) continue; // Don't show ".", "..", etc.
        $data .= "<a href=\"{$me}?url={$url}/{$file}\">$file</a><br />";
      }

      echo aviewer_basicTemplate($data, "Directory \"{$url}\"");
    }
  }
  else {
    if (file_exists($absPath)) {
      $contents = file_get_contents($absPath); // Get the file contents.

      $urlFileParts = explode('.', $urlParts['file']);
      $urlFileExt = $urlFileParts[count($urlFileParts) - 1];

      if (!$fileType) {
        switch ($urlFileExt) { // Attempt to detect file type by extension.
          case 'html': case 'htm': case 'shtml': case 'php': $fileType = 'html';  break;
          case 'css':                                        $fileType = 'css';   break;
          case 'js':                                         $fileType = 'js';    break;
          default:                                           $fileType = 'other'; break;
        }

        if ($fileType == 'other' && preg_match('/^([\ \n]*)(\<\!DOCTYPE|\<html)/i', $contents)) $fileType = 'html';
      }

      switch ($fileType) {
        case 'html': header('Content-type: text/html');       echo aviewer_processHtml($contents);       break;
        case 'css':  header('Content-type: text/css');        echo aviewer_processCSS($contents);        break;
        case 'js':   header('Content-type: text/javascript'); echo aviewer_processJavascript($contents); break;
        case 'other':
        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $mimeType = finfo_file($finfo, $absPath);
        finfo_close($finfo);

        header('Content-type: ' . $mimeType);
        if (in_array($urlFileExt, array('zip', 'tar', 'gz', 'bz2', '7z', 'lzma'))) header('Content-Disposition: *; filename="' . $urlParts['file'] . '"');

        echo $contents;
        break;
      }
    }
    else {
      if ($config['passthru']) {
        header('Location: ' . $url); // Redirect to the URL as originally passed. (Though, if no prefix was available, "http:" will have been added.)
        die(aviewer_basicTemplate("<a href=\"$url\">Redirecting.</a>"));
      }
      else {
        die(aviewer_basicTemplate('File not found: "' . $absPath . '"'));
      }
    }
  }
}
?>