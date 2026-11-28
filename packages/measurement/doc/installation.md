# Installation

```sh
composer require phil/measurement
```

The package needs PHP 8.5 or later, with the `intl` extension, whose ICU gives the number symbols, the plural rules
and the list patterns of each locale, and the `mbstring` extension. It has no other dependency.

The CLDR data it reads — unit preferences and unit patterns — ships with it under `data/cldr/`, at the release named
in `data/cldr/VERSION`: nothing is downloaded at run time. [Channels, preferences and formatting](cldr.md#the-cldr-data)
says how the data is imported again for a new CLDR release.

With Symfony, `phil/measurement-bundle` registers the services, a form type, validation constraints, Twig filters and
a Doctrine column type.
