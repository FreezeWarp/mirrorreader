<?php
namespace MirrorReader;

class Spider {

    /**
     * @var string The domain being processed by this spider.
     */
    private $domain;

    /**
     * @var string If set, all URLs must match this regex to be considered.
     */
    private $match;

    /**
     * @var array A list of items already queued this session. They will not be requeued during this session, reducing the size of the queue.
     * For performance, this should probably be converted to a ordered list or associative array.
     */
    private static $processed_cache = [];
    
    
    public function __construct($domain, $match) {
        $this->domain = $domain;
        $this->match = $match;
    }
    
    
	public function processFile($srcUrl, $lastFile = false) {


        if (in_array($srcUrl, self::$processed_cache)) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->debug('File already submitted this session', [$srcUrl, $lastFile]);
            return;
        } else {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->debug('Processing file', [$srcUrl, $lastFile]);
        }

        self::$processed_cache[] = $srcUrl;


        if (!$srcUrl) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- empty', [$srcUrl, $lastFile]);
            return;
        }


	    // Hash ban
	    if (strpos($srcUrl, '#') === 0) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- hash ban', [$srcUrl, $lastFile]);
            return;
        }


	    // Forums Ban
	    if (
	        (stripos($srcUrl, "/forums/") !== false)
            || (stripos($srcUrl, "forums.") !== false)
        ) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- forums ban', [$srcUrl, $lastFile]);
            return;
        }


	    // Domain Exceptions
	    if (stripos($srcUrl, "wikipedia.org/") !== false ||
	        stripos($srcUrl, "youtube.com/") !== false ||
	        stripos($srcUrl, "mediawiki.org/") !== false ||
	        stripos($srcUrl, "facebook.com/") !== false ||
	        stripos($srcUrl, "reddit.com/") !== false ||
	        stripos($srcUrl, "twitter.com/") !== false ||
	        stripos($srcUrl, "tumblr.com/share/") !== false ||
	        stripos($srcUrl, "archive.org/") !== false ||
	        stripos($srcUrl, "scorecardresearch.com/") !== false ||
	        stripos($srcUrl, "pixel.wp.com/") !== false
        ) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- domain ban', [$srcUrl, $lastFile]);
            return;
	    }


	    // File Exceptions
	    if (stripos($srcUrl, "/wp-json/") !== false ||
	        stripos($srcUrl, "/oembed/") !== false ||
	        stripos($srcUrl, "/ebay/") !== false ||
	        stripos($srcUrl, "/ebaysearch/") !== false ||
	        stripos($srcUrl, "/amazon/") !== false ||
	        stripos($srcUrl, "/random/") !== false ||
	        stripos($srcUrl, "/feed/") !== false ||
            stripos($srcUrl, "/clientscript/") !== false ||
	        stripos($srcUrl, ".msg") !== false ||
	        stripos($srcUrl, "prev_next") !== false ||
	        stripos($srcUrl, "Special:") !== false ||
	        stripos($srcUrl, "Talk:") !== false ||
	        stripos($srcUrl, "User:") !== false
        ) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- file ban', [$srcUrl, $lastFile]);
            return;
	    }


	    // Protocol ban
	    if (stripos($srcUrl, "javascript:") !== false ||
	        stripos($srcUrl, "mailto:") !== false ||
	        stripos($srcUrl, "irc:") !== false ||
	        stripos($srcUrl, "aim:") !== false
        ) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- protocol ban', [$srcUrl, $lastFile]);
	        return;
	    }


	    // GET Ban
	    if (preg_match("/(&|\\?)("
            . "p=|sort=|view=|theme=|do=(add|sendtofriend|getinfo|markread)|week=|replytocom|advertisehereid=|oldid"
            . "|mobileaction|veaction=edit|action=(pmformcreate|edit|create|history|info|printpage|register|lostpw)|postingmode="
            . "|printable|parent=|redirect"
        . ")/", $srcUrl) !== 0) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- GET ban', [$srcUrl, $lastFile]);
            return;
	    }


	    // Page ban
	    if (preg_match("/\/("
            . "privmsg|posting|api|xmlrpc|newreply|sendmessage|newthread"
            . "|cron|external|private|printthread|register|search|showpost"
        . ")\.php/", $srcUrl) !== 0) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- page ban', [$srcUrl, $lastFile]);
	        return;
	    }



        $object = \MirrorReader\Factory::get($srcUrl);


	    if (file_exists($object->getFileStore())) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- already exists', [$srcUrl, $lastFile]);
            return;
        }

        if (\MirrorReader\RedisInstance::get()->sIsMember("Spider-{$this->domain}-errors", $object->getFile())) {
            \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- previous failure', [$srcUrl, $lastFile]);
            return;
        }


        if ($this->match) {
            if (preg_match("/{$this->match}/", $srcUrl) !== 1) {
                \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- bad match', [$srcUrl, $lastFile, $this->match]);
                return;
            }
        }
        else {
            $target_host = parse_url($object->getFile())['host'];
            $target_host_primary = implode('.', array_slice(explode('.', $target_host), -2, 2));
            $self_host_primary = implode('.', array_slice(explode('.', $this->domain), -2, 2));

            if ($target_host_primary != $self_host_primary) {
                \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Refused to submit file -- different host', [$srcUrl, $lastFile, $target_host_primary, $self_host_primary]);
                return;
            }
        }

        \MirrorReader\Logger::getLogger("Spider-{$this->domain}")->info('Queueing file', [$srcUrl, $lastFile]);
        \MirrorReader\Queue::getProducer()->send(\MirrorReader\Queue::getQueue($this->domain), \MirrorReader\Queue::getConnection()->createMessage($srcUrl));
	}

}