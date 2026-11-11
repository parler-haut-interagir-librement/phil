# Testing

## The library's tests

The suite `tests/Unit` reads messages written for it in `tests/Fixtures` — a Postfix notification, a delay and a
success, a full mailbox in base64, an international notification, an abuse report, Exim and qmail bounces written as
text, an automatic reply and a message from a person — and tests each reading, each kind, the rules and their order,
VERP, the authenticators, status codes, and the MIME reader at each of its limits.

From the monorepo root:

```sh
vendor/bin/phpunit packages/bounce-parser/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

The fuzz target `bounce-parser` gives the parser messages nobody wrote, from the seeds of `tests/Fuzz/corpus/`, with
two rules and a `Verp`: whatever it reads, it returns a report and never throws. From the monorepo root:

```sh
composer fuzz -- bounce-parser
```

## Testing code that reads bounces

The parser is deterministic and needs no server: keep the bounces your servers send as `.eml` files, parse them, and
compare the kind, the action and the addresses.

```php
$report = new BounceParser()->parse((string) file_get_contents(__DIR__.'/bounces/mailbox-full.eml'));

self::assertSame(BounceKind::Soft, $report->kind);
self::assertSame(['ada@example.org'], $report->addresses());
```

To test a rule, parse a message that should match it and one that should not, and compare `BounceReport::$rule`.
