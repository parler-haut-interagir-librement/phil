# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network: every check reads its records from `StaticTxtResolver`.
`tests/Support/Keys` builds DKIM keys byte by byte, of an exact size, and `tests/Support/CountingResolver` remembers the
names it was asked, to prove each name is asked once and that the SPF walk stops at its limit. In the monorepo,
Infection holds the package at 100 % of covered mutants.

`tests/Integration/SystemResolverTest` asks the system's resolver for a name that never resolves; it runs only when
`PHIL_TEST_DNS` is set, as it needs a network.

## Testing an application

`StaticTxtResolver` takes the TXT records by name, and the names whose lookup fails:

```php
use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\MailAuthCheck;
use Phil\MailAuthCheck\SenderProfile;

$checker = new MailAuthCheck(new StaticTxtResolver(
    ['example.org' => ['v=spf1 -all'], '_dmarc.example.org' => ['v=DMARC1; p=reject']],
    ['s1._domainkey.example.org'],
));

$report = $checker->check(new SenderProfile('example.org', dkimSelectors: ['s1']));
// dkim.lookup-failed is among $report->failures()
```

Compare findings by their `code`, which is stable, rather than by their message.

## The fuzz target

`tests/Fuzz/records.php` reads text nobody wrote with the SPF, DKIM and DMARC parsers, then analyses the SPF record of a
domain whose every name answers with that text: each parser returns or throws `InvalidRecord`, nothing else escapes,
and the walk never counts more than eleven lookups. Its seeds are in `tests/Fuzz/corpus/mail-auth-records`.

```sh
composer fuzz -- mail-auth-records
```
