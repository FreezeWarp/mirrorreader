<?php
/* Copyright (c) Joseph T. Parsons */
/* I did this without the manual; sue me for using regex. */
/* A basic overview of this file; note that it is still in early alpha and what-not:
1. We define all functions.
2. We check to see if a URL for lookup is specified.
 2a. If not, we list all stored URLs.
 2b. If it is, we:
  2bi. Check to see if it is defined in the cache. If it is, we get it and display it.
  2bii. If not, we determine whether it stored compressed. If so, we uncompress it, otherwise we link it. */

require('aviewerFunctions.php');

error_reporting(E_ALL);
$data = '';

$url = isset($_GET['url']) ? (string) $_GET['url'] : false; // Get the URL to display from GET.
$fileType = isset($_GET['type']) ? (string) $_GET['type'] : false; // Get the URL to display from GET.
$me = 'aviewer.php'; // We should change this to that fun constant I'm too lazy to look up; TODO
$store = '/home/joseph/Documents/WebOffline/'; // Configuration variable for where the domains are stored offline.
$cacheStore = '/var/www/cache/'; // Configuration variable for where we'll store uncompressed zip files.
$homeFile = 'index.html';
$passthru = false;
$selectHack = true;
$metaHack = true;
$metaDispose = true;
$scriptDispose = true;
$noscriptDispose = true;
$scriptEccentric = true;

if ($url === false) { // No URL specified.
  $fileScan = scandir($store); // Read the directory and return each file in the form of an array.

  echo 'Please choose a domain:<br /><br />';

  foreach ($fileScan AS $domain) { // List each of the stored domains.
    if (aviewer_isSpecial($domain)) continue; // Don't show ".", "..", etc.

    $domainNoZip = aviewer_stripZip($domain); // Domains can be zipped initially, so remove them if needed.

    echo "<a href=\"{$me}?url={$domainNoZip}/{$homeFile}\">{$domainNoZip}</a><br />";
  }
}
else { // URL specified
  $urlDomain = preg_replace('/^((http|https|ftp|mailto):(\/\/|)|)(([a-zA-Z0-9\.\-\_]+?)\.(com|net|org|info|us|co\.jp))\/(.+)$/', '\\4', $url); // This is prolly the worst way to do this; TODO
  $urlFile = preg_replace('/^((http|https|ftp|mailto):(\/\/|)|)(([a-zA-Z0-9\.\-\_]+?)\.(com|net|org|info|us|co\.jp))\/(.+)$/', '\\7', $url); // This is prolly the worst way to do this; TODO
  $urlDirectory = aviewer_dirPart($urlFile);
  $absPath = $cacheStore . $urlDomain . '/' . $urlFile;

//  echo preg_replace('/(([a-zA-Z0-9\.\-\_]+?)\.(com|net|org|info|us|co\.jp))\/(.+)/', '\\1 | \\2 | \\3 | \\4', $url);

  if (!aviewer_inCache($urlDomain)) {
    $storeScan = scandir($store); // Scan the directory that stores offline domains.

    if (in_array($urlDomain, $storeScan)) { // Check to see if the domain is in the store.
      symlink("{$store}/{$urlDomain}", "{$cacheStore}/{$urlDomain}");
    }
    elseif (in_array($urlDomain . '.zip', $storeScan)) {

    }
    else { // The domain isn't in the store.
      if ($passthru) {
        header('Location: ' . $url);
      }
      else {
        $data = 'Domain not found: "' . $urlDomain . '"';
        aviewer_basicTemplate($data);
        die();
      }
    }

    /* TODO: Uncompress */
  }

  if (is_dir($absPath)) { // Allow (minimal) directory viewing.
    if (is_file("{$absPath}/{$homeFile}")) { // Automatically redirect to the home/index file if it exists in the directory.
      header("Location: {$me}?url={$url}/{$homeFile}");
      die();
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
          case 'html':
          case 'htm':
          case 'shtml':
          case 'php':
          $fileType = 'html';
          break;

          case 'css':
          $fileType = 'css';
          break;

          case 'js':
          $fileType = 'js';
          break;

          default:
          $fileType = 'other';
          break;
        }

        if ($fileType == 'other') { // Try to autodetect file type by contents.
          if (preg_match('/^([\ \n]*)(\<\!DOCTYPE|\<html)/i', $contents)) {
            $fileType = 'html';
          }
        }
      }

      switch ($fileType) {
        case 'html':
        header('Content-type: text/html');
        echo aviewer_processHtml($contents);
        break;

        case 'css':
        header('Content-type: text/css');
        echo aviewer_processCSS($contents);
        break;

        case 'js':
        header('Content-type: text/javascript');
        echo aviewer_processJavascript($contents);
        break;

        case 'other':
        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $mimeType = finfo_file($finfo, $absPath);
        finfo_close($finfo);

        header('Content-type: ' . $mimeType);
  //`      if ($urlFileExt == 'zip') header('Content-Disposition: *; filename="' . filePart($urlFile) . '"');

        echo $contents;
        break;
      }
    }
    else {
      $data = 'File not found: "' . $absPath . '"';
      aviewer_basicTemplate($data);
      die();
    }
  }
}
?>