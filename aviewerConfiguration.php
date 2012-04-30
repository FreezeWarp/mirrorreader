<?php
$store = '/home/joseph/WebArchives/'; // Configuration variable for where the domains are stored offline.
$cacheStore = '/var/www/cache/'; // Configuration variable for where we'll store uncompressed zip files and directory symlinks.
$homeFile = 'index.html';

/* TODO: Domain-Specific Configuration */

$domainConfiguration = array(
  'default' => array(
    'passthru' => true, // If enabled, when the script encounters a non-stored file, it will instead include the live one from the web -- say, PayPal and Twitter links.

    'selectHack' => true, // If enabled, OPTION tags that contain URLs in their "value" attr will work.
    'metaHack' => true, // If enabled, Meta REFRESH will work.
    'badEntitiesHack' => false, // If enabled, will attempt to (experimentally) replace problematic characters ("<" and ">") in Javascript strings with appropriate entities. For most sites, this won't pose a problem, and indeed this will break some Javascript. Only try this if Javascript does not seem to work properly.

    'scriptDispose' => false, // If enabled, SCRIPTS that are not external will be dropped. This is useful for getting rid of advertisement and tracking code, but should be used with care.
    'scriptEccentric' => false, // If enabled, the SCRIPT processing will be far more liberal, replacing anything that looks like a file, even if its not within a string. This allows some sites to work that wouldn't otherwise, but usually it should be off.
    'removeExtra' => false, // This will remove extra comments. In some sites, it will break things, but for the rest it will increase the execution speed of the program.
  ),

  'www.upokecenter.com' => array(
    'passthru' => true,

    'selectHack' => true,
    'metaHack' => true,
    'badEntitiesHack' => true,

    'scriptDispose' => false,
    'scriptEccentric' => true,
    'removeExtra' => false,
  ),
);
?>