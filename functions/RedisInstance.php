<?php
/**
 * Created by PhpStorm.
 * User: Joseph
 * Date: 10/4/2018
 * Time: 5:46 PM
 */

namespace MirrorReader;


class RedisInstance
{
    public static $host = '127.0.0.1';
    private static $instance;

    public static function get() : \Redis {
        if (empty(self::$instance)) {
            self::$instance = new \Redis();
            self::$instance->connect(self::$host);
        }

        return self::$instance;
    }
}