# mirrorreader
This is a reader for [Heritrix](https://github.com/internetarchive/heritrix3) (archive.org) site archives created with the MirrorWriter module.

It generally works with most MirrorWriter-written archives out-of-the-box, but includes two built in utilities that can be used to clean up such archives:
1. *fileNameFixer.php* can be used to scan MirrorWritten directories and combine files and directories that should have the same name. (For instance, if MirrorWriter encounters a file "a" and then a file "a/b", it will write the file "a" normally, but then create a directory "a1/" to store the "b" file. fileNameFixer.php will move "a" into "a1/" as "index.html" and then rename "a1/" to "a/".)
2. *spider.php*, which does basically the same thing Heritrix does (scanning and downloading copies of websites), but operates on existing archives instead. For instance, if Heritrix missed a file, spider.php will typically download the file itself. It does this by scanning the HTML of all files in an archive and looking for broken links. (The most common case was, [until recently](https://github.com/internetarchive/heritrix3/issues/177), Heritrix missing files located in the [srcset HTML attribute](https://www.w3schools.com/tags/att_source_srcset.asp).)

It can read archives stored in .zip, .rar, .tar.bz2, and .tar.gz archives, though .rar is the only recommended format in most situations.
