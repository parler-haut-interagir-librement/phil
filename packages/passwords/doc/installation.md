# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `clone()` with properties, `Random\Randomizer` |
| `ext-mbstring` | Passwords read by characters, not bytes |
| `ext-ctype` | Digits of dates and of answers of the breach source |
| `symfony/http-client-contracts` 3.6 and an HTTP client, optional | `PwnedPasswordsSource` |
| `symfony/validator` 8.2 or later, optional | The `MinimumStrength` and `NotCompromised` constraints |

## Installing

```sh
composer require phil/passwords
```

It is a library, not a bundle: there is nothing to register. Build the estimator, the checker and the generators once
and keep them; none holds state between two calls, so that they are safe in a worker that serves many requests.

```php
use Phil\Passwords\Breach\Adapter\PwnedPasswordsSource;
use Phil\Passwords\Breach\BreachChecker;
use Phil\Passwords\Strength\StrengthEstimator;
use Symfony\Component\HttpClient\HttpClient;

$estimator = new StrengthEstimator();                       // the bundled dictionaries and layouts
$checker = new BreachChecker(new PwnedPasswordsSource(HttpClient::create()));
```

Building the estimator indexes its dictionaries, a few milliseconds: build it once per process, not once per call.

## In a Symfony application

Declare the services, the validators with them, and the translations of the library:

```yaml
services:
    Phil\Passwords\Strength\StrengthEstimator: ~

    Phil\Passwords\Breach\Adapter\PwnedPasswordsSource:
        arguments: ['@http_client']

    Phil\Passwords\Breach\BreachChecker:
        arguments: ['@Phil\Passwords\Breach\Adapter\PwnedPasswordsSource']

    Phil\Passwords\Validator\MinimumStrengthValidator:
        arguments: ['@Phil\Passwords\Strength\StrengthEstimator']
        tags: ['validator.constraint_validator']

    Phil\Passwords\Validator\NotCompromisedValidator:
        arguments: ['@Phil\Passwords\Breach\BreachChecker']
        tags: ['validator.constraint_validator']
```

```yaml
framework:
    translator:
        paths:
            - '%kernel.project_dir%/vendor/phil/passwords/translations'
```

The translation files are PHP arrays: `phil_passwords.<locale>.php` for the feedback, `validators.<locale>.php` for
the constraints.
