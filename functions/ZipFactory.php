<?php
namespace MirrorReader;

class ZipFactory {
    /**
     * @var \ZipArchive[]
     */
    public static $processorCollection = [];

    public static function registerShutdownFunction() {
    }

    public static function get($file) : \ZipArchive {
        if (isset(self::$processorCollection[$file])) {
            return self::$processorCollection[$file];
        }

        $processor = new \ZipArchive();
        $processor->open($file);

        self::$processorCollection[$file] = $processor;
        return $processor;
    }
}