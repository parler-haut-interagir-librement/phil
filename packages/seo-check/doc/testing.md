# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. Each rule has a compliant case, a
faulty case and its limits; `SeoCheckerTest` checks a whole page, compliant and faulty; the canonical address is
checked across two paths to a home page, the indexing directives across channels, `hreflang` with translations that
do not list the page back. The classes of `Bridge\` run on test pages with phil/structured-data, phil/social-cards and
phil/link-checker, installed in development; `LinkCheckerStatuses` runs on a `LinkChecker` whose probe answers from a
script, `ScriptedLinkProbe`, without a request. In the monorepo, Infection holds the package at 100 % of covered mutants.

`tests/Support` holds the doubles of the ports, `MemoryRenderedPages` and `MemoryLinkStatuses`, and the test pages of
`Pages`; an application's tests may copy them.

## Fuzzing

The fuzz target `seo-check-page`, `tests/Fuzz/page.php`, reads pages nobody wrote with the markup reader and the
default rules: whatever the page holds, the check returns a report that encodes as JSON, and never throws nor warns.
In the monorepo:

```sh
composer fuzz -- seo-check-page
```

Its seeds are in `tests/Fuzz/corpus/seo-check-page`.

## Benchmark

`tests/Benchmark/SeoCheckBench.php` checks a long article — some 600 links, 100 images, 300 headings and the
translations of ten languages, at its address and at a second path — with every default rule: the time must stay in
proportion to the size of the page.
