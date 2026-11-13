# phil/access-links

**Invitations by personal link: one opaque token per recipient, an answer editable until the closure, reminders to
those who have not answered.**

phil/access-links invites recipients — to a poll, an event, a scheduling vote, a survey — each by a personal link.
The link carries a token of 256 bits from PHP's secure generator and nothing else: no email address, no name, no
campaign. The store keeps the token's HMAC-SHA-256 under the application's key, an ordered list of keys where the first
hashes and every key finds, so that the key rotates without breaking the links already sent.

An invitation has its moments, read from a PSR-20 clock and compared by order: the link opens from a moment, the answer
changes until the closure, and the link still opens, to show the answer, until it expires. Opening a link changes
nothing — the preview of a mail client spends nothing —; answering records the answer, and answering again changes it,
until the closure. A recipient is named by a stable identifier of the application, never by an email address: the
library knows no address and sends nothing.

Reminders follow a schedule — three days, then seven days after the invitation — and reach only those who have not
answered. A dispatcher sends one Symfony Messenger message per due reminder, carrying the invitation's identifier only;
the handler claims the reminder in one conditional `UPDATE`, so that two workers never send it twice, issues a fresh
link, and hands it to the application's sender, which finds how to reach the recipient.

It is a plain PHP library on `phil/url` and `psr/clock`; Symfony Messenger is needed for the dispatcher and the handler
only. The invitations live in a store: in memory for tests and scripts, or in two tables of a Doctrine DBAL connection
on PostgreSQL, MySQL, MariaDB or SQLite, tested on MySQL 8.4 and PostgreSQL 17. It holds no state between calls and
runs in a worker. The invitation page and its form belong to the application or to a bundle.

## Status

| Part | State |
|---|---|
| Tokens of 32 secure bytes in URL-safe Base64, read strictly | Implemented and tested |
| HMAC-SHA-256 under an ordered list of up to eight keys: the first hashes, every key finds | Implemented and tested |
| Invitations per campaign and stable recipient, unique; moments of opening, closure and expiry | Implemented and tested |
| Opening without side effect, answers editable until the closure, revocation, counters | Implemented and tested |
| Links issued again, up to sixteen per invitation, on `phil/url` | Implemented and tested |
| Reminder schedules, reminders to non-respondents only, claimed atomically, through Messenger messages of identifiers | Implemented and tested |
| In-memory store; Doctrine DBAL store and its schema, tested on MySQL 8.4 and PostgreSQL 17 | Implemented and tested |
| Fuzz target of the token reader | Implemented |
| A Symfony bundle: services from configuration, the invitation page and form, a scheduled dispatcher | Not written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, building the services. |
| [Invitations](doc/invitations.md) | Inviting, the moments, opening, answering, revoking, counting. |
| [Links](doc/links.md) | The token, its keyed hash and key rotation, the link's address, links issued again. |
| [Reminders](doc/reminders.md) | The schedule, the dispatcher, the message, the handler, the sender. |
| [Storage](doc/storage.md) | The stores, the tables, the atomic writes. |
| [Exceptions](doc/exceptions.md) | `AccessLinksException` and its six exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The package's tests and fuzz target, and how to test code that invites. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/access-links
```

```php
use Phil\AccessLinks\AccessLinks;
use Phil\AccessLinks\InMemoryInvitationStore;
use Phil\AccessLinks\LinkFormat;
use Phil\AccessLinks\ReminderSchedule;
use Phil\AccessLinks\TokenHasher;
use Phil\Url\Url;
use Symfony\Component\Clock\NativeClock;

$links = new AccessLinks(
    new InMemoryInvitationStore(),
    new TokenHasher($_SERVER['ACCESS_LINKS_KEY']),
    new LinkFormat(Url::fromString('https://events.example/rsvp')),
    new NativeClock(),
    new ReminderSchedule(['P3D', 'P7D']),
);

$issued = $links->invite('dinner-2026', 'member-1842', new DateTimeImmutable('2026-12-01 12:00'));
$issued->url;   // https://events.example/rsvp?token=… — send it to member 1842

$access = $links->open($token);              // the invitation, and whether the answer can still change
$links->respond($token, 'yes, with one guest');
```

## Requirements

PHP 8.5 or later with `ext-mbstring` and `ext-sodium`, `phil/url` and `psr/clock`; `symfony/messenger` 8.2 or later
for the reminders as messages, `doctrine/dbal` 4.5 or later for the database store.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
