<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://lh3.googleusercontent.com/ehSTPnXqrkk0M3U-UPCjC0fty9K6lgykK2WOUA2nUHp8gIkRjeTN8z8SABlkvcvR-9PIrboxIvPGujPgWebLQeHHgX7yLUoxFSduiZrTog6WoZLiAvqcTR1QTPVRmns2tYjACpp7EQ=w2400" height="100px">
    </a>
    <h1 align="center">Mailer services</h1>
    <br>
</p>

[![Total Downloads](https://img.shields.io/packagist/dt/yii-extension/mailer-service)](https://packagist.org/packages/yii-extension/mailer-service)
[![build](https://github.com/yii-extension/mailer-service/workflows/build/badge.svg)](https://github.com/yii-extension/mailer-service/actions)
[![codecov](https://codecov.io/gh/yii-extension/mailer-service/branch/master/graph/badge.svg)](https://codecov.io/gh/yii-extension/mailer-service)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyii-extension%2Fmailer-service%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yii-extension/mailer-service/master)
[![static analysis](https://github.com/yii-extension/mailer-service/workflows/static%20analysis/badge.svg)](https://github.com/yii-extension/mailer-service/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/yii-extension/mailer-service/coverage.svg)](https://shepherd.dev/github/yii-extension/mailer-service)


## Requirements

The minimum requirement by this project template that your Web server supports PHP 7.4.0.

## Installation

~~~
composer require yii-extension/mailer-service
~~~

## Usages:

You can inject mailer-service into the controller or action, and automatically all dependencies are resolved by autowired in di-container.

```php
public function contact(
    MailerService $mailer,
    ServerRequestInterface $request,
): ResponseInterface {
    $mailer->run(
        'test@example.com', // from
        'admin1@example.com', // to
        'TestMe', // subject
        '@mail', // path mail
        [ 'html' => 'contact'], // name layout
        [ // params
            'username' => 'User',
            'body' => 'TestMe',
        ],
        $request->getUploadedFiles(), // attach files
    );
}
```

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [roave-infection-static-analysis-plugin](https://github.com/Roave/infection-static-analysis-plugin) mutation framework. To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```
