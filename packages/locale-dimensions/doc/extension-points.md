# Extension points

The library is plain PHP and declares no service and no interface. The package is at `0.x`: its types are stable only
from its 1.0 release; until then, a minor version may change them, and its CHANGELOG says how.

## Declaring the locales

A bundle reads the locales from its configuration and builds them once, at the construction of the container, so that
a malformed declaration fails there and not at the first request:

```php
$services->set(DeclaredLocales::class)
    ->factory([DeclaredLocales::class, 'fromArray'])
    ->args([$config['locales'], $config['default_fallbacks']]);
```

```yaml
locales:
    fr_CA: [fr, en]
    fr: [en]
    en_CA: ~        # the default chain: en_CA → en
    en: []
default_fallbacks: [en]
```

A locale written `~` gets the default chain, derived as Symfony's translator derives it and cut to the served
locales; `default_fallbacks` is the list the translator's `fallbacks` option holds, when the application wants both
to agree.

Keep the keys as they are written: the default normalization of Symfony's configuration would read `fr-CA` as `fr_CA`,
where the library refuses it.

## Walking a chain

A reader asks the chain of the requested locale, then gives `resolve()` the locales that have something to serve —
the rows of a page, the translations of a record —, read in one query for the locales of the chain:

```php
$chain = $locales->chainFor($requested);
$available = $repository->localesOf($record, $chain->locales);
$served = $chain->resolve($available) ?? throw new NotFoundHttpException();
```

A locale typed by a visitor is read with `Locale::tryFromString()`, whose `null`, like the refusal of `chainFor()` for
a locale that is not served, is the place to answer with a 404.
