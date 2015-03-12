A small set of PHP scripts that I created to accompany Heritrix's Mirror Writer. I found that ARC and WARC files were too much trouble for personal tasks, and I couldn't find any decent way of reading the updated paths (aside from creating Apache hosts, which is annoying). So, I decided to throw together a PHP script to do this for me; its still an early alpha, but you may find it useful.

To run it:
  1. Get the source: `hg clone https://JosephTParsons@code.google.com/p/mirrorreader/`
  1. Place the files on a LAMP server (local or web-accessible).
  1. Set `$store` to the directory containing mirrors made by MirrorWriter (it may, for instance, include the directories "www.google.com" and "www.yahoo.com").
  1. Set `$cacheStore` to a directory the LAMP server can write to. It will be used more in the future.

Remember, this only works with uncompressed directories written by Heritrix's MirrorWriter, which must all be moved to the same location. It will work with ZIPed files in the future, but ARC support is not planned.

Todo:
  1. Lazy Subdomains