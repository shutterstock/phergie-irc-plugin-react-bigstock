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

use Shutterstock\Phergie\Plugin\Bigstock\DefaultFormatter;

/**
 * Tests for the DefaultFormatter class.
 *
 * @category Shutterstock
 * @package Shutterstock\Phergie\Plugin\Bigstock
 */
class DefaultFormatterTest extends \PHPUnit_Framework_TestCase
{

    public function testPatternSet()
    {
        $formatter = new DefaultFormatter();
        $this->assertSame(
            \PHPUnit_Framework_Assert::readAttribute($formatter, 'default_pattern'),
            \PHPUnit_Framework_Assert::readAttribute($formatter, 'pattern')
        );

        $pattern = '%title%, %id% || %url% (%small_thumb%)';
        $formatter = new DefaultFormatter($pattern);
        $this->assertSame(
            $pattern,
            \PHPUnit_Framework_Assert::readAttribute($formatter, 'pattern')
        );
    }

}

