# mirrorreader
This is a reader for [Heritrix](https://github.com/internetarchive/heritrix3) (archive.org) site archives created with the MirrorWriter module.

It can read archives stored in .zip, .rar, .tar.bz2, and .tar.gz archives, though .rar is the only recommended format in most situations.

## Utilities
MirrorReader generally works with most MirrorWriter-written archives out-of-the-box, but includes two built in utilities that can be used to clean up such archives:
1. **fileNameFixer.php** can be used to scan MirrorWritten directories and combine files and directories that should have the same name. (For instance, if MirrorWriter encounters a file "a" and then a file "a/b", it will write the file "a" normally, but then create a directory "a1/" to store the "b" file. fileNameFixer.php will move "a" into "a1/" as "index.html" and then rename "a1/" to "a/".)
2. **spider.php**, which does basically the same thing Heritrix does (scanning and downloading copies of websites), but operates on existing archives instead. For instance, if Heritrix missed a file, spider.php will typically download the file itself. It does this by scanning the HTML of all files in an archive and looking for broken links. (The most common case was, [until recently](https://github.com/internetarchive/heritrix3/issues/177), Heritrix missing files located in the [srcset HTML attribute](https://www.w3schools.com/tags/att_source_srcset.asp).)

## How it Parses Files
MirrorReader parses HTML, Javascript, and CSS to try and transform a website's original URL to one passed to the MirrorReader program. It uses several techniques for doing so:

  1. **HTML** - Almost all HTML is correctly processed, including malformed HTML. The following HTML elements are processed:
    * `<link>`'s href attribute (if `<link>`'s type attribute is "text/css" and rel attribute is "stylesheet")
    * `<script>`'s src attribute
    * `<img>`, `<video>`, `<audio>`, `<source>`, `<frame>`, `<iframe>`, `<applet>`'s src attributes
    * `<img>`'s srcset attribute
    * `<a>`, `<area>`'s href attribute
    * `<body>`, `<table>`, `<td>`, and `<th>`'s background attribute
    * `<meta http-equiv>` refresh
    * `<option>` value attribute, on a per-site basis
    * Inline `<script>` tags, `<a href="javascript:...">` tags, and all element's event attributes (onclick, onmouseover, etc.) are processed for Javascript, though the event attributes are disabled by default for performance reasons.
    * Inline `<style>` tags are processed for CSS.
  2. **Javascript** - Because it is hard to consistently extract URLs from Javascript, MirrorReader supporters several modes for Javascript extraction, which can be enabled on a per-site basis. By default, it will transform any Javascript string that appears to contain a valid file. It can also search for what appear to be valid directories in Javascript strings, and what appear to be valid files anywhere in the Javascript body, though the latter two are more prone to errors. Finally, it can be configured to remove all Javascript from a site and activate all of a site's [noscript](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/noscript) nodes.
  3. **CSS** - CSS processing is simple: search for an process [url()](https://developer.mozilla.org/en-US/docs/Web/CSS/url) tags. MirrorReader is smart, and will not process [data: URIs](https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs).
