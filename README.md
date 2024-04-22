# This is my package symfony-messanger

[![PHP Version Require](https://poser.pugx.org/spiral-packages/symfony-messanger/require/php)](https://packagist.org/packages/spiral-packages/symfony-messanger)
[![Latest Stable Version](https://poser.pugx.org/spiral-packages/symfony-messanger/v/stable)](https://packagist.org/packages/spiral-packages/symfony-messanger)
[![phpunit](https://github.com/spiral-packages/symfony-messanger/actions/workflows/phpunit.yml/badge.svg)](https://github.com/spiral-packages/symfony-messanger/actions)
[![psalm](https://github.com/spiral-packages/symfony-messanger/actions/workflows/psalm.yml/badge.svg)](https://github.com/spiral-packages/symfony-messanger/actions)
[![Codecov](https://codecov.io/gh/spiral-packages/symfony-messanger/branch/master/graph/badge.svg)](https://codecov.io/gh/spiral-packages/symfony-messanger/)
[![Total Downloads](https://poser.pugx.org/spiral-packages/symfony-messanger/downloads)](https://packagist.org/spiral-packages/symfony-messanger/phpunit)


This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.


## Requirements

Make sure that your server is configured with following PHP version and extensions:

- PHP 8.1+
- Spiral framework 3.0+

## Installation

You can install the package via composer:

```bash
composer require spiral-packages/symfony-messanger
```

After package install you need to register bootloader from the package.

```php
protected const LOAD = [
    // ...
    \Spiral\SymfonyMessanger\Bootloader\MessengerBootloader::class,
];
```

> Note: if you are using [`spiral-packages/discoverer`](https://github.com/spiral-packages/discoverer),
> you don't need to register bootloader by yourself.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [butschster](https://github.com/spiral-packages)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
