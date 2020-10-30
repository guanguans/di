# di

> A flexible dependency injection container based on the modification of `illuminate/container`. - 一个灵活的基于 `illuminate/container` 修改的依赖注入容器。

![Tests](https://github.com/guanguans/di/workflows/Tests/badge.svg)
![Check & fix styling](https://github.com/guanguans/di/workflows/Check%20&%20fix%20styling/badge.svg)
[![codecov](https://codecov.io/gh/guanguans/di/branch/main/graph/badge.svg)](https://codecov.io/gh/guanguans/di)
[![Latest Stable Version](https://poser.pugx.org/guanguans/di/v)](//packagist.org/packages/guanguans/di)
[![Total Downloads](https://poser.pugx.org/guanguans/di/downloads)](//packagist.org/packages/guanguans/di)
[![License](https://poser.pugx.org/guanguans/di/license)](//packagist.org/packages/guanguans/di)

## Requirement

* PHP >= 5.6

## Installation

``` bash
$ composer require guanguans/di -vvv
```

## Usage

``` php
<?php

require __DIR__.'/vendor/autoload.php';

class ConcreteStub{}

$container = new \Guanguans\Di\Container();

// Simple Bindings
$container->bind(ConcreteStub::class, function ($container) {
    return new ConcreteStub();
});

// Binding A Singleton
$container->singleton('ConcreteStub', function ($container) {
    return new ConcreteStub();
});

// Binding Interfaces To Implementations
$container->bind(
    'App\Contracts\EventPusher',
    'App\Services\RedisEventPusher'
);

// Resolving
$concreteStub = $container->make(ConcreteStub::class);
```

## Testing

``` bash
$ composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

* [guanguans](https://github.com/guanguans)
* [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
