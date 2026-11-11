# Installation

## Requirements

PHP 8.5 or later, with `ext-ctype`, `ext-iconv` (encoded words), `ext-mbstring` and `ext-sodium` (base64 decoding).

## Installing

```sh
composer require phil/bounce-parser
```

## As a Symfony service

The library declares no service. An application declares the parser with its rules, its return paths and its
authenticator:

```php
// config/services.php
use Phil\BounceParser\Auth\AuthenticationResults;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Verp;

$services->set(Verp::class)->args(['bounces', 'mail.example.net']);
$services->set(AuthenticationResults::class)->args([['mail.example.net'], ['fbl.example.com']]);
$services->set(BounceParser::class)
    ->arg('$verp', service(Verp::class))
    ->arg('$authenticator', service(AuthenticationResults::class));
```

Every class is immutable and keeps no state between calls: one parser serves every message of a long-running worker.
