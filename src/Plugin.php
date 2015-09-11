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
use Phergie\Irc\Client\React\LoopAwareInterface;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use WyriHaximus\Phergie\Plugin\Http\Request;
use WyriHaximus\Phergie\Plugin\Url\Url;

/**
 * Plugin class.
 *
 * @category Shutterstock
 * @package Shutterstock\Phergie\Plugin\Bigstock
 */
class Plugin extends AbstractPlugin implements LoopAwareInterface, LoggerAwareInterface
{
    /**
     * API account ID associated with your Bigstock account
     *
     * @var string
     */
    private $accountId;

    /**
     * The bot's event loop
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * Logger for any debugging output the plugin may emit
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

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
     * accountId - The API account ID associated with your Bigstock account
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

        $this->logger = new NullLogger();
    }

    /**
     * Set the event loop (LoopAwareInterface)
     *
     * @param \React\EventLoop\LoopInterface $loop
     */
    public function setLoop(\React\EventLoop\LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Sets the logger for the plugin to use.
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
     * @return Shutterstock\Phergie\Plugin\Bigstock\FormatterInterface
     */
    protected function getFormatter(array $config)
    {
        if (isset($config['formatter'])) {
            if (!$config['formatter'] instanceof FormatterInterface) {
                throw new \DomainException(
                    '"formatter" must implement ' . __NAMESPACE__ . '\\FormatterInterface'
                );
            }
            return $config['formatter'];
        }
        return new DefaultFormatter;
    }

    /**
     * Command to search Bigstock
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleBigstockCommand(Event $event, Queue $queue)
    {
        $this->logger->info('Bigstock plugin received a new command');

        $params = $event->getCustomParams();
        if (count($params) < 1) {
            $this->logger->debug('Bigstock plugin did not detect any custom params, returning help method');
            $this->handleBigstockHelp($event, $queue);
            return;
        }

        $this->logger->info('Bigstock plugin performing search with params', [
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
                        $this->logger->notice('Bigstock api responded with error', [
                            'code' => $code,
                            'message' => $data['error']['message'],
                        ]);
                        $this->sendMessage(
                            'Sorry, no images were found that matched your query',
                            $event,
                            $queue
                        );
                        return;
                    }

                    $this->logger->info('Bigstock api successful return', [
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
                            $this->logger->info('Bigstock responding with successful url shortening', [
                                'message' => $message,
                            ]);
                            $this->sendMessage($message, $event, $queue);
                        },
                        function () use ($image, $event, $queue) {
                            $message = $this->formatter->format($image);
                            $this->logger->info('Bigstock responding with failed url shortening', [
                                'message' => $message,
                            ]);
                            $this->sendImageMessage($message, $event, $queue);
                        }
                    );
                },
            'rejectCallback' =>
                function ($data, $headers, $code) use ($event, $queue) {
                    $this->logger->notice('Bigstock api failed to respond');
                    $this->sendMessage(
                        'Sorry, there was a problem communicating with the API',
                        $event,
                        $queue
                    );
                },
        ]);
        $this->getEventEmitter()->emit('http.request', [$request]);
    }

    /**
     * Bigstock Command Help
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleBigstockHelp(Event $event, Queue $queue)
    {
        $this->sendHelpReply($event, $queue, array(
            'Usage: bigstock queryString',
            'queryString - the search query (all words are assumed to be part of message)',
            'Searches Bigstock for an image based on the provided query string.',
        ));
    }

    /**
     * Responds to a help command.
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     * @param array $messages
     */
    protected function sendHelpReply(Event $event, Queue $queue, array $messages)
    {
        $method = 'irc' . $event->getCommand();
        $target = $event->getSource();
        foreach ($messages as $message) {
            $queue->$method($target, $message);
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
            $this->logger->info('Emitting: ' . $eventName);
            $this->emitter->emit($eventName, array($url, $privateDeferred));
        } elseif (count($this->emitter->listeners($eventName . 'all')) > 0) {
            $eventName .= 'all';
            $this->logger->info('Emitting: ' . $eventName);
            $this->emitter->emit($eventName, array($url, $privateDeferred));
        } else {
            $this->loop->addTimer(0.1, function () use ($privateDeferred) {
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
        $this->loop->addTimer($this->shortenTimeout, function () use ($privateDeferred) {
            $privateDeferred->reject();
        });

        return array(
            $privateDeferred,
            $userFacingPromise,
        );
    }

    /**
     * Send a response
     *
     * @param string $message
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    protected function sendMessage($message, Event $event, Queue $queue)
    {
        foreach ($event->getTargets() as $target) {
            $queue->ircPrivmsv($target, $message);
        }
    }

}

