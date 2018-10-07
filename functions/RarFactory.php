<?php
namespace MirrorReader;

class RarFactory {
    /**
     * @var \RarArchive[]
     */
    public static $processorCollection = [];

    public static function get($file) : \RarArchive {
        if (empty(self::$processorCollection[$file])) {
            self::$processorCollection[$file] = \RarArchive::open($file);
        }

        return self::$processorCollection[$file];
    }
}