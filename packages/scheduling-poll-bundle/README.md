# phil/scheduling-poll-bundle

**Find a date that suits everyone: the organizer proposes dates, each person answers yes, if need be or no by their own
link, without an account, and the best date is chosen and sent to all as an iCalendar file.**

phil/scheduling-poll-bundle is a Symfony bundle for polls of availabilities. The organizer proposes two to a hundred
dates, a start and an end each, shown in a time zone of the poll, and sets a deadline. Each respondent joins from the
poll's page with a name alone — an address only if they want the date chosen, an account only if they have one — or is
invited by the organizer, and gets a personal link of `phil/access-links`: a token of 256 bits kept only as its keyed
hash. With it, they answer each date yes, if need be or no, read the dates in their own time zone, and change their
answers until the poll closes.

The organizer closes the poll on the date they choose, or a scheduled task closes it at the deadline on the best date:
the most yes answers, then the most if-need-be answers, then the earliest. The answers are counted by the voting
engine of `phil/poll`. The closing is one conditional write, so that a closing by hand racing the deadline closes the
poll once; the closing that wins queues one Messenger message of identifiers, and the worker sends every respondent the
date with its iCalendar file, written by `phil/calendar-sync`, each notice claimed once. Whether the organizer sees the
answers before the closing, and who else sees them, are the poll's choice. The names and addresses are erased a delay
after the closing; the export and the erasure of a person go through the contracts of `phil/privacy-bundle`.

The pages of a respondent — the poll's page and their own — come with the bundle: a table of the dates with three radio
buttons a row, accessible, usable with the keyboard and without JavaScript, drawn with the recipes of the PHIL UX kit.
Two Stimulus controllers add to them: the dates written in the time zone the respondent picks, and the DOM event
`scheduling-poll:chosen`, which plays the kit's celebration once the date is chosen.

## Status

| Part | State |
|---|---|
| Polls of two to a hundred dates, in UTC with a time zone to show them, a deadline, a description and a place | Implemented and tested |
| Respondents by name alone, an address and an account optional, invited or joining, a hundred characters of name | Implemented and tested |
| Personal links of `phil/access-links`: opened without side effect, issued again, revoked | Implemented and tested |
| Answers yes, if need be or no, written while the poll is open, each respondent's time zone | Implemented and tested |
| The count on `phil/poll`, its tie-break and places; the date chosen by the organizer or at the deadline | Implemented and tested |
| One closing, one announcement: a conditional write, then Messenger messages of identifiers, each notice claimed once | Implemented and tested |
| The mail of the date chosen with its iCalendar file, in the respondent's language and time zone | Implemented and tested |
| Who sees the answers before the closing: the organizer when the poll says so, the others by `ResultsVisibility` | Implemented and tested |
| The schedule `phil_scheduling_poll`, stateful and locked: deadlines closed, names and addresses erased | Implemented and tested |
| Export and erasure through `phil/privacy-bundle` | Implemented and tested |
| Doctrine mapping and stores, a store in memory; the tables of the links added to the generated schema | Implemented and tested |
| The pages of a respondent and the table of the answers, with the PHIL UX kit; two Stimulus controllers | Implemented and tested |
| A grid of free time painted by each respondent, instead of fixed dates | Not started |
| A Flex recipe | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the tables, the routes, the recipes of the kit. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Polls and answers](doc/polls-and-answers.md) | Creating a poll, respondents, links, answers, time zones, who sees what. |
| [Closing and the date chosen](doc/closing.md) | The organizer's choice, the deadline, the tie-break, the announcement, the mail and its iCalendar file. |
| [Pages and templates](doc/pages.md) | The routes, the templates, their blocks, the Stimulus controllers, the celebration. |
| [Privacy](doc/privacy.md) | What is kept, the erasure after the closing, the export and the erasure of a person. |
| [Extension points](doc/extension-points.md) | The services to replace, the events to listen to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test code that uses it. |
| [Reference](doc/reference.md) | Every public type of the bundle, with its role. |

## Quick start

```php
// config/bundles.php
Phil\SchedulingPollBundle\PhilSchedulingPollBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_scheduling_poll.yaml
phil_scheduling_poll:
    links:
        keys: ['%env(SCHEDULING_POLL_LINK_KEY)%']
        page: 'https://example.org/scheduling-poll/answer'
```

```yaml
# config/routes/phil_scheduling_poll.yaml
phil_scheduling_poll:
    resource: '@PhilSchedulingPollBundle/config/routes.php'
    prefix: /scheduling-poll
```

```php
use Phil\SchedulingPollBundle\Application\ProposedSlot;
use Phil\SchedulingPollBundle\Application\SchedulingPollDraft;

$zone = new DateTimeZone('America/Toronto');
$poll = $polls->create(new SchedulingPollDraft('Game night', [
    new ProposedSlot(new DateTimeImmutable('2026-12-04 19:00', $zone), new DateTimeImmutable('2026-12-04 23:00', $zone)),
    new ProposedSlot(new DateTimeImmutable('2026-12-05 19:00', $zone), new DateTimeImmutable('2026-12-05 23:00', $zone)),
], 'America/Toronto', new DateTimeImmutable('2026-11-28 12:00', $zone)), $organizer);

// Share https://example.org/scheduling-poll/{poll}/join, or invite by name:
$link = $polls->invite($poll->id, $organizer, 'Bob', 'bob@example.org');
```

## Requirements

PHP 8.5 or later with `ext-intl` and `ext-mbstring`, Symfony 8.2 or later, Doctrine ORM 3.7 and DBAL 4.5, Symfony Messenger, Mailer and
Scheduler; TwigBundle, `twig/intl-extra` and the recipes of the PHIL UX kit for the pages.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
