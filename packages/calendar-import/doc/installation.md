# Installation

```sh
composer require phil/calendar-import
```

The package needs PHP 8.5 or later, with the `intl` and `mbstring` extensions. It reads feeds through
`phil/safe-http`, which takes any Symfony HTTP client; with none given, it creates one.
