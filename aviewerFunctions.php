<?php
/* Copyright (c) Joseph T. Parsons */

function aviewer_debug($text) { // I dunno what this is.
//  error_log(print_r($text) . "\n\n", 'log.txt');
  var_dump($text);

  return false;
}

function aviewer_isZip($file) {
  $file = (string) $file; // God, I wish this could be done in the function line.

  if (preg_match('/\.zip$/', $file)) { return true; }  // This is prolly the worst way of doing this (like, duh); TODO
  return false;
}

function aviewer_stripZip($file) {
  $file = (string) $file; // God, I wish this could be done in the function line.

  if (aviewer_isZip($file)) { return preg_replace('/^(.+)\.zip$/', '\\1', $file); } // This is prolly the worst way of doing this (like, duh); TODO
  else { return $file; }
}

function aviewer_inCache($domain) {
  global $cacheStore;

  $fileScan = (array) scandir($cacheStore); // Read the directory and return each file in the form of an array.

  if (in_array($domain, $fileScan)) { return true; } // TODO
  else { return false; }
}

function aviewer_unArchive($text, $maxDepth = 1) { // Unarchive ZIP files, and optionally recursive into the directories and unzip all zipped files in them. Note that zip files follow these rules: (1) if they contain a singular parent directory, the directory will be igored in the created tree; (2) the directory will be named based on the zip name (e.g. fun.zip = /fun); (3) if a directory exists to match the zip name directory, the file will not be unzipped; (4) the zip name directory must NOT contain non-alphanumeric characters; (5) the original zip will not be deleted; it can still be safely referrenced by files if needed (a zip file is assumed to be downloaded when referrenced in $_GET[url]]

}

function aviewer_format($file) { // Attempts to format URLs -- absolute or relative -- so that they can be loaded with the viewer.
  global $me, $urlDomain, $urlDirectory; // Oh, sue me. I'll make it a class or something later.

  if (preg_match('/^(http|https|ftp|mailto)\:/i', $file)) { // Domain Included

  }
  elseif (preg_match('/^\//', $file) || !$urlDirectory) { // Absolute Path
    $file = "{$urlDomain}/{$file}";
  }
  else { // Relative Path
    while (preg_match('/^.\//', $file)) {
      $file = preg_replace('/^.\/(.+)/', '$1', $file);
    }
    while (preg_match('/^..\//', $file)) {
      $file = preg_replace('/^..\/(.+)/', '$1', $file);
      $urlDirectory = aviewer_dirPart($urlDirectory);
    }

    $file = "{$urlDomain}/{$urlDirectory}/{$file}";
  }

  return "{$me}?url={$file}";
}

function aviewer_dirPart($file) {
  $fileParts = explode('/', $file);

  foreach ($fileParts AS &$part) {
    if (!$part) unset($part);
  }

  if (count($fileParts) > 0) {
    unset($fileParts[count($fileParts) - 1]);
  }

  return implode('/', $fileParts);
}

function aviewer_filePart($file) {
  $filePieces = explode('/', $file);

  return $filePieces[count($filePieces) - 1];
}

function aviewer_isSpecial($file) {
  if ($file === '.' || $file === '..' || $file === '~') return true; // Yes, the last one isn't normally used; I have my pointless reasons.
  else return false;
}

function aviewer_basicTemplate($data, $title = '') {
  echo "<html>
  <head>
    <title>{$title}</title>
    <style>
    body {
      font-family: Ubuntu, sans;
    }
    h1 {
      margin: 0px;
      padding: 0px;
    }
    </style>
  </head>

  <body>
    {$data}
  </body>
</html>";
}

function aviewer_processHtml($contents) {
  global $metaHack, $selectHack, $noscriptDispose; // Yes, I will make this a class so this is less annoying.
  // A brief rundown so you understand this one:
  // href= and src= are always used with URLs in HTML tags.
  // url= is used in special contexts, including custom tags. As always, this one is apt to break thing, but it is required for, say, meta refresh.
  // as with all of HTML, quotes are optional and text is case insensitive.
//      $contents = str_replace('>',">\n", $contents); // This fixes a small bug I don't want to document. Long story short, it is, as far as I know, impossible to get both <a href=http://google.com/> and <a href="http://google.com"> supported on the same line without this.
//      $contents = preg_replace('/ (href|src|url)=("|\'|)(.+)\\2/ei', '" $1=$2" . aviewer_format("$3", "' . addslashes(aviewer_dirPart($urlFile)) . '") . "$2"', $contents); // Yes, this is stupid. I'll work on making it more universal and accurate.=
//      $contents = preg_replace('/url\((\'|"|)(.+)\\1\)/ei', '"url($1" . aviewer_format("$2", "' . addslashes(aviewer_dirPart($urlFile)) . '") . "$1)"', $contents); // CSS images are handled with this.
  $contents = preg_replace('/\<\!--(.*?)--\>/ism', '', $contents); // Get rid of comments (cleans up the DOM at times, making things faster)

  if ($noscriptDispose) { // Though far less proper, this is much faster.
    $contents = preg_replace('/\<noscript\>(.*?)<\/noscript\>/ism', '', $contents);
  }

  // w00t! The future! Maybe!
  libxml_use_internal_errors(true); // Stop the loadHtml call for spitting out a million errors.
  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->loadHTML($contents);

  // Process LINK tags
  $linkList = $doc->getElementsByTagName('link');
  for ($i = 0; $i < $linkList->length; $i++) {
    if ($linkList->item($i)->hasAttribute('href')) {
      if ($linkList->item($i)->getAttribute('type') == 'text/css' || $linkList->item($i)->getAttribute('rel') == 'stylesheet') {
        $linkList->item($i)->setAttribute('href', aviewer_format($linkList->item($i)->getAttribute('href') . '&type=css'));
      }
      else {
        $linkList->item($i)->setAttribute('href', aviewer_format($linkList->item($i)->getAttribute('href')));
      }
    }
  }

  // Process SCRIPT tags.
  $scriptList = $doc->getElementsByTagName('script');
  $scriptDrop = array();
  for ($i = 0; $i < $scriptList->length; $i++) {
    if ($scriptList->item($i)->hasAttribute('src')) {
      $scriptList->item($i)->setAttribute('src', aviewer_format($scriptList->item($i)->getAttribute('src')) . '&type=js');
    }
    else {
      if ($scriptDispose) {
        $scriptDrop[] = $scriptList->item($i);
      }
      else {
        // TODO: Format Javascript.
      }
    }
  }

  foreach ($scriptDrop AS $drop) {
    $drop->parentNode->removeChild($drop);
  }

  // Process BASE tags.
  $baseList = $doc->getElementsByTagName('base');
  for ($i = 0; $i < $scriptList->length; $i++) {
    // TODO: Change Base (e.g. $urlDirectory)
  }

  // Process IMG, VIDEO, AUDIO, IFRAME tags
  foreach (array('img', 'video', 'audio', 'iframe', 'applet') AS $ele) {
    $imgList = $doc->getElementsByTagName($ele);
    for ($i = 0; $i < $imgList->length; $i++) {
      if ($imgList->item($i)->hasAttribute('src')) {
        $imgList->item($i)->setAttribute('src', aviewer_format($imgList->item($i)->getAttribute('src')) . '&type=other');
      }
    }
  }

  foreach (array('a', 'area') AS $ele) {
    $aList = $doc->getElementsByTagName($ele);
    for ($i = 0; $i < $aList->length; $i++) {
      if ($aList->item($i)->hasAttribute('href')) {
        $aList->item($i)->setAttribute('href', aviewer_format($aList->item($i)->getAttribute('href')));
      }
    }
  }

  if ($selectHack) {
//        $contents = preg_replace('/<option(.*?)value=("|\'|)(.+?)\.(shtml|html|htm|php)\\2(.*?)>/ei', '"<option$1value=$2" . aviewer_format("$3.$4", "' . addslashes(aviewer_dirPart($urlFile)) . '") . "$2$5>"', $contents); // Yes, this is stupid. I'll work on making it more universal and accurate.=
    // Process Option Links
    $optionList = $doc->getElementsByTagName('option');
    for ($i = 0; $i < $optionList->length; $i++) {
      if ($optionList->item($i)->hasAttribute('value')) {
        $optionValue = $optionList->item($i)->getAttribute('value');
        if (preg_match('/\.(htm|html|php|shtml|\/)$/', $optionValue)) { // TODO Optimise
          $optionList->item($i)->setAttribute('value', aviewer_format($optionValue));
        }
      }
    }
  }

  if ($metaHack) { // This is the meta-refresh hack, which tries to fix meta-refresh headers that may in some cases automatically redirect a page, similar to <a href>. This is hard to work with, and in general sites wishing to achieve this will often implement it instead using headers (which, due to the nature of an archive, will not be transmitted and thus we don't have to worry about modifying them) or using JavaScript (which is never easy to implement, though we will take a shot at it later on [TODO]). An example: <meta http-equiv="Refresh" content="5; URL=http://www.google.com/index">
    $metaList = $doc->getElementsByTagName('meta');
    $metaDrop = array();

    for ($i = 0; $i < $metaList->length; $i++) {
      $metaDisposeSkip = false;

      if ($metaList->item($i)->hasAttribute('http-equiv') && $metaList->item($i)->hasAttribute('content')) {
        if (strtolower($metaList->item($i)->getAttribute('http-equiv')) == 'refresh') {
          $metaList->item($i)->setAttribute('content', preg_replace('/^(.*)url=([^ ]+)(.*)$/ies', '"$1" . aviewer_format("$2") . "$3"', $metaList->item($i)->getAttribute('content')));

          $metaDisposeSkip = true;
        }
      }

      if ($metaDispose && !$metaDisposeSkip) {
        $metaDrop[] = $metaList->item($i);
      }
    }

    foreach($metaDrop AS $drop) {
      $drop->parentNode->removeChild($drop);
    }
  }

  return $doc->saveHTML();
}
?>