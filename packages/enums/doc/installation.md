# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | The traits use `array_any()` and `array_first()`, and the library is tested on PHP 8.5. |
| `ext-mbstring` | The default labels of `stringOptions()` change the case of names that may hold letters outside ASCII. |

The library has no package dependency. Its PHPStan extensions use PHPStan's own classes, which PHPStan provides
when it loads them.

## Installing

```sh
composer require phil/enums
```

Nothing to register: the library is a set of traits, attributes and a small metadata reader. Use a trait on an
enum and its methods are there.

## Every trait at once

`AsEnumerableEnumTrait` brings every trait except `AsStringSelectableEnumTrait`:

```php
use Phil\Enums\Traits\AsEnumerableEnumTrait;

enum Status: string
{
    use AsEnumerableEnumTrait;

    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}

Status::values();               // ['draft', 'published']
Status::names();                // ['DRAFT', 'PUBLISHED']
Status::options();              // ['DRAFT' => 'draft', 'PUBLISHED' => 'published']
Status::DRAFT();                // 'draft'
Status::DRAFT->is(Status::DRAFT); // true; strict, so is('draft') is false
Status::fromName('PUBLISHED');  // Status::PUBLISHED
Status::isBacked();             // true
```

Prefer the single traits an enum needs: each one adds public methods to the enum's API, and an enum that only
lists its values in an error message needs `AsValuableEnumTrait` and nothing else.

## PHPStan

Include the extension so that PHPStan knows the static case calls and the metadata accessors — see
[PHPStan](phpstan.md).
