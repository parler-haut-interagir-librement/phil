# phil/access-codes

**Single-use codes in named batches, given once at creation and kept only as keyed hashes, redeemed once.**

phil/access-codes creates batches of codes — download cards sold at a booth, voucher codes printed in a book, keys
handed to backers — and redeems each code once. A batch has a name, a size up to a million, and an optional expiry.
Its codes are drawn by PHP's secure generator from an alphabet without lookalike symbols, Crockford's Base32 by
default, with a check symbol that catches a mistyped symbol before any lookup: `7KQ2-M9XD-H4TB-H`. The length and the
entropy are chosen, never below 40 bits.

The codes in clear exist once: in the `CreatedBatch` that `create()` returns, written once as CSV — each code with its
prefilled redemption link — or taken once as a list for printing. The store keeps the HMAC-SHA-256 of each code under
the application's key, an ordered list of keys where the first hashes and every key finds, so that the key rotates
without losing a batch. Nothing can show a code again.

A redemption spends one attempt of a Symfony rate limiter, reads the code as typed — in lowercase, with hyphens or
spaces, `O` for `0` —, then redeems it in one conditional `UPDATE`: of two redemptions at the same instant, one wins.
The answer says whether the code was redeemed, and otherwise why: unknown, already redeemed, expired, revoked, or too
many attempts. A batch is revoked at once, for a box of cards lost, and counted: redeemed, revoked, unused.

It is a plain PHP library on `phil/url`, `psr/clock` and `symfony/rate-limiter`. The batches live in a store: in
memory for tests and scripts, or in two tables of a Doctrine DBAL connection on PostgreSQL, MySQL, MariaDB or SQLite,
tested on MySQL 8.4 and PostgreSQL 17. It holds no state between calls and runs in a worker. The redemption page and
the forms belong to the application or to a bundle; the library gives the page its prefilled links and the answer to
show.

## Status

| Part | State |
|---|---|
| Alphabets without lookalikes, lookalike letters read as their digit, Luhn mod N check symbol, length and entropy chosen with a floor of 40 bits | Implemented and tested |
| Named batches of up to a million distinct codes from the secure generator, with an expiry | Implemented and tested |
| The codes given once: CSV with prefilled links, or a list; never serialized, cloned or dumped | Implemented and tested |
| HMAC-SHA-256 under an ordered list of up to eight keys: the first hashes, every key finds | Implemented and tested |
| Redemption in one conditional `UPDATE`, outcomes, attempts bounded by a Symfony rate limiter | Implemented and tested |
| Revocation of a batch, counters, redeemer named by a stable identifier | Implemented and tested |
| In-memory store; Doctrine DBAL store, its schema, eight processes racing on MySQL 8.4 and PostgreSQL 17 | Implemented and tested |
| Fuzz target of the code reader; benchmark of a batch of 100,000 codes | Implemented |
| A Symfony bundle: services from configuration, the redemption page and form, a console command that exports a batch | Not written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, building the services. |
| [Codes](doc/codes.md) | `CodeAlphabet`, `CodeFormat`: the alphabet, lookalikes, the check symbol, length and entropy. |
| [Batches](doc/batches.md) | Creating a batch, the one-time CSV and list, prefilled links, revocation, counters. |
| [Redemption](doc/redemption.md) | `redeem()`, attempts, the outcomes, atomicity, the redeemer. |
| [Keys and storage](doc/storage.md) | `CodeHasher` and its ordered keys, the stores, the tables. |
| [Exceptions](doc/exceptions.md) | `AccessCodesException` and its four exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The package's tests, its fuzz target and benchmark, and how to test code that redeems. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/access-codes
```

```php
use Phil\AccessCodes\AccessCodes;
use Phil\AccessCodes\CodeHasher;
use Phil\AccessCodes\InMemoryCodeStore;
use Phil\AccessCodes\RedemptionLinks;
use Phil\Url\Url;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

$codes = new AccessCodes(
    new InMemoryCodeStore(),
    new CodeHasher($_SERVER['ACCESS_CODES_KEY']),
    new RateLimiterFactory(['id' => 'redeem', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '15 minutes'], new InMemoryStorage()),
    new NativeClock(),
);

$created = $codes->create('Album cards, autumn fair', 500);
$created->writeCsv(new SplFileObject('cards.csv', 'x'), new RedemptionLinks(Url::fromString('https://shop.example/redeem')));

$redemption = $codes->redeem('7kq2-m9xd-h4tb-h', 'member-1842', $clientAddress);
$redemption->isRedeemed();   // true, and $redemption->batchId names what it unlocks
```

## Requirements

PHP 8.5 or later with `ext-mbstring`, `phil/url`, `psr/clock` and `symfony/rate-limiter` 8.2 or later;
`doctrine/dbal` 4.5 or later for the database store.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
