# Introduction #

There are far simpler ways one can go about doing what this script does -- displaying a website archive. However, there are certain limitations to these other methods. The best example is with Javascript. Normally, sites that store URLs in Javascript are going to have trouble displaying in an archive reader. Here, however, a number of hacks are used that detect and fix these issues. Technically, one could also simulate this entire process by simply changing the document base. However, even when I tried just doing this, I found that a number of problems are apt to crop up. At the very least, sites that include their domain in their links aren't going to display using this method. Ultimately, there is no truly simple way of fixing this . What I did is the hard, and perhaps uneccessary, way. It also allows for customisations that simply can't be done in any simpler way, like rewriting file names and providing (eventually) UserStyles and UserScripts-like hacks. Is there are a simpler way? Yes. Is there a more customisable and fool-proof way? There simply isn't.

Still, a part of the goal of this is to have a small file size, and not be some bloated mess. Ideally, this should never exceed 1,000 lines, sans configuration. As a result, it will be limited in some more advanced features.

# The Current Process #
  1. Load the file.
  1. Determine its file type...
    1. Specified in GET?
    1. Familiar extension?
    1. Autodetect by content? (starts with DOCTYPE or `<html>`)
  1. If HTML:
    1. Load the HTML document in PHP's DOMDOCUMENT.
    1. Find various tags of interest and change their attributes as necessary -- link, a, area, img, video, audio, etc.
    1. If a link is found in certain elements, add a GET attribute to force the appropriate type. For instance, if a CSS file is downloaded using the PHP extension, browsers will often fail to load this if transmitted using an HTML Content-type.
    1. apply appropriate CSS and JS actions to `<style>` and `<script>` blocks.
  1. If CSS:
    1. Convert URLs declared using the url() syntax to the appropriate format.
  1. If JS:
    1. Replace links found in strings, or anywhere in the file if $scriptEccentric is true.