# Installation

## Requirements

PHP 8.5 or later, with `ext-intl` (IDNA and Unicode normalisation), `ext-ctype` and `ext-filter` (IP literals);
`symfony/validator` 8.2 or later for the constraint, and `symfony/filesystem` 8.2 or later for the importers, both
installed with the library.

## Installing

```sh
composer require phil/email-address
```

## As a Symfony service

The library declares no service. An application that wants one validator everywhere declares it:

```php
// config/services.php
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\DnsGetRecordResolver;
use Phil\EmailAddress\EmailValidator;
use Phil\EmailAddress\TopLevelDomains;
use Phil\EmailAddress\Validator\DeliverableEmailAddressValidator;

$services->set(DnsGetRecordResolver::class);
$services->set(EmailValidator::class)
    ->arg('$disposable', service('app.disposable_domains'))
    ->arg('$resolver', service(DnsGetRecordResolver::class))
    ->arg('$topLevelDomains', service('app.top_level_domains'));
$services->set('app.disposable_domains', DisposableDomains::class)
    ->factory([DisposableDomains::class, 'bundled']);
$services->set('app.top_level_domains', TopLevelDomains::class)
    ->factory([TopLevelDomains::class, 'bundled']);
$services->set(DeliverableEmailAddressValidator::class)
    ->arg('$disposableDomains', service('app.disposable_domains'))
    ->arg('$topLevelDomains', service('app.top_level_domains'))
    ->arg('$resolver', service(DnsGetRecordResolver::class))
    ->tag('validator.constraint_validator');
```

The constraint works without this declaration too: the Validator then builds its validator itself, with the bundled
lists and `dns_get_record()`. See [Symfony Validator](symfony-validator.md).

Every class is immutable and keeps no state between calls: one instance serves every request of a long-running
worker.
