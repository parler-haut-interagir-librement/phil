# Installation

```sh
composer require phil/number-words
```

The package needs PHP 8.5 or later with `ext-intl`, the extension that brings ICU to PHP. Without it, building a
`NumberWords` throws `IntlUnavailable`: the library has no spelling rules of its own to fall back on.

The words and the rule sets come from the ICU data of the extension, and change from one ICU version to the next: the
gendered and plural ordinals of German, for one, are in recent data only, and a language without gendered rule sets
gives one form for every gender. The tests of the package expect the words that ICU 76 and later give alike. No configuration and no service are needed; in a Symfony application, a `NumberWords` per
locale can be built where it is used or registered as a service with its locale as an argument.
