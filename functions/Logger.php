<?php
namespace MirrorReader;

class Logger {

	private static $instances = [];

	public static function getLogger($name) : \Monolog\Logger {
		if (empty(self::$instance[$name])) {
			self::$instances[$name] = new \Monolog\Logger("MRSpider-{$name}");
			self::$instances[$name]->pushHandler(new \Monolog\Handler\StreamHandler("Spider-{$name}", \Monolog\Logger::INFO));
            self::$instances[$name]->pushHandler(new \Monolog\Handler\StreamHandler('php://output', \Monolog\Logger::INFO));
		}

		return self::$instances[$name];
	}

}