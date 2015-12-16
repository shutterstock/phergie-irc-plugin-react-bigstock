# shutterstock/phergie-irc-plugin-react-bigstock

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin to use Bigstock API to search for and display images.

[![Build Status](https://secure.travis-ci.org/shutterstock/phergie-irc-plugin-react-bigstock.png?branch=master)](http://travis-ci.org/shutterstock/phergie-irc-plugin-react-bigstock)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "shutterstock/phergie-irc-plugin-react-bigstock": "dev-master"
    }
}
```

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Configuration

If you do not already have a Bigstock API account, you will need to [create one](https://www.bigstockphoto.com/partners/get-started/). You will be given an account ID which must be included in plugin configuration.

```php
return [
    'plugins' => [
        // dependencies
        new \Phergie\Irc\Plugin\React\Command\Plugin, // Handles commands and routes to correct plugins
        new \Phergie\Irc\Plugin\React\CommandHelp\Plugin, // Optional - enables help messages for commands
        new \Phergie\Plugin\Dns\Plugin, // Handles DNS lookups for the HTTP plugin
        new \Phergie\Plugin\Http\Plugin, // Handles the HTTP requests for this plugin
        new \Phergie\Irc\Plugin\React\Url\Plugin, // Helps get hostname for building url.shorten.* events
        new \PSchwisow\Phergie\Plugin\UrlShorten\Plugin, // Optional - provides short URLs if available

        // configuration
        new \Shutterstock\Phergie\Plugin\Bigstock\Plugin([
            // REQUIRED: The API account ID associated with your Bigstock account
            'accountId' => '123456',

            // OPTIONAL: The formatter used for output (default value is shown)
            'formatter' => new \Shutterstock\Phergie\Plugin\Bigstock\DefaultFormatter(
                '%title% - %url_short% < %large_thumb% >'
            )

            // OPTIONAL: How long to wait for URL shortener before skipping it (default value is shown)
            'shortenTimeout' => 15,
        ])
    ]
];
```

## Usage

Use the `bigstock` command to search for images matching your query string and return a randomly selected image from the top 10.

```
PSchwisow: !bigstock puppy
Phergie: Newborn Baby And Puppy - http://gsc.io/u/38 < http://static7.bigstockphoto.com/thumbs/6/3/8/small3/83626697.jpg >
```

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.
