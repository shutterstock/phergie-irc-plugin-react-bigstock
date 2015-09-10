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
        } else {
            $request = new Request([
                'url' => 'http://api.bigstockphoto.com/2/' . $this->accountId . '/search/?q=' . urlencode(implode(' ', $params)),
                'resolveCallback' =>
                    function ($data, $headers, $code) {
                        $this->logDebug('Bigstock response: ' . $data);
                        // @todo something other than just logging the response
                    },
//                'rejectCallback' => [$deferred, 'reject']
            ]);
            $this->getEventEmitter()->emit('http.request', [$request]);
        }
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
            'Searches bigstock for an image based on the provided query string.',
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
}
