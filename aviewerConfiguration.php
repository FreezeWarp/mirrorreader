<?php
$store = '/home/joseph/WebArchives/'; // Configuration variable for where the domains are stored offline.
$cacheStore = '/var/www/cache/'; // Configuration variable for where we'll store uncompressed zip files and directory symlinks.
$homeFile = 'index.html';

/* TODO: Domain-Specific Configuration */

$domainConfiguration = array(
  'default' => array(
    'passthru' => false, // If enabled, when the script encounters a non-stored file, it will instead include the live one from the web -- say, PayPal and Twitter links.

    'selectHack' => false, // If enabled, OPTION tags that contain URLs in their "value" attr will work.
    'metaHack' => false, // If enabled, Meta REFRESH will work.
    'badEntitiesHack' => false, // If enabled, will attempt to (experimentally) replace problematic characters ("<" and ">") in Javascript strings with appropriate entities. For most sites, this won't pose a problem, and indeed this will break some Javascript. Only try this if Javascript does not seem to work properly.

    'scriptDispose' => false, // If enabled, SCRIPTS that are not external will be dropped. This is useful for getting rid of advertisement and tracking code, but should be used with care.
    'scriptEccentric' => false, // If enabled, the SCRIPT processing will be far more liberal, replacing anything that looks like a file, even if its not within a string. This allows some sites to work that wouldn't otherwise, but usually it should be off.
    'removeExtra' => false, // This will remove extra comments. In some sites, it will break things, but for the rest it will increase the execution speed of the program.
      
     'getHack' => true, // GET variables will be modified according to the default MirrorWriter pattern if enabled. This is off by default as it causes some slow down and may not be implemented correctly. (It may later be removed from config and turned on by default.)
     'recognisedExtensions' => ['asp', 'css', 'doc', 'docx', 'htm', 'html', 'js', 'pdf', 'php', 'php4', 'php5', 'rss', 'txt', 'xml']
  ),

  'www.upokecenter.com' => array( // Working, Mostly
    'passthru' => false, // ??

    'selectHack' => true,
    'metaHack' => true,
    'badEntitiesHack' => true,

    'scriptDispose' => true,
    'scriptEccentric' => true,
    'removeExtra' => false,
  ),
  
  'www.psypokes.com' => array( // Working
    'passthru' => true, // Psypokes Forums

    'selectHack' => true,
    'metaHack' => false,
    'badEntitiesHack' => false,

    'scriptDispose' => false,
    'scriptEccentric' => true,
    'removeExtra' => true,

    'jsReplace' => [
      'document.oncontextmenu=new Function("return false");' => '',
    ],
  ),
    
  'browsers.garykeith.com' => array( // Working (pulls some external)
    'passthru' => true, // ??

    'selectHack' => false,
    'metaHack' => false,
    'badEntitiesHack' => false,

    'scriptDispose' => false,
    'scriptEccentric' => false,
    'removeExtra' => true,
  ),
  
  'mother3.fobby.net' => array( // Working
    'passthru' => true, // YT, Sourceforge, etc.

    'selectHack' => false,
    'metaHack' => false,
    'badEntitiesHack' => false,

    'scriptDispose' => false,
    'scriptEccentric' => false,
    'removeExtra' => true,
  ),
    
  'shrines.rpgclassics.com' => array(
    'passthru' => false, // YT, Sourceforge, etc.

    'selectHack' => false,
    'metaHack' => false,
    'badEntitiesHack' => false,

    'scriptDispose' => false,
    'scriptEccentric' => false,
    'removeExtra' => true,
    
    'redirect' => [
       'shrines.rpgclassics.com/images/' => 'themes.rpgclassics.com/images/',
       'shrines.rpgclassics.com/space.gif' => 'http://themes.rpgclassics.com/images/space.gif',
    ]
  ),
    
  'themes.rpgclassics.com' => array( // Image repo
    'passthru' => false, // No external links present in scan.

    'selectHack' => false,
    'metaHack' => false,
    'badEntitiesHack' => false,

    'scriptDispose' => false,
    'scriptEccentric' => false,
    'removeExtra' => true,
  ),
    
  'www.spriters-resource.com' => array( // Working
    'passthru' => false, // Disabled for testing.

    'selectHack' => false,
    'metaHack' => false,
    'badEntitiesHack' => false,

    'scriptDispose' => false,
    'scriptEccentric' => false,
    'removeExtra' => true,
  ),
    
  'www.serebii.net' => array( // Working
    'passthru' => false, // Disabled for testing.

    'selectHack' => true,
    'metaHack' => true,
    'badEntitiesHack' => false,

    'scriptDispose' => false,
    'scriptEccentric' => false,
    'removeExtra' => true,
  ),
  
  'iimarck.us' => array( // Working (uses BASE)
    'passthru' => true,

    'selectHack' => false,
    'metaHack' => false,
    'badEntitiesHack' => false,

    'scriptDispose' => false,
    'scriptEccentric' => false,
    'removeExtra' => true,
  ),
);
?>