# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `mbstring` and `sodium` | Labels counted in characters; receipts encoded without timing leaks. |
| `phil/poll` | `^0.1` | The rules, the ballots and the counting methods. |
| `phil/resource` | `^0.1` | Voters, creators and subjects named as references (`user:7`, `idea:42`). |
| `doctrine/orm`, `doctrine/dbal` | `^3.7`, `^4.5` | The tables of the polls, ballots and participations. |
| `symfony/config`, `symfony/dependency-injection`, `symfony/uid` | `^8.2` | The bundle, its configuration and the random identifiers. |
| `symfony/cache-contracts`, `symfony/service-contracts` | `^3.6` | The cache of the counts, when one is named; the stores in memory emptied between requests. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | The time of a ballot and of the dates; the events. |

DoctrineBundle keeps the tables in the application's database; without it, the `memory` store serves tests. TwigBundle,
with the translator, draws the [voting interface](voting-interface.md), with Symfony UX Twig Component 3.7 or later and
the recipes of the kit below.

## Enabling the bundle

```sh
composer require phil/poll-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\PollBundle\PhilPollBundle::class => ['all' => true],
];
```

The bundle needs a `clock` service, which FrameworkBundle registers.

## The recipes of the kit

The templates draw their parts with recipes of the PHIL UX kit, installed in the application by the Symfony UX
Toolkit; nothing is rendered from `vendor/`:

```sh
for recipe in field radio-group checkbox native-select button progress; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

A site on Tailwind installs the same six recipes from the Shadcn kit of the Toolkit instead, with `label`, which its
`Field:Label` calls, and Symfony UX Icons, which its checkbox and select draw; the templates give the recipes only
what both kits read the same way. They are found under the components subdirectory of the `ux_toolkit`
configuration; in an environment where the Toolkit is not enabled, as production often is, repeat it in
`kit_subdirectory`.

## The tables

The bundle prepends its mapping to DoctrineBundle: `phil_poll`, `phil_poll_vote` and `phil_poll_participation`. The
table `phil_poll` holds the seats of each poll and the revision of its ballots.
Generate the migration as for the application's own entities:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

### Collation of the keys

**Keys compare byte by byte**, as PHP compares them. A voter casts one ballot per poll and is found again by their
reference: a ballot of `user:Ann` is never one of `user:ann`, so that neither is refused as having voted already, nor
shown the other's ballot; and the polls of the subject `post:Abc` are never listed for `post:abc`.
`BinaryKeysListener` gives these fields the binary collation when the ORM loads their metadata on MySQL or MariaDB;
the mapping itself stays the same on every platform.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_poll` | `id`, `subject` |
| `phil_poll_vote` | `id`, `poll`, `voter`, `receipt` |
| `phil_poll_participation` | `poll`, `voter` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.3

The tables of a 0.3 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

## A first poll

```php
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;

$poll = $polls->create(
    new PollDraft('Saturday or Sunday?', [PollOption::of('sat', 'Saturday'), PollOption::of('sun', 'Sunday')]),
    ResourceReference::of('user', '7'),
);

$polls->cast($poll->id, ResourceReference::of('user', '8'), ['sun']);

$polls->results($poll->id)->winners();   // ['sun']
```

Who may create, vote and manage is the application's: see [Extension points](extension-points.md).
