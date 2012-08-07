<?php
$store = '/home/joseph/WebArchives/'; // Configuration variable for where the domains are stored offline.



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
     'recognisedExtensions' => ['asp', 'css', 'doc', 'docx', 'gif', 'htm', 'html', 'jpeg', 'jpg', 'js', 'pdf', 'php', 'php4', 'php5', 'png', 'rss', 'txt', 'xml'], // List of recognised extensions.
     'homeFile' => 'index.html', // The "index" file, usually set as a part of MirrorReader's settings.
      
     'cacheStore' => '/var/www/cache/',
      
     /* Additional Configuration Directives
      * 'redirect' -- An array in the form of "find => replace" that redirects domains, directories, and files.
      * 'mirror' -- An array in the form of "mirror" that means all domain lookups under this address are identical.
      */
  ),
    
  'browsers.garykeith.com' => array( // Working [note: requires passthru=true if using default robots.txt]
    'removeExtra' => true,
  ),
  
  'iimarck.us' => array( // Working [note1: uses BASE] [note2: default run fails with duplicate files in i/ directory]
    'removeExtra' => true,
  ),
  
  'www.lostlevels.org' => array( // Working
    'mirror' => ['lostlevels.org']
  ),
    
  'www.jaytheham.com' => array(
    'dirtyAttributes' => true,
  ),
    
  'mmxz.zophar.net' => array(
    'scriptEccentric' => true,
  ),
  
  'www.blue-reflections.net' => array(
    'dirtyAttributes' => true,
    'scriptEccentric' => true,
  ),  
  
  'mother3.fobby.net' => array( // Working
    'passthru' => true, // YT, Sourceforge, etc.
    'removeExtra' => true,
  ),
    
  'shrines.rpgclassics.com' => array(
    'passthru' => false, // YT, Sourceforge, etc.
    'removeExtra' => true,
    'redirect' => [
       'shrines.rpgclassics.com/images/' => 'themes.rpgclassics.com/images/',
       'shrines.rpgclassics.com/space.gif' => 'http://themes.rpgclassics.com/images/space.gif',
    ]
  ),
    
  'themes.rpgclassics.com' => array( // Image repo
    'passthru' => false, // No external links present in scan.
    'removeExtra' => true,
  ),
    
  'www.serebii.net' => array( // Working
    'passthru' => false, // Disabled for testing.
    'selectHack' => true, // Pokedex, etc
    'metaHack' => true, // Splash screen
    'removeExtra' => false, // Disabled, as it breaks some older pages.
    'htmlReplacePre' => [
      "\n" . '<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">' // Older pages contain this, which breaks the script.
    ]
  ),
    
  'www.spriters-resource.com' => array( // Working [requires xml in recognisedExtensions]
    'removeExtra' => true,
  ),
  
  'www.psypokes.com' => array( // Working
    'passthru' => true, // Psypokes Forums
    'selectHack' => true, // Pokedex [CONFIRMATION NEEDED]
    'removeExtra' => true,
    'jsReplacePost' => [
      'document.oncontextmenu=new Function("return false");' => '', // Re-enables right click
    ],
  ),

  'www.upokecenter.com' => array( // Working, Mostly
    'selectHack' => true,
    'badEntitiesHack' => false, // MUST be set off for a few of the JavaScripts used.
    'scriptDispose' => false, // MUST be set off for a few of the JavaScripts used.
    'scriptEccentric' => true, // Required for CSS stylings to work. (note: if turned off, the issue will remain when turned back on unless cookies are cleared)
    'removeExtra' => true,
  ),
);
?>