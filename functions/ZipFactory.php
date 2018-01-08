<?php
namespace MirrorReader;

class ZipFactory {
    /**
     * @var \ZipArchive[]
     */
    public static $processorCollection = [];

    public static function registerShutdownFunction() {
        register_shutdown_function(function() {
            foreach (self::$processorCollection AS $file => $processor) {
                apcu_store("mirrorreader_zip_{$file}", $processor);
            }
        });
    }

    public static function get($file) : \ZipArchive {
        if (isset(self::$processorCollection[$file])) {
            return self::$processorCollection[$file];
        }
        if (apcu_exists("mirrorreader_zip_{$file}")) {
            return apcu_fetch("mirrorreader_zip_{$file}");
        }

        $processor = new \ZipArchive();
        $processor->open($file);

        self::$processorCollection[$file] = $processor;
        return $processor;
    }
}