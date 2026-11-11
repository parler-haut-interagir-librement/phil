# phil/poll

**A voting engine: ballots checked against the rules of their poll, and counted into standings.**

phil/poll is the core every vote of an application goes through: a question with one answer, a choice of several
options, a ranking of preferences. The rules of a poll say what a ballot may hold — one option, between a minimum
and a maximum of options, or options ranked — and whether a blank ballot is an abstention; a ballot is checked
against them as a form or a request gives it. A counting method turns the ballots into standings: the count of
choices for single and multiple ballots, the instant runoff, the Borda count and the Schulze method for ranked ones.
Options tied share their place, an instant runoff keeps its rounds, and abstentions are counted apart.

It is for any PHP application where people vote: a club choosing the date of its next meeting, a community ranking
the ideas of its members, an association electing its board, a site asking its readers a question. Who may vote,
whether ballots are anonymous and where they are kept belong to the application, or to `phil/poll-bundle` in Symfony.

It is a plain PHP library with no requirement but PHP. A count reads the ballots once, from
any iterable, so a store streams them from its table; the ranked methods group identical rankings, so their memory
grows with the distinct rankings rather than with the ballots. The methods keep no state between two counts and are
safe to share across the requests of a long-running worker.

## Status

| Part | State |
|---|---|
| The rules of a poll: single, multiple and ranked ballots, bounds, abstention, 2 to 100 options | Implemented and tested |
| Ballots checked against the rules from the values of a form or a request | Implemented and tested |
| The count of choices: plurality and approval | Implemented and tested |
| The instant runoff, its rounds, its exhausted ballots and its tie-breaks | Implemented and tested |
| The Borda count and the Schulze method | Implemented and tested |
| `CountingMethods`, the methods of an application by name, and the port of a method of its own | Implemented and tested |
| Polls, ballots and their anonymity in Symfony and Doctrine | Implemented in `phil/poll-bundle` |
| The single transferable vote of an election with several seats, score and majority judgment ballots | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, a first count. |
| [Rules and ballots](doc/rules-and-ballots.md) | The kinds of ballot, the options, the bounds, the abstention, the checks. |
| [Counting](doc/counting.md) | The four methods, their scores, places, rounds and tie-breaks, and their cost. |
| [Extension points](doc/extension-points.md) | A counting method of the application. |
| [Exceptions](doc/exceptions.md) | `PollException` and its three exceptions. |
| [Testing](doc/testing.md) | The library's tests, mutation bar and benchmark, and how to test a count. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/poll
```

```php
use Phil\Poll\CountingMethods;
use Phil\Poll\PollRules;

$rules = PollRules::ranked(['tea', 'coffee', 'cocoa'], abstention: true);

$ballots = [
    $rules->ballot(['coffee', 'tea']),
    $rules->ballot(['tea']),
    $rules->ballot(['tea', 'coffee']),
    $rules->ballot(['cocoa', 'tea']),
    $rules->ballot([]),               // an abstention
];

$result = CountingMethods::defaults()->count('instant_runoff', $rules, $ballots);

$result->winners();                   // ['tea']
$result->standings[0]->place;         // 1
\count($result->rounds);              // 2
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
