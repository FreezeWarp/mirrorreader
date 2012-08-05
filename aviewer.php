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

$url = isset($_GET['url']) ? (string) urldecode($_GET['url']) : false; // Get the URL to display from GET.
$fileType = isset($_GET['type']) ? (string) $_GET['type'] : false; // Get the URL to display from GET.
$me = $_SERVER['PHP_SELF']; // This file.

if ($url === false) { // No URL specified.
  $fileScan = scandir($store); // Read the directory and return each file in the form of an array.

  echo 'Please choose a domain:<br /><br />';

  foreach ($fileScan AS $domain) { // List each of the stored domains.
    if (aviewer_isSpecial($domain)) continue; // Don't show ".", "..", etc.
    
    if (is_dir("{$store}/{$domain}") || substr($domain, -3, 3) == 'zip') { // Only show ZIPed files and directories.
      $domainNoZip = aviewer_stripZip($domain); // Domains can be zipped initially, so remove them if needed.
      echo "<a href=\"{$me}?url={$domainNoZip}/{$homeFile}\">{$domainNoZip}</a><br />";
    }
  }
}

else { // URL specified
  $urlPrefixless = preg_replace('/^((http|https|ftp|mailto):(\/\/|)|)/', '', $url);
  while(strpos($urlPrefixless, '//') !== false) $urlPrefixless = str_replace('//', '/', $urlPrefixless); // Get rid of excess slashes.

  $urlDomain = preg_replace('/^(([a-zA-Z0-9\.\-\_]+?)\.(com|net|org|info|us|co\.jp))(\/(.*)|)$/', '\\1', $urlPrefixless); // This is prolly the worst way to do this; TODO
  $urlFile = preg_replace('/^(([a-zA-Z0-9\.\-\_\?\&\=]+?)\.(com|net|org|info|us|co\.jp))(\/(.*)|)$/', '\\4', $urlPrefixless); // This is prolly the worst way to do this; TODO

  $urlDirectory = aviewer_dirPart($urlFile);
  $absPath = $cacheStore . $urlDomain . '/' . $urlFile;

  // Get proper configuration.
  if (isset($domainConfiguration[$urlDomain])) $config = array_merge($domainConfiguration['default'], $domainConfiguration[$urlDomain]);
  else $config = $domainConfiguration['default'];

  if (!aviewer_inCache($urlDomain)) {
    $storeScan = scandir($store); // Scan the directory that stores offline domains.
    if (in_array($urlDomain, $storeScan)) { // Check to see if the domain is in the store.
      symlink("{$store}/{$urlDomain}", "{$cacheStore}/{$urlDomain}");
    }
    elseif (in_array($urlDomain . '.zip', $storeScan)) {
      $zip = new ZipArchive;
      if ($zip->open("{$store}/{$urlDomain}.zip") === TRUE) {
        $zip->extractTo("{$cacheStore}");
        $zip->close();
      }
      else {
        die('Zip Extraction Failed.');
      }
    }
    else { // The domain isn't in the store.
      if ($config['passthru'] || $_GET['passthru']) {
        header('Location: ' . $url); // Note: This redirects to the originally embedded URL (thus, we aren't touching it at all).
        die("<a href=\"$url\">Redirecting.</a>");
      }
      else {
        if (!$_SERVER['HTTP_REFERER']) {
          $data = 'Domain not found: "' . $urlDomain . '"';
          aviewer_basicTemplate($data);
        }

        die();
      }
    }

    /* TODO: Uncompress */
  }

  /* Handle $config Redirects */
  if (isset($config['redirect'])) {
    foreach ($config['redirect'] AS $find => $replace) {
      if (strpos($urlDomain . $urlFile, $find) === 0) {
        $newLocation = str_replace($find, $replace, $urlDomain . $urlFile);
        header("Location: {$me}?url={$newLocation}");
        die("<a href=\"{$me}?url={$newLocation}\">Redirecting.</a>");
      }
    }
  }
  
  if (is_dir($absPath)) { // Allow (minimal) directory viewing.
    if (is_file("{$absPath}/{$homeFile}")) { // Automatically redirect to the home/index file if it exists in the directory.
      header("Location: {$me}?url={$urlDomain}{$urlFile}/{$homeFile}");
      die("<a href=\"{$me}?url={$urlDomain}{$urlFile}/{$homeFile}\">Redirecting</a>");
    }
    else {
      $dirFiles = scandir($absPath); // Get all files.
      $data = "<h1>Directory \"{$url}\"</h1><hr />";

      foreach ($dirFiles AS $file) { // List each one.
        if (aviewer_isSpecial($file)) continue; // Don't show ".", "..", etc.
        $data .= "<a href=\"{$me}?url={$url}/{$file}\">$file</a><br />";
      }

      aviewer_basicTemplate($data);
    }
  }
  else {
    if (file_exists($absPath)) {
      $contents = file_get_contents($absPath); // Get the file contents.

      $urlFileParts = explode('.', $urlFile);
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
        if (in_array($urlFileExt, array('zip', 'tar', 'gz', 'bz2', '7z', 'lzma'))) header('Content-Disposition: *; filename="' . filePart($urlFile) . '"');

        echo $contents;
        break;
      }
    }
    else {
      if ($config['passthru']) {
        if (strpos($url, 'http:') === 0 || strpos($url, 'https:') === 0 || strpos($url, 'ftp:') === 0 || strpos($url, 'mailto:') === 0) $redirectUrl = $url; // If none of the main prefixes exist, we will assume the URL passed does not have a prefix, and will append the "http:" prefix to the base URL.
        else $redirectUrl = 'http://' . $urlDomain . $urlFile;

        header('Location: ' . $redirectUrl);
        die("<a href=\"$redirectUrl\">Redirecting.</a>");
      }
      else if (!$_SERVER['HTTP_REFERER']) {
        $data = 'File not found: "' . $absPath . '"';
        aviewer_basicTemplate($data);
      }

      die();
    }
  }
}
?>