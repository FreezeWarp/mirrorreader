<?php
namespace MirrorReader;

class Queue {
	private static $connectionFactory;
	private static $queue;
	private static $consumer;
    private static $producer;

	public static function getConnection() :  \Enqueue\Fs\FsContext {
        return self::$connectionFactory ?: self::$connectionFactory = (new \Enqueue\Fs\FsConnectionFactory())->createContext();
	}

	public static function getQueue($domain) : \Enqueue\Fs\FsDestination {
		return self::$queue ?: self::$queue = self::getConnection()->createQueue("MRSpider-$domain");
	}

    public static function getConsumer($domain) : \Enqueue\Fs\FsConsumer {
        return self::$consumer ?: self::$consumer = self::getConnection()->createConsumer(self::getQueue($domain));
    }

    public static function getProducer() : \Enqueue\Fs\FsProducer {
        return self::$producer ?: self::$producer = self::getConnection()->createProducer();
    }
}