<?php
/**
 * Phergie plugin for Use Bigstock API to search for and display images (https://github.com/shutterstock/phergie-irc-plugin-react-bigstock)
 *
 * @link https://github.com/shutterstock/phergie-irc-plugin-react-bigstock for the canonical source repository
 * @copyright Copyright (c) 2015 Shutterstock, Inc. (http://www.bigstockphoto.com)
 * @license http://phergie.org/license Simplified BSD License
 * @package Shutterstock\Phergie\Plugin\Bigstock
 */

namespace Shutterstock\Phergie\Plugin\Bigstock;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use React\Promise\Deferred;
use WyriHaximus\Phergie\Plugin\Http\Request;

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
     * Log debugging messages
     *
     * @param string $message
     */
    public function logDebug($message)
    {
        $this->logger->debug('[Bigstock]' . $message);
    }

    /**
     * Command to search Bigstock
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleBigstockCommand(Event $event, Queue $queue)
    {
        $params = $event->getCustomParams();
        if (count($params) < 1) {
            $this->handleBigstockHelp($event, $queue);
            return;
        }

        $request = new Request([
            'url' => 'http://api.bigstockphoto.com/2/' . $this->accountId . '/search/?q=' . urlencode(implode(' ', $params)) . '&limit=10&thumb_size=large_thumb',
            'resolveCallback' =>
                function ($data, $headers, $code) use ($event, $queue) {
                    $data = json_decode($data, true);
                    if ($code !== 200) {
                        $this->logDebug('Bigstock responded with code ' . $code . ' message :' . $data['error']['message']);
                        foreach ($event->getTargets() as $target) {
                            $queue->ircPrivmsg($target, "Sorry, no images found that matched your query");
                        }
                        return;
                    }
                    $this->logDebug('Bigstock returned ' . $data['data']['paging']['items'] . ' of ' . $data['data']['paging']['total_items']);
                    $image_key = array_rand($data['data']['images']);
                    $image = $data['data']['images'][$image_key];
                    $this->sendMessage($image, $event, $queue);
                },
            'rejectCallback' => 
                function ($data, $headers, $code) use ($event, $queue) {
                    foreach ($event->getTargets() as $target) {
                        $queue->ircPrivmsg($target, "Sorry, there was a problem communicating with the API");
                    }
                }
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
     * Send a response
     *
     * @param object $image
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function sendMessage($image, Event $event, Queue $queue)
    {
        $message = $image['large_thumb']['url'];
        foreach ($event->getTargets() as $target) {
            $queue->ircPrivmsg($target, $message);
        }
    }
}
