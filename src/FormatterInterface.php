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

/**
 * Interface for objects used to format data from Bigstock API prior to syndication.
 *
 * @package Shutterstock\Phergie\Plugin\Bigstock
 */
interface FormatterInterface
{
    /**
     * Formats data from an individual image response for syndication.
     *
     * @param array $image individual image from response
     * @return string formatted image response
     */
    public function format(array $image);
}

