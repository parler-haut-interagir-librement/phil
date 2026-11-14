# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test tests/js
```

The unit suite, under `tests/Unit`, runs without a kernel or a network: the DBAL repository on SQLite in memory, the
form on the real form component, the pages on Twig with templates in memory, the others on memory and recording
fixtures. The functional suite, under `tests/Functional`, boots an application with FrameworkBundle and TwigBundle,
renders the preferences in French, saves a visitor's display preferences in their cookie and reads them back on the
page's root element, saves a user's, and refuses a form from another origin through the real stateless CSRF
protection. A second application of the functional suite adds SecurityBundle, its users in memory signing in by HTTP
Basic: the site's settings refused to a visitor and to a member, each setting shown to its role only, a field of
another role refused, the preview that saves nothing, the preferences saved under the user Symfony Security names,
and the migrations run from the console once. Each of its requests goes through the kernel as a worker serves it,
terminated, then the services reset. The Node tests cover the Stimulus controller.

In the monorepo, the tests run on PHP 8.5 and 8.6, and Infection holds the package at 100 % of covered mutants.

## The benchmarks

`tests/Benchmark/SettingsBench.php` measures what every page does: the attributes of the root element for a user, on
a new request — the values read from a table of a thousand users — and within one, the same for a visitor through
their cookie, and the cookie read on its own. On PHP 8.5 and an Apple M1 Pro, the root element's attributes take
about 17 µs on a new request, 5 µs within one, and 4 µs for a visitor; the cookie is read in 5 µs.

```sh
composer bench -- --filter=SettingsBench
```

## Fuzzing

The reader of the preference cookie is fuzzed: whatever it reads, it never throws, returns only values its settings
accept, and what it returns writes and reads back to the same values.

```sh
composer fuzz -- cookie --budget=60
```
