<?php
/**
 * Phergie plugin for Use Bigstock API to search for and display images
 * (https://github.com/shutterstock/phergie-irc-plugin-react-bigstock)
 *
 * @link https://github.com/shutterstock/phergie-irc-plugin-react-bigstock for the canonical source repository
 * @copyright Copyright (c) 2015 Shutterstock, Inc. (http://www.bigstockphoto.com)
 * @license http://phergie.org/license Simplified BSD License
 * @package Shutterstock\Phergie\Plugin\Bigstock
 */

namespace Shutterstock\Phergie\Plugin\Bigstock;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEventInterface as Event;
use React\Promise\Deferred;
use WyriHaximus\Phergie\Plugin\Http\Request;
use WyriHaximus\Phergie\Plugin\Url\Url;

/**
 * Plugin class.
 *
 * @category Shutterstock
 * @package Shutterstock\Phergie\Plugin\Bigstock
 */
class Plugin extends AbstractPlugin
{
    /**
     * API account ID associated with your Bigstock account
     *
     * @var string
     */
    private $accountId;

    /**
     * Maximum time to wait for URL shortener
     *
     * @var int
     */
    protected $shortenTimeout = 15;

    /**
     * Accepts plugin configuration.
     *
     * Supported keys:
     *
     * accountId - REQUIRED: The API account ID associated with your Bigstock account
     *
     * formatter - OPTIONAL: An instance of \Shutterstock\Phergie\Plugin\Bigstock\FormatterInterface
     *
     * shortenTimeout - OPTIONAL: How long to wait for URL shortener before skipping it (default = 15)
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (empty($config['accountId'])) {
            throw new \InvalidArgumentException("Missing required configuration key 'accountId'");
        }
        $this->accountId = $config['accountId'];

        $this->formatter = $this->getFormatter($config);

        if (isset($config['shortenTimeout'])) {
            $this->shortenTimeout = $config['shortenTimeout'];
        }
    }

    /**
     * Subscribe to events
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            'command.bigstock' => 'handleBigstockCommand',
            'command.bigstock.help' => 'handleBigstockHelp',
        ];
    }

    /**
     * Returns a configured formatter
     *
     * @param array $config
     * @return \Shutterstock\Phergie\Plugin\Bigstock\FormatterInterface
     */
    protected function getFormatter(array $config)
    {
        if (isset($config['formatter'])) {
            if (!$config['formatter'] instanceof FormatterInterface) {
                throw new \DomainException(
                    "'formatter' must implement Shutterstock\\Phergie\\Plugin\\Bigstock\\FormatterInterface"
                );
            }
            return $config['formatter'];
        }
        return new DefaultFormatter;
    }

    /**
     * Command to search Bigstock
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleBigstockCommand(Event $event, Queue $queue)
    {
        $this->getLogger()->info('[Bigstock] received a new command');

        $params = $event->getCustomParams();
        if (count($params) < 1) {
            $this->getLogger()->debug('[Bigstock] did not detect any custom params, returning help method');
            $this->handleBigstockHelp($event, $queue);
            return;
        }

        $this->getLogger()->info('[Bigstock] performing search with params', [
            'params' => $params,
        ]);
        $search_request = "http://api.bigstockphoto.com/2/{$this->accountId}/search?";
        $search_request .= http_build_query([
            'q' => implode(' ', $params),
            'limit' => 10,
            'thumb_size' => 'large_thumb,small_thumb',
        ]);

        $request = new Request([
            'url' => $search_request,
            'resolveCallback' =>
                function ($data, $headers, $code) use ($event, $queue) {
                    $data = json_decode($data, true);

                    if ($code !== 200) {
                        $this->getLogger()->notice('[Bigstock] API responded with error', [
                            'code' => $code,
                            'message' => $data['error']['message'],
                        ]);
                        $queue->ircPrivmsg($event->getSource(), 'Sorry, no images were found that match your query');
                        return;
                    }

                    $this->getLogger()->info('[Bigstock] API successful return', [
                        'items' => $data['data']['paging']['items'],
                        'total' => $data['data']['paging']['total_items'],
                    ]);
                    $image_key = array_rand($data['data']['images']);
                    $image = $data['data']['images'][$image_key];
                    $image['url'] = "http://www.bigstockphoto.com/image-{$image['id']}";
                    $this->emitShorteningEvents($image['url'])->then(
                        function ($shortUrl) use ($image, $event, $queue) {
                            $image['url_short'] = $shortUrl;
                            $message = $this->formatter->format($image);
                            $this->getLogger()->info('[Bigstock] responding with successful url shortening', [
                                'message' => $message,
                            ]);
                            $queue->ircPrivmsg($event->getSource(), $message);
                        },
                        function () use ($image, $event, $queue) {
                            $message = $this->formatter->format($image);
                            $this->getLogger()->info('[Bigstock] responding with failed url shortening', [
                                'message' => $message,
                            ]);
                            $queue->ircPrivmsg($event->getSource(), $message);
                        }
                    );
                },
            'rejectCallback' =>
                function ($data, $headers, $code) use ($event, $queue) {
                    $this->getLogger()->notice('[Bigstock] API failed to respond');
                    $queue->ircPrivmsg($event->getSource(), 'Sorry, there was a problem communicating with the API');
                },
        ]);
        $this->getEventEmitter()->emit('http.request', [$request]);
    }

    /**
     * Bigstock Command Help
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleBigstockHelp(Event $event, Queue $queue)
    {
        $messages = [
            'Usage: bigstock queryString',
            'queryString - the search query (all words are assumed to be part of message)',
            'Searches Bigstock for an image based on the provided query string.',
        ];
        foreach ($messages as $message) {
            $queue->ircPrivmsg($event->getSource(), $message);
        }
    }

    /**
     * Emit URL shortening events
     *
     * @param string $url
     * @return \React\Promise\Deferred
     */
    public function emitShorteningEvents($url)
    {
        $host = Url::extractHost($url);
        list($privateDeferred, $userFacingPromise) = $this->preparePromises();

        $eventName = 'url.shorting.';
        if (count($this->emitter->listeners($eventName . $host)) > 0) {
            $eventName .= $host;
            $this->getLogger()->info('[Bigstock] emitting: ' . $eventName);
            $this->emitter->emit($eventName, [$url, $privateDeferred]);
        } elseif (count($this->emitter->listeners($eventName . 'all')) > 0) {
            $eventName .= 'all';
            $this->getLogger()->info('[Bigstock] emitting: ' . $eventName);
            $this->emitter->emit($eventName, [$url, $privateDeferred]);
        } else {
            $this->getLoop()->addTimer(0.1, function () use ($privateDeferred) {
                $privateDeferred->reject();
            });
        }

        return $userFacingPromise;
    }

    /**
     * Prepare promises for URL shortening
     *
     * @return array
     */
    private function preparePromises()
    {
        $userFacingDeferred = new Deferred();
        $privateDeferred = new Deferred();
        $userFacingPromise = $userFacingDeferred->promise();
        $privateDeferred->promise()->then(function ($shortUrl) use ($userFacingDeferred) {
            $userFacingDeferred->resolve($shortUrl);
        }, function () use ($userFacingDeferred) {
            $userFacingDeferred->reject();
        });
        $this->getLoop()->addTimer($this->shortenTimeout, function () use ($privateDeferred) {
            $privateDeferred->reject();
        });

        return [
            $privateDeferred,
            $userFacingPromise,
        ];
    }
}

