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

    public function dataProviderFormat()
    {
        $image = [
            'id' => 123,
            'title' => 'TEST TITLE',
            'url' => 'http://test/123',
            'url_short' => 'http://test_short/123',
            'small_thumb' => [
                'url' => 'http://small_thumb/123',
            ],
            'large_thumb' => [
                'url' => 'http://large_thumb/123',
            ],
        ];

        return [
            [
                null,
                $image,
                'TEST TITLE - http://test_short/123 < http://large_thumb/123 >',
            ],
            [
                '%title%, %id% || %url% (%small_thumb%)',
                $image,
                'TEST TITLE, 123 || http://test/123 (http://small_thumb/123)',
            ],
            [
                '%title% %empty% id',
                $image,
                'TEST TITLE %empty% id',
            ],
        ];
    }

    /**
     * @dataProvider dataProviderFormat
     */
    public function testFormat($format, array $image, $expected_response)
    {
        $formatter = new DefaultFormatter($format);
        $response = $formatter->format($image);
        $this->assertInternalType('string', $response);
        $this->assertSame($expected_response, $response);
    }

}

