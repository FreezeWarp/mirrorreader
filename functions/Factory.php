<?php
namespace MirrorReader;

class Factory {
    public static function get($file) : Processor {
        if (apcu_exists("ar_$file")) {
            return apcu_fetch("ar_$file");
        }

        else {
            return new Processor($file);
        }
    }
}