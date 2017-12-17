<?php

namespace MirrorReader;

class MkdirIndex
{

    public static function execute($dirName, $successFile = false) {

        if (!mkdir($dirName, 0777, true)) {
            $baseFile = $dirName;

            while (!is_file($baseFile) && $baseFile !== '') {
                $baseFile = rtrim(dirname($baseFile), '/');
            }

            if ($baseFile !== '') {
                rename($baseFile, $baseFile . '~temp') or die("Could not rename $baseFile to $baseFile~temp");
                mkdir($dirName, 0777, true) or die('Mkdir failed. Temp file leftover: ' . $baseFile . '~temp');
                rename($baseFile . '~temp', $baseFile . '/index.html') or die("Could not rename $baseFile~temp to $baseFile/index.html");

                if ($successFile)
                    fwrite($successFile, "$dirName: created $dirName directory, moving in $baseFile as $baseFile/index.html\n");

                return true;
            }
            else return false;
        }

        return true;

    }

}