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
with the translator, draws the [voting interface](voting-interface.md); `phil/ux-css` styles it.

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

## The tables

The bundle prepends its mapping to DoctrineBundle: `phil_poll`, `phil_poll_vote` and `phil_poll_participation`. The
table `phil_poll` holds the seats of each poll and the revision of its ballots.
Generate the migration as for the application's own entities:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

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
