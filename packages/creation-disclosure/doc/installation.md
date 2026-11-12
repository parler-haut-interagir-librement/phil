# Installation

```sh
composer require phil/creation-disclosure
```

The package needs PHP 8.5 or later and `ext-mbstring`. It has no service to configure: every type is a value, built
where it is needed. A Symfony application stores a disclosure in a column of type `json` with `toArray()` and reads it
back with `fromArray()`; see [Declaring](declaring.md#storing-a-disclosure).
