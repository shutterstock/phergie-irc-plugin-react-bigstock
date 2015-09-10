# shutterstock/phergie-irc-plugin-react-bigstock

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin for Use Bigstock API to search for and display images.

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

```php
return [
    'plugins' => [
        // configuration
        new \Shutterstock\Phergie\Plugin\Bigstock\Plugin([



        ])
    ]
];
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
