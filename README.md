# This is my package symfony-messenger

[![PHP Version Require](https://poser.pugx.org/spiral/symfony-messenger/require/php)](https://packagist.org/packages/spiral/symfony-messenger)
[![Latest Stable Version](https://poser.pugx.org/spiral/symfony-messenger/v/stable)](https://packagist.org/packages/spiral/symfony-messenger)
[![phpunit](https://github.com/spiral/symfony-messenger/actions/workflows/phpunit.yml/badge.svg)](https://github.com/spiral/symfony-messenger/actions)
[![psalm](https://github.com/spiral/symfony-messenger/actions/workflows/psalm.yml/badge.svg)](https://github.com/spiral/symfony-messenger/actions)
[![Codecov](https://codecov.io/gh/spiral/symfony-messenger/branch/master/graph/badge.svg)](https://codecov.io/gh/spiral/symfony-messenger/)
[![Total Downloads](https://poser.pugx.org/spiral/symfony-messenger/downloads)](https://packagist.org/spiral/symfony-messenger/phpunit)


This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.


## Requirements

Make sure that your server is configured with following PHP version and extensions:

- PHP 8.2+
- Spiral framework 3.13+

## Installation

You can install the package via composer:

```bash
composer require spiral/symfony-messenger
```

After package install you need to register bootloader from the package.

```php
// Kernel.php
public function defineBootloaders(): array
{
    return [
        // ...
        \Spiral\Messenger\Bootloader\MessengerBootloader::class,
        // ...
    ];
}
```

> Note: if you are using [`spiral/discoverer`](https://github.com/spiral/discoverer),
> you don't need to register bootloader by yourself.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
