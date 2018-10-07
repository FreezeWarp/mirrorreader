<?php

/**
 * This is a basic tool that scans an existing archive and tries to fill in any gaps. It can be used in a download gets partially corrupted, or your Heritrix job gets corrupted.
 * It even detects a small number of things Heritrix fails to, e.g. <img srcset>. I use it even on completed Heritrix jobs to ensure a full archive.
 * Additionally, it will clean up the directory structure a bit. MirrorReader handles the 1-suffixed directories fairly well (albeit at the cost of speed), but it does not handle 1-suffixed files, which this does deal with.
 * Also note that it will write 301 redirects where they are expected by the including files, unless the 301 is specified in config.php. This is good for archive viewing, but does mean you'll end up with duplicated files. (I find it worthwhile, in any case, to have them located in both places.)
 * Currently searches <a href>, <img src>, and <img srcset>.
 * Does not redownload existing files.
 * Does not check content type, but will only download files matching valid string/regex rules.
 */


// For now, report all errors.
error_reporting(E_ALL);
ini_set('display_errors', 'On');
ini_set('display_startup_errors', 'On');

// Allow Unlimited Execution Time
set_time_limit(0);

// Format as text output
header('Content-Type: text/plain');

// Require Configuration Files
require(__DIR__ . '/vendor/autoload.php');
require('config.php');

// Let's not actually write anything yet
$trial = false;

// Disable the script hacks by default, since they are liable to include too many files in our scan.
\MirrorReader\Processor::$domainConfiguration['default']['scriptHacks'] = [];

// Get $_GETs
$resource = $_GET['resource'];
$protocol = $_GET['protocol'] ?? 'http';
$match = $_GET['match'] ?? '.*';
$path = realpath(\MirrorReader\Processor::$store . $resource);

$spider = new \MirrorReader\Spider($resource, $match);

while ($message = \MirrorReader\Queue::getConsumer($resource)->receiveNoWait()) {

    $srcFile = \MirrorReader\Factory::get($message->getBody());
    $destFile = $srcFile->getFileStore();

    if (!$destFile) {
        \MirrorReader\Logger::getLogger("Spider-{$resource}")->warn('Skipping file, dest root directory doesn\'t exist', [$srcFile, $destFile]);
    }

    elseif (strlen(basename($destFile)) > 254) {
        \MirrorReader\Logger::getLogger("Spider-{$resource}")->warn('Skipping file, destination base name too long', [$srcFile, $destFile]);
    }

    elseif (!is_dir(dirname($destFile)) && !\MirrorReader\MkdirIndex::execute(dirname($destFile))) {
        \MirrorReader\Logger::getLogger("Spider-{$resource}")->error('Skipping file, couldn\'t create destination directory', [$srcFile, $destFile]);
    }

    elseif ($srcFile->fileStore301less != $srcFile->getFileStore()) {
        \MirrorReader\Logger::getLogger("Spider-{$resource}")->warn('Skipping file, appears to have 301 path -- run fileNameFixer.php first', [$srcFile, $destFile]);
    }

    else {

        $client = new \GuzzleHttp\Client([
            'base_uri' => $protocol . '://' . $resource,
            'http_errors' => false,
            // When all is said and done, we want to write the effective URL, not the original request (which should be handled by the redirects)
            'on_stats' => function(\GuzzleHttp\TransferStats $stats) use (&$effectiveUrl, &$transferTime) {
                $effectiveUrl = $stats->getEffectiveUri();
                $transferTime = $stats->getTransferTime();
            },
            'allow_redirects' => [
                // A big part of this is that, unlike Heritrix MirrorWriter, we will record all redirects and write correct files for them.
                'on_redirect' => function(
                    \Psr\Http\Message\RequestInterface $request,
                    \Psr\Http\Message\ResponseInterface $response,
                    \Psr\Http\Message\UriInterface $uri
                ) use ($trial, $resource) {
                    $sourceObject = \MirrorReader\Factory::get((string) $request->getUri());
                    $responseObject = \MirrorReader\Factory::get((string) $uri);

                    if ($responseObject->getFileStore() == $sourceObject->getFileStore()) {
                        \MirrorReader\Logger::getLogger("Spider-{$resource}")->warn('Redirect file points to itself (maybe HTTPS?)', [$request->getUri(), $uri]);
                    }

                    elseif (file_exists($sourceObject->getFileStore())) {
                        \MirrorReader\Logger::getLogger("Spider-{$resource}")->warn('File already exists at redirect location', [$sourceObject->getFileStore(), $uri]);
                    }

                    else {
                        \MirrorReader\Logger::getLogger("Spider-{$resource}")->info('Writing internal redirect file...', [$sourceObject->getFileStore(), $uri]);

                        if (!$trial) {
                            if (!is_dir(dirname($sourceObject->getFileStore())) && !mkdir($sourceObject->getFileStore())) {
                                \MirrorReader\Logger::getLogger("Spider-{$resource}")->error('Failed to create directory for internal redirect file...', [$sourceObject->getFileStore(), $uri]);
                            } elseif (file_put_contents($sourceObject->getFileStore(),
                                '<!-- MirrorReader Redirect Page --><html><head>'
                                . '<title>Internal Redirect</title><meta http-equiv="refresh" content="0; url=' . htmlspecialchars((string) $uri) . '">'
                                . '</head><body>'
                                . '<center><a href="' . htmlspecialchars((string) $uri) . '">Follow redirect.</a></center>'
                                . '</body></html>'
                            )) {
                                \MirrorReader\Logger::getLogger("Spider-{$resource}")->notice('Wrote internal redirect file...', [$sourceObject->getFileStore(), $uri]);
                            } else {
                                \MirrorReader\Logger::getLogger("Spider-{$resource}")->error('Failed to write internal redirect file...', [$sourceObject->getFileStore(), $uri]);
                            }
                        }
                    }
                }
            ]
        ]);

        $client->head($srcFile->getFile());
        $effectiveUrlObject = \MirrorReader\Factory::get((string) $effectiveUrl);

        if (file_exists($effectiveUrlObject->getFileStore())) {
            \MirrorReader\Logger::getLogger("Spider-{$resource}")->notice('Effective URL already exists', [$srcFile->getFile(), $effectiveUrlObject->getFile(), $effectiveUrlObject->getFileStore()]);
        }

        else {
            $response = $client->get($srcFile->getFile());

            \MirrorReader\Logger::getLogger("Spider-{$resource}")->info('Downloaded file', [
                'source' => $srcFile->getFile(),
                'effective' => $effectiveUrlObject->getFile(),
                'time' => $transferTime,
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders()
            ]);

            if ($response->getStatusCode() !== 200) {
                \MirrorReader\Logger::getLogger("Spider-{$resource}")->warn('File does not return 200', [$srcFile->getFile(), $effectiveUrlObject->getFile(), $effectiveUrlObject->getFileStore(), $response]);

                if ($response->getStatusCode() >= 400) {
                    \MirrorReader\Logger::getLogger("Spider-{$resource}")->warn('File with > 400 status code was added to Redis block list', [$srcFile->getFile(), $effectiveUrlObject->getFile(), $effectiveUrlObject->getFileStore(), $response]);
                    \MirrorReader\RedisInstance::get()->sAdd("Spider-{$resource}-errors", $srcFile->getFile());
                }
            }

            else {

                if (
                    !is_file($effectiveUrlObject->getFileStore())
                    && is_dir($effectiveUrlObject->getFileStore())
                    && !file_exists($effectiveUrlObject->getFileStore() . "/index.html")
                ) {
                    \MirrorReader\Logger::getLogger("Spider-{$resource}")->info('Writing new file (as index.html)', [$srcFile->getFile(), $effectiveUrlObject->getFile(), $effectiveUrlObject->getFileStore()]);

                    if (!$trial) {
                        if (file_put_contents($effectiveUrlObject->getFileStore() . "/index.html", $response->getBody()->getContents())) {
                            \MirrorReader\Logger::getLogger("Spider-{$resource}")->notice('Wrote new file (as index.html)', [$srcFile->getFile(), $effectiveUrlObject->getFile(), $effectiveUrlObject->getFileStore()]);
                        } else {
                            \MirrorReader\Logger::getLogger("Spider-{$resource}")->error('Failed to write new file (as index.html)', [$srcFile->getFile(), $effectiveUrlObject->getFile(), $effectiveUrlObject->getFileStore()]);
                        }
                    }
                }

                else {
                    \MirrorReader\Logger::getLogger("Spider-{$resource}")->info('Writing new file', [$srcFile->getFile(), $effectiveUrlObject->getFile(), $effectiveUrlObject->getFileStore()]);

                    if (!$trial) {
                        if (file_put_contents($effectiveUrlObject->getFileStore(), $response->getBody()->getContents())) {
                            \MirrorReader\Logger::getLogger("Spider-{$resource}")->notice('Wrote new file', [$srcFile->getFile(), $effectiveUrlObject->getFile(), $effectiveUrlObject->getFileStore()]);
                        } else {
                            \MirrorReader\Logger::getLogger("Spider-{$resource}")->error('Failed to write new file', [$srcFile->getFile(), $effectiveUrlObject->getFile(), $effectiveUrlObject->getFileStore()]);
                        }
                    }
                }

                if (!$trial) {
                    usleep(50000);
                    // Now process the newly-obtained file for outlinks
                    // Tell the Resource Object to invoke the processFile function whenever it encounters a URL.
                    $effectiveUrlObject = \MirrorReader\Factory::get((string) $effectiveUrl); // recreate since the file should now exist
                    //var_dump($effectiveUrlObject);
                    $effectiveUrlObject->formatUrlCallback = [$spider, 'processFile'];
                    $effectiveUrlObject->getContents();
                }

            }
        }
    }

    // Remove the message from the queue
    \MirrorReader\Queue::getConsumer($resource)->acknowledge($message);
    usleep(500000);
    flush();
}