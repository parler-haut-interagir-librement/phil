# phil/guardian-consent

**Age brackets per jurisdiction, and parental consent feature by feature for members below the age of consent.**

phil/guardian-consent reads a member's age from their date of birth against the rules of a jurisdiction — the age from
which a person consents for themselves, the age of majority, the time zone in which a birthday begins, the day a
person born on 29 February has one — and keeps only the day they reach the age of consent, never the date of birth.
Quebec's rules are the default: 14 years for the consent, 18 for majority. Any other jurisdiction, such as a member
state applying the GDPR, is configured by the application.

Below the age of consent, every feature the site declares — the messages, the public profile, the classified ads — is
refused until the holder of parental authority grants it. A request is sent by e-mail with a confirmation link: signed
by the application's key, stored only as its keyed hash, used once, and expiring after a configurable delay. The
guardian grants some features and refuses the others, and may withdraw a consent at any time. A request left
unanswered expires and is erased after a retention delay. Every decision is kept as a dated proof: who decided, what,
when, on which version of the text, by which request.

On the day a member reaches the age of consent, the guardian's consents stop applying: the member is told, and
confirms their own choices. Card transactions and identity documents are not used as proof: they collect more than
the purpose needs.

It is a plain PHP library on the PSR clock and event dispatcher. Minors, requests and proofs are kept behind three
ports, with stores in memory; the e-mail, the link to an adult who manages an account, and the access and erasure
requests of the privacy tooling are ports too. Its events carry identifiers only. Its services keep no state between
calls and are safe in a long-running worker.

## Status

| Part | State |
|---|---|
| Calendar dates, whole years completed, 29 February by the jurisdiction's rule | Implemented and tested |
| Jurisdictions: age of consent, majority, time zone; Quebec by default | Implemented and tested |
| Age brackets from a date of birth that is not kept | Implemented and tested |
| Requests to a guardian, feature by feature, refused by default | Implemented and tested |
| Confirmation tokens: signed, hashed, single-use, expiring; key rotation | Implemented, tested and fuzzed |
| Withdrawal by a guardian, or by an adult a link names | Implemented and tested |
| Dated proofs of every decision | Implemented and tested |
| The handover at the age of consent, with a notice and the member's own confirmation | Implemented and tested |
| The daily sweep: unanswered requests erased, handover notices sent | Implemented and tested |
| Access and erasure, read as a member and as a guardian | Implemented and tested |
| Stores in memory | Implemented and tested |
| Stores in a database, the mailer, the pages and forms | Not part of the library: the application, or a bundle on it, provides them |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the services to build. |
| [Concepts](doc/concepts.md) | Minors, features, requests, tokens, proofs, statuses, the handover and the sweep. |
| [Jurisdictions](doc/jurisdictions.md) | The rules of a jurisdiction, the default one, and how to configure others. |
| [Security and privacy](doc/security.md) | What is kept, the tokens, access and erasure, the bounds on every input. |
| [Extension points](doc/extension-points.md) | The stores, the notifier, the guardian link, the events, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests, and how to test code that uses the consents. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/guardian-consent
```

```php
use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\Feature;
use Phil\GuardianConsent\Guardian;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\NoticeVersion;

$member = new MemberId($user->id);

$consent->register($member, CalendarDate::fromString('2013-05-02'));  // the date of birth is read, not kept

$consent->allows($member, new Feature('messages'));                    // false: refused until a guardian grants it

$consent->request(
    $member,
    new Guardian(new GuardianId($parentAccountId), 'parent@example.org'),
    [new Feature('messages'), new Feature('public-profile')],
    new NoticeVersion('2026-11'),
);                                                                      // the notifier sends the link

// On the page the link opens, then on its form:
$request = $consent->open($token);
$consent->decide($token, [new Feature('messages')]);                    // messages granted, public profile refused
```

## Requirements

PHP 8.5 or later with `sodium`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
