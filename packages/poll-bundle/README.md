# phil/poll-bundle

**Polls and their ballots for Symfony applications: single, multiple or ranked, anonymous or not, counted on demand,
with the single transferable vote for several seats and a voting interface to show them.**

phil/poll-bundle puts the voting engine of `phil/poll` behind the services of a Symfony application. A poll has a
question, two to a hundred labelled options, the rules of its ballots, the method its ballots are counted by, and,
when the application wants it, an opening and a closing. People cast a ballot, change it, withdraw it while the poll
is open; the people who manage it close it, reschedule it or delete it; the results are counted when they are asked
for, and shown as the poll's visibility says — at any time, after voting, after closing, or to its managers only.

A poll that fills several seats — a board, a jury — is counted by the single transferable vote, by Meek's method, in
exact numbers: every round is published, with its quota, the votes and keep factor of each option and the value
exhausted, so that anyone holding the ballots replays the count to the last decimal.

A poll may be anonymous: its ballots are then kept apart from their voters. The bundle records that a voter took
part, so that nobody votes twice, but nothing ties a ballot to them: the ballot has a random identifier, no time, and
only the hash of the receipt its voter was given, with which they may change it while the poll is open.

It is for any Symfony application where people vote, and for the bundles that build on a vote: a poll of
availabilities that finds a date, a questionnaire, a box of ideas voted by members, the general assembly of an
association. A poll may belong to one of the application's resources — an idea, a meeting — named as a reference of
`phil/resource` (`idea:42`), and so may its voters (`user:7`). The application says who may create a poll, vote and
manage it; it may add counting methods of its own beside the four of the library. The bundles built on it show a
poll with the same views and templates: the form of a ballot and the results, in the markup of the PHIL UX kit's
recipes, working without JavaScript, and a DOM event when the results of a closed poll are revealed, on which the
kit's celebration plays.

It is a Symfony bundle on `phil/poll`, `phil/resource` and Doctrine ORM: polls, ballots and participations live in
three tables, or in memory for tests. The services keep no state between requests and are safe in a long-running
worker; every action dispatches an event that names the poll and nothing else. A count can be kept in a cache pool
until the next ballot, when the application names one.

## Status

| Part | State |
|---|---|
| `Poll`, `Vote`, `Participation`: polls, named and anonymous ballots, the participations of anonymous polls | Implemented and tested |
| `Polls`: create, cast, change, change by receipt, withdraw, close, reschedule, delete, count, with the policy asked | Implemented and tested |
| Results shown as each poll's visibility says | Implemented and tested |
| Counting methods of the application, autoconfigured beside the library's | Implemented and tested |
| The single transferable vote by Meek's method, in exact numbers, with its rounds published | Implemented, tested, fuzzed and benchmarked |
| Counts kept in a cache pool until the next ballot, behind an option | Implemented and tested |
| The voting interface: views, templates in the markup of the kit's recipes, the reading of a posted form | Implemented and tested |
| The DOM event of revealed results, for the kit's celebration | Implemented and tested |
| Doctrine and in-memory stores, the in-memory ones emptied between requests | Implemented and tested, on SQLite |
| Events naming the poll: created, ballot cast, changed, withdrawn, closed, deleted | Implemented and tested |
| Routes and controllers for voting | Not part of the bundle: the bundles built on it and the application keep their own |
| Grades, majority judgment; scales, word clouds, recommendation indexes and brainstorms, not scored | Not started: built when a site asks for them |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the tables, a first poll. |
| [Configuration](doc/configuration.md) | Every option and the services registered. |
| [Polls and ballots](doc/polls-and-ballots.md) | Creating a poll, its dates, casting, changing and withdrawing a ballot, managing a poll. |
| [Anonymity](doc/anonymity.md) | What an anonymous poll keeps, the receipt, and what anonymity does not cover. |
| [Results](doc/results.md) | Counting, the visibility of the results, the cost of a count. |
| [Single transferable vote](doc/single-transferable-vote.md) | Polls with seats, Meek's method, the published rounds, exact numbers. |
| [The voting interface](doc/voting-interface.md) | The views, the templates, the posted form, celebrating revealed results, accessibility. |
| [Events](doc/events.md) | The events dispatched, and what they carry. |
| [Extension points](doc/extension-points.md) | The policy, counting methods, the stores and the events. |
| [Exceptions](doc/exceptions.md) | The bundle's exceptions, and the library's it lets through. |
| [Testing](doc/testing.md) | The bundle's tests, the mutation bar, and how to test code that uses polls. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require phil/poll-bundle
```

```php
use Phil\Poll\BallotKind;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;

final readonly class DrinksController
{
    public function __construct(
        private Polls $polls,
    ) {
    }

    public function create(ResourceReference $member): string
    {
        $poll = $this->polls->create(new PollDraft(
            'What do we drink at the next meeting?',
            [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee'), PollOption::of('cocoa', 'Cocoa')],
            BallotKind::Ranked,
            anonymous: true,
            results: ResultsVisibility::AfterClosing,
        ), $member);

        return $poll->id;
    }

    public function vote(string $poll, ResourceReference $member, array $choices): ?string
    {
        return $this->polls->cast($poll, $member, $choices)->receipt;   // shown once to the voter
    }
}
```

## Requirements

PHP 8.5 or later with `mbstring` and `sodium`, Symfony 8.2, Doctrine ORM 3.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
