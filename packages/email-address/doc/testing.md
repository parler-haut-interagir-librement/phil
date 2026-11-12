# Testing

## The library's tests

The suite `tests/Unit` tests the syntax on every reason of refusal and at each length bound — counted as written and
in ASCII —, international domains, the three forms of the policy; the domain lists with their subdomains; the top-level
domains and their importer on the release line, comments, duplicates, A-labels, a bad line and a directory that cannot
be written; the importer of disposable domains likewise, and the lines of `data/VERSION` each import keeps; the resolver on each answer of the DNS
through a closure; the suggester on slips, ties, bounds and the distance itself, checked against the whole table on
random pairs; the validator on the order of its steps; and the constraint through
Symfony's `ConstraintValidatorTestCase`, on each problem, message, code and option, and inside a Validator built
without a container.

From the monorepo root:

```sh
vendor/bin/phpunit packages/email-address/tests
```

The library is held to 100 % covered mutation score by Infection; the early exits and the filter of the distance,
which change only its cost, are named in `phil-dev.php` with their reasons.

## Testing code that validates addresses

Give the validator a `Dns\StaticResolver`, so that no test asks the DNS:

```php
$validator = new EmailValidator(resolver: new StaticResolver(
    ['example.com' => MailDomain::Mx],
    otherwise: MailDomain::NoRecord,
));

self::assertTrue($validator->validate('ada@example.com')->isValid());
self::assertSame([Problem::NoMailServer], $validator->validate('ada@example.org')->problems);
```

A test of disposable domains gives its own list through `DisposableDomains::of()` rather than relying on the
bundled one, whose content changes with each import; a test of top-level domains gives its own through
`TopLevelDomains::of()`, and a test of the constraint passes both to `DeliverableEmailAddressValidator`.
