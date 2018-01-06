<?php
namespace MirrorReader;

class Factory {
    /**
     * @var Processor[]
     */
    public static $processorCollection = [];

    public static function registerShutdownFunction() {
        register_shutdown_function(function() {
            foreach (self::$processorCollection AS $file => $processor) {
                if ($processor->contents)
                    apcu_store("mirrorreader_{$file}", $processor);
            }
        });
    }

    public static function get($file) : Processor {
        if (apcu_exists("mirrorreader_{$file}")) {
            return apcu_fetch("mirrorreader_{$file}");
        }

        $processor = new Processor($file);
        self::$processorCollection[$file] = $processor;
        return $processor;
    }
}