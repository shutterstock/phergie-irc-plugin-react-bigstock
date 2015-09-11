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

namespace Shutterstock\Phergie\Tests\Plugin\Bigstock;

use Phake;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use Shutterstock\Phergie\Plugin\Bigstock\Plugin;

/**
 * Tests for the Plugin class.
 *
 * @category Shutterstock
 * @package Shutterstock\Phergie\Plugin\Bigstock
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{


    protected function setUp()
    {
        $this->event = Phake::mock('\Phergie\Irc\Event\UserEventInterface');
        $this->queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $this->emitter = Phake::mock('\Evenement\EventEmitterInterface');
        $this->logger = Phake::mock('\Psr\Log\LoggerInterface');
        $this->plugin = $this->getPlugin();
    }

    protected function getPlugin(array $config = [])
    {
        $config['accountId'] = 'ACCOUNT';
        $plugin = new Plugin($config);
        $plugin->setEventEmitter($this->emitter);
        $plugin->setLogger($this->logger);
        return $plugin;
    }

    /**
     * Tests that getSubscribedEvents() returns an array.
     */
    public function testGetSubscribedEvents()
    {
        $this->assertInternalType('array', $this->plugin->getSubscribedEvents());
    }

}

