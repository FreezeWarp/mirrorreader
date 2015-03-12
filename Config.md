# Introduction #

Below is a list of configuration directives you can use with each site. Some are hackish, others just allow for some extra customisation.


# Details #

  * passthru - If enabled, when the script encounters a non-stored file, it will instead include the live one from the web -- say, PayPal and Twitter links.

  * selectHack -  enabled, OPTION tags that contain URLs in their "value" attr will work.
  * metaHack - If enabled, Meta REFRESH will work.
  * badEntitiesHack - If enabled, will attempt to (experimentally) replace problematic characters ("<" and ">") in Javascript strings with appropriate entities. For most sites, this won't pose a problem, and indeed this will break some Javascript. Only try this if Javascript does not seem to work properly.
  * scriptDispose - If enabled, SCRIPTS that are not external will be dropped. This is useful for getting rid of advertisement and tracking code, but should be used with care.
  * scriptHacks - Which script hacks should be used. Script processing is tricky, as there are a million ways things can be done (indeed, URLs could even be encrypted or specially encoded, making it impossible to work with them). Instead, we have four common hacks:
    * suspectFileString, suspectFileAnywhere -  'suspectFileString' and 'suspectFileAnywhere' are mutually exclusive, with the former usually working but not breaking anything, and the latter more likely to both work and break something.
    * suspectDirString - Likely to break something, but can work with directories that are placed in strings (it is almost guaranteed to break something if implemented using the anywhere method, due to regex, etc.)
    * suspectDomainAnywhere - Usually won't break anything, but should still be used with caution.
  * removeExtra - This will remove extra comments. In some sites, it will break things, but for the rest it will increase the execution speed of the program. (There are a few instances of it fixing things, as well. You should try to toggle it if nothing is displaying.)
  * 301mode - Heritrix MirrorReader has a lot of trouble with 301s. This is a hack that will fix some instances of this, either:
    * none - Does, well, nothing.
    * dir - Will redirect any file to a directory with the same name and a "1" appended.
  * getHack - GET variables will be modified according to the default MirrorWriter pattern if enabled. This is off by default as it causes some slow down and may not be implemented correctly. (It may later be removed from config and turned on by default.)
  * recognisedExtensions - An array of recognised extensions, used for regex, etc.

  * homeFile - The "index" file, usually set as a part of MirrorReader's settings.
  * cacheStore - Where to store the file in cache.

  * redirect -- An array in the form of "find => replace" that redirects domains, directories, and files.
  * mirror -- [UNIMPLEMENTED](UNIMPLEMENTED.md) An array in the form of "mirror" that means all domain lookups under this address are identical.