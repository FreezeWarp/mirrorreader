<?php
namespace MirrorReader;

class RarFactory {
    /**
     * @var \RarArchive[]
     */
    public static $processorCollection = [];

    public static function registerShutdownFunction() {
        register_shutdown_function(function() {
            foreach (self::$processorCollection AS $file => $processor) {
                apcu_store("mirrorreader_zip_{$file}", $processor);
            }
        });
    }

    public static function get($file) : \RarArchive {
        if (isset(self::$processorCollection[$file])) {
            return self::$processorCollection[$file];
        }
        if (apcu_exists("mirrorreader_rar_{$file}")) {
            return apcu_fetch("mirrorreader_rar_{$file}");
        }

        $processor = \RarArchive::open($file);

        self::$processorCollection[$file] = $processor;
        return $processor;
    }
}