# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `clone()` with properties, `array_all()` |
| `ext-mbstring` | The source made valid UTF-8, escapes turned into characters, subjects of removals cut |
| `symfony/validator` 8.2 or later, optional | The `SafeCss` constraint |
| `symfony/translation` and `ext-intl` or its polyfill, optional | The ICU catalogues of the `SafeCss` messages |

## Installing

```sh
composer require phil/css-sanitizer
```

It is a library, not a bundle: there is nothing to register but, optionally, the translations of its constraint. Build a `CssSanitizer` with the application's policy,
once, and keep it; it holds no state between two calls.

```php
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\Policy;

$sanitizer = new CssSanitizer();                                     // the default policy, every URL refused
$print = new CssSanitizer(new Policy()->withProperties('position'));  // a policy of its own
```

In a Symfony application, declare the policy and the sanitizer as services, and the validator with them so that the
`SafeCss` constraint uses the same policy:

```yaml
services:
    app.css_policy:
        class: Phil\CssSanitizer\Policy
        factory: ['@App\Css\PolicyFactory', 'create']

    Phil\CssSanitizer\CssSanitizer:
        arguments: ['@app.css_policy']

    Phil\CssSanitizer\Validator\SafeCssValidator:
        arguments: ['@Phil\CssSanitizer\CssSanitizer']
        tags: ['validator.constraint_validator']
```

Without that declaration, the validator is built by the validator's own factory with the default policy.

## Translations

The messages of `SafeCss` are translated by catalogues in ICU MessageFormat, in `translations/`
([Validator](validator.md#messages-and-translations)). Give their directory to the translator:

```yaml
framework:
    translator:
        paths:
            - '%kernel.project_dir%/vendor/phil/css-sanitizer/translations'
```
