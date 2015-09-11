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

class DefaultFormatter implements FormatterInterface
{

    protected $pattern;

    protected $default_pattern = '%title% - %url_short% < %large_thumb% >';

    public function __construct($pattern = null)
    {
        $this->pattern = (!is_null($pattern)) ? $pattern : $this->default_pattern;
    }

    public function format(array $image)
    {
        $replacements = [
            '%id%' => $image['id'],
            '%title%' => $image['title'],
            '%url%' => $image['url'],
            '%url_short%' => empty($image['url_short']) ? $image['url'] : $image['url_short'],
            '%small_thumb%' => $image['small_thumb']['url'],
            '%large_thumb%' => $image['large_thumb']['url'],
        ];

        $formatted = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $this->pattern
        );
        return $formatted;
    }

}

