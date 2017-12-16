<?php
$store = '/Library/'; // Configuration variable for where the domains are stored offline.



$domainConfiguration = array(
    'default' => array(
        'homeFiles' => [
            'index.html',
            'index.shtml',
            'index.php',
            'Main_Page',
        ],

        'passthru' => false, // If enabled, when the script encounters a non-stored file, it will instead include the live one from the web -- say, PayPal and Twitter links.

        'scriptHacks' => [ // Which script hacks should be used. Script processing is tricky, as there are a million ways things can be done (indeed, URLs could even be encrypted or specially encoded, making it impossible to work with them). Instead, we have four common hacks: 'suspectFileString' and 'suspectFileAnywhere' are mutually exclusive, with the former usually working but not breaking anything, and the latter more likely to both work and break something. Additionally, there are 'suspectDirString' which is more likely to break something, but can work with directories that are placed in strings (it is almost guarenteed to break something if implemented using the anywhere method, due to regex, etc.), and 'suspectDomainAnywhere' which usually won't break anything, but should still be used with caution.
            //'removeAll', // Removes all scripts, and activates <noscript> nodes. An easy way to disable Javascript on a per-site basis.
            'suspectFileString', // Searches for file patterns inside of JS strings. Fairly effective, and unlikely to break things.
            //'suspectDomainAnywhere', // Searches for file patterns anywhere in the JS body. As you'll almost never find a full domain just cooincidentally exist in the JS body, this shouldn't break things, and will still be fairly effective.
        ],

        'htmlHacks' => [
            'backgroundHack', // Checks <body background>, <table background>, <tr background>, etc. No reason not to enable, but because the behaviour is not compliant with HTML, we have it available as a flag.
            'selectHack', // Checks <option values> for URL-looking patterns, and rewrites as appropriate. This can, in very rare situations, break things. No noticable performance penalty.
            'dirtyAttributes' // Processes style and javascript attributes. Currently quite slow, but can probably be sped up, and in any case is unlikely to break anything.
        ],

        'customSrcAttributes' => ['data-src'], // This is a list of custom attributes containing URLs that should be parsed. data-src is used be Wikia.

        'ignoreGETs' => ['PHPSESSID', 'sid', 'highlight', 's'],

        // Heritrix MirrorReader has a lot of trouble with 301s. This is a hack that will fix some instances of this, either "none" (which does nothing) or "dir" which will redirect any file to a directory with the same name and a "1" appended.
        // Note that the fixer script will generally remove most 301s, so after running it on a site you can set this parameter to none for a modest speed boost.
        '301mode' => 'dir',

        'recognisedExtensions' => ['asp', 'css', 'doc', 'docx', 'gif', 'htm', 'html', 'jpeg', 'jpg', 'js', 'pdf', 'php', 'png', 'rss', 'txt', 'xml'], // List of recognised extensions.

        'cacheStore' => '/var/www/cache/',

        /* Additional Configuration Directives
         * 'redirect' -- An array in the form of "find => replace" that redirects domains, directories, and files.
         * 'mirror' -- An array in the form of "mirror" that means all domain lookups under this address are identical.
         */
    ),

    'www.youtube.com' => array(
         'passthru' => true,
    ),
    
    'youtube.com' => array(
         'passthru' => true,
    ),

    'arstechnica.com' => array(
        'redirect' => array(
            'http://arstechnica.com/archive/' => 'http://archive.arstechnica.org/archive/',
            '/journals/microsoft.ars/' => '/information-technology/',
            '/microsoft/' => '/information-technology/',
            '/journals/thumbs.ars/' => '/gaming/',
            '/news/' => '/',
            '/1/' => '/',
            '/articles/' => '/features/',
        ),

        'redirectRegex' => array(
            'http://arstechnica.com(.*)\.(jpg|png)$' => 'http://cdn.arstechnica.net$1.$2',
            '/(\d{4})/(\d{2})/\d{2}/' => '/$1/$2/',
            '\.(html|ars)$' => '/',
        )
    ),

    /* seem to be the occasion stray unknown glpyh character, but I can't tell what from -- we seem to be using the same encoding as the server */
    'shrines.rpgclassics.com' => array(
        'redirect' => array(
            'shrines.rpgclassics.com/shrines/' => 'shrines.rpgclassics.com/',
        )
    ),

    'www.rpgclassics.com' => array(
        'redirect' => array(
            'www.rpgclassics.com/shrines/' => 'shrines.rpgclassics.com/',
            'www.rpgclassics.com/staff/' => 'staff.rpgclassics.com/'
        )
    ),

    /* working 100% */
    'www.zeldawiki.org' => array(
        'redirect' => array(
            'www.zeldawiki.org' => 'zeldawiki.org',
        )
    ),

    'jaytheham.com' => array(
        'redirect' => array(
            'jaytheham.com' => 'www.jaytheham.com',
        )
    ),

    'niwanetwork.org' => array(
        'redirect' => array(
            'niwanetwork.org' => 'www.niwanetwork.org',
        )
    ),

    'lostlevels.org' => array(
        'redirect' => array(
            'lostlevels.org' => 'www.lostlevels.org',
        )
    ),

    /* need to fix some glitchdex URLs */
    'www.glitchcity.info' => array(
        'redirect' => array(
            'www.glitchcity.info' => 'glitchcity.info',
        )
    ),

    'ajax.googleapis.com' => array(
        'passthru' => true,
    ),

    /* still need fixes */
    'www.psypokes.com' => array(
        'scriptHacks' => [ // Which script hacks should be used. Script processing is tricky, as there are a million ways things can be done (indeed, URLs could even be encrypted or specially encoded, making it impossible to work with them). Instead, we have four common hacks: 'suspectFileString' and 'suspectFileAnywhere' are mutually exclusive, with the former usually working but not breaking anything, and the latter more likely to both work and break something. Additionally, there are 'suspectDirString' which is more likely to break something, but can work with directories that are placed in strings (it is almost guarenteed to break something if implemented using the anywhere method, due to regex, etc.), and 'suspectDomainAnywhere' which usually won't break anything, but should still be used with caution.
            //'suspectDomainAnywhere'
        ],
    ),

    /* working 100% */
    'www.serebii.net' => [],
);
?>
