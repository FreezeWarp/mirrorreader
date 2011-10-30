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

  $urlDirectoryLocal = $urlDirectory;

  if (preg_match('/^(http|https|ftp|mailto)\:/i', $file)) { // Domain Included

  }
  elseif (preg_match('/^\//', $file) || !$urlDirectory) { // Absolute Path
    $file = "{$urlDomain}/{$file}";
  }
  else { // Relative Path
    while (preg_match('/^\.\//', $file)) {
      $file = preg_replace('/^\.\/(.*)/', '$1', $file);
    }
    while (preg_match('/^\.\.\//', $file)) {
      $file = preg_replace('/^\.\.\/(.*)/', '$1', $file);
      $urlDirectoryLocal = aviewer_dirPart($urlDirectoryLocal);
    }

    $file = "{$urlDomain}/{$urlDirectoryLocal}/{$file}";
  }

  return "{$me}?url={$file}";
}

function aviewer_dirPart($file) { // Obtain the parent directory of a file or directory by analysing its string value. This will not operate on the directory or file itself.
  $fileParts = explode('/', $file);

  foreach ($fileParts AS $id => $part) { // Remove all empty elements.
    if (!$part) {
      unset($fileParts[$id]);
    }
  }

  array_pop($fileParts); // Note: Because of the previous foreach loop, the array index may be corrupted (e.g. the array will be {0 = ele, 2 = ele}), thus making array_pop the only possible means of removing the last element of the array (as opposed to the count method that may be faster).

  return implode('/', $fileParts);
}

function aviewer_filePart($file) { // Obtain the file or directory without its parent directory by analysing its string value. This will not operate on the directory or file itself.
  $fileParts = explode('/', $file);

  foreach ($fileParts AS $id => $part) { // Remove all empty elements.
    if (!$part) {
      unset($fileParts[$id]);
    }
  }

  return array_pop($fileParts); // Note: Because of the previous foreach loop, the array index may be corrupted (e.g. the array will be {0 = ele, 2 = ele}), thus making array_pop the only possible means of removing the last element of the array (as opposed to the count method that may be faster).
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
  global $metaHack, $selectHack, $scriptDispose, $noscriptDispose, $metaDispose; // Yes, I will make this a class so this is less annoying.

  $contents = preg_replace('/\<\?xml(.+)\?\>/', '', $contents);
  $contents = preg_replace('/\<\!--(.*?)--\>/ism', '', $contents); // Get rid of comments (cleans up the DOM at times, making things faster)

  if ($noscriptDispose) { // Though far less proper, this is much faster.
    $contents = preg_replace('/\<noscript\>(.*?)<\/noscript\>/ism', '', $contents);
  }


  libxml_use_internal_errors(true); // Stop the loadHtml call for spitting out a million errors.
  $doc = new DOMDocument(); // Initiate the PHP DomDocument.
  $doc->preserveWhiteSpace = false; // Don't worry about annoying whitespace.
  $doc->loadHTML($contents); // Load the HTML.

/*  if (true) {
    for ($i = 0; $i < $doc->document->length; $i++) {
      if ($doc->document->item($i)->hasAttribute('onclick')) {
        $doc->document->item($i)->setAttribute('onclick', aviewer_processJavascript($doc->document->item($i)->getAttribute('onclick')));
      }
    }
  }*/

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
        $scriptList->item($i)->nodeValue = aviewer_processJavascript($scriptList->item($i)->nodeValue);
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

  // Process A, AREA (image map) tags
  foreach (array('a', 'area') AS $ele) {
    $aList = $doc->getElementsByTagName($ele);
    for ($i = 0; $i < $aList->length; $i++) {
      if ($aList->item($i)->hasAttribute('href')) {
        $aList->item($i)->setAttribute('href', aviewer_format($aList->item($i)->getAttribute('href')));
      }
    }
  }

  // Process BODY, TABLE, TD, and TH tags w/ backgrounds. TABLE, TD & TH do support the background tag, but it was an extension of both Netscape and IE way back, and today most browsers still recognise it and will add a background image as appropriate, so... we have to support it.
  foreach (array('body', 'table', 'td', 'th') AS $ele) {
    $aList = $doc->getElementsByTagName($ele);
    for ($i = 0; $i < $aList->length; $i++) {
      if ($aList->item($i)->hasAttribute('background')) {
        $aList->item($i)->setAttribute('background', aviewer_format($aList->item($i)->getAttribute('background')));
      }
    }
  }

  // Process Option Links; some sites will store links in OPTION tags and then sue Javascript to link to them. Thus, if the hack is enabled, we will try to cope.
  if ($selectHack) {
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

  // This is the meta-refresh hack, which tries to fix meta-refresh headers that may in some cases automatically redirect a page, similar to <a href>. This is hard to work with, and in general sites wishing to achieve this will often implement it instead using headers (which, due to the nature of an archive, will not be transmitted and thus we don't have to worry about modifying them) or using JavaScript (which is never easy to implement, though in some cases it still works). An example: <meta http-equiv="Refresh" content="5; URL=http://www.google.com/index">
  if ($metaHack) {
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

function aviewer_processJavascript($contents) {
  global $scriptEccentric;

  if ($scriptEccentric) { // Convert anything that appears to be a suspect file. Because of the nature of this, there is a high chance stuff will break if $scriptEccentric is enabled. But, it allows some sites to work properly that otherwise wouldn't.
    $contents = preg_replace('/(([a-zA-Z0-9\_\-\/]+)\.(php|htm|html|css|js))/ie', 'aviewer_format("$1")', $contents);
  }
  else { // Convert strings that contain files ending with suspect extensions.
    $contents = preg_replace('/("|\')(([a-zA-Z0-9\_\-\/]+)\.(php|htm|html|css|js))\1/ie', 'stripslashes("$1") . aviewer_format("$2") . stripslashes("$1")', $contents);
  }

  return $contents;
}

function aviewer_processCSS($contents) {
  $contents = str_replace(';',";\n", $contents); // Fixes an annoying REGEX quirk below, I won't go into it.
  $contents = preg_replace('/url\((\'|"|)(.+)\\1\)/ei', '\'url($1\' . aviewer_format("$2") . \'$1)\'', $contents); // CSS images are handled with this.

  return $contents;
}
?>