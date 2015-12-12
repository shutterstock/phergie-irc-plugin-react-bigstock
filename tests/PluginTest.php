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
    private $event;

    private $queue;

    private $emitter;

    private $logger;

    private $loop;

    private $plugin;

    protected function setUp()
    {
        $this->event = Phake::mock('\Phergie\Irc\Plugin\React\Command\CommandEvent');
        $this->queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $this->emitter = Phake::mock('\Evenement\EventEmitterInterface');
        $this->logger = Phake::mock('\Psr\Log\LoggerInterface');
        $this->loop = Phake::mock('\React\EventLoop\LoopInterface');
        $this->plugin = $this->getPlugin();
    }

    protected function getPlugin()
    {
        $config = [
            'accountId' => 'ACCOUNT'
        ];
        $plugin = new Plugin($config);
        $plugin->setEventEmitter($this->emitter);
        $plugin->setLogger($this->logger);
        $plugin->setLoop($this->loop);
        return $plugin;
    }

    public function testFullConfiguration()
    {
        $config = [
            'accountId' => 'ACCOUNT',
            'formatter' => new \Shutterstock\Phergie\Plugin\Bigstock\DefaultFormatter(
                '%title% - %url_short% < %large_thumb% >'
            ),
            'shortenTimeout' => 15
        ];
        $plugin = new Plugin($config);
        $this->assertInstanceOf('Shutterstock\Phergie\Plugin\Bigstock\Plugin', $plugin);
    }

    public function testInvalidConfiguration()
    {
        try {
            $plugin = new Plugin();
            $this->fail('Plugin should throw exception if required configuration key is missing');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(
                "Missing required configuration key 'accountId'",
                $e->getMessage()
            );
        }

        try {
            $plugin = new Plugin([
                'accountId' => 'Account',
                'formatter' => 'Foo\Bar()',
            ]);
            $this->fail("Plugin should throw exception if 'formatter' is not an instance of FormatterInterface");
        } catch (\DomainException $e) {
            $this->assertSame(
                "'formatter' must implement Shutterstock\\Phergie\\Plugin\\Bigstock\\FormatterInterface",
                $e->getMessage()
            );
        }
    }

    /**
     * Tests that getSubscribedEvents() returns an array.
     */
    public function testGetSubscribedEvents()
    {
        $this->assertInternalType('array', $this->plugin->getSubscribedEvents());
    }

    public function testHandleBigstockCommand()
    {
        Phake::when($this->event)->getCustomParams()->thenReturn(['donkey']);
        Phake::when($this->event)->getSource()->thenReturn('#channel');
        $this->plugin->handleBigstockCommand($this->event, $this->queue);

        Phake::verify($this->emitter)->emit('http.request', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(1, $params);
        $request = reset($params);
        $this->assertInstanceOf('\Phergie\Plugin\Http\Request', $request);

        $config = $request->getConfig();
        $this->assertInternalType('array', $config);
        $this->assertArrayHasKey('resolveCallback', $config);
        $this->assertInternalType('callable', $config['resolveCallback']);
        $this->assertArrayHasKey('rejectCallback', $config);
        $this->assertInternalType('callable', $config['rejectCallback']);
    }

    public function testHandleBigstockCommandNoQuery()
    {
        Phake::when($this->event)->getCustomParams()->thenReturn([]);
        Phake::when($this->event)->getSource()->thenReturn('#channel');
        $this->plugin->handleBigstockCommand($this->event, $this->queue);

        Phake::verify($this->queue, Phake::atLeast(1))->ircPrivmsg('#channel', $this->isType('string'));
    }

    /**
     * Tests handleBigstockHelp().
     */
    public function testHandleBigstockHelp()
    {
        Phake::when($this->event)->getCustomParams()->thenReturn([]);
        Phake::when($this->event)->getSource()->thenReturn('#channel');
        Phake::when($this->event)->getCommand()->thenReturn('PRIVMSG');

        $this->plugin->handleBigstockHelp($this->event, $this->queue);

        Phake::verify($this->queue, Phake::atLeast(1))->ircPrivmsg('#channel', $this->isType('string'));
    }
}
