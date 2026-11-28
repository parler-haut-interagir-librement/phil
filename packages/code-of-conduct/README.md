# phil/code-of-conduct

**Publishes a versioned code of conduct, records who accepted which version, and routes encrypted reports to
responsible people not named in them.**

phil/code-of-conduct gives each scope — a community, an event, a space — a code of conduct published in numbered
versions. A member accepts the version in force; a major version asks every member to accept again, a minor one does
not. The application asks where a member stands before they take part — write, register, post — and never holds
reading back.

Members file reports under the code, named or anonymous, and follow where their report stands. The responsible people
of the scope read the report, write notes, review it, resolve it with an outcome or dismiss it. A responsible person a
report names never sees it, and a responsible person may step aside; a report no responsible person could receive is
refused, so the member reaches the contact outside the site. The text of a report and its notes are sealed by the
envelope encryption of Symfony's KeyManagement component before they are stored.

It is a plain PHP library. The stores and the list of responsible people are ports the application implements; the
outcome of a report is applied by the application's moderation, from an event that carries identifiers only.

## Status

| Part | State |
|---|---|
| Versions of a code, major or minor, numbered, hashed | Implemented and tested |
| Acceptance of the version in force, status of a member | Implemented and tested |
| Reports, named or anonymous, sealed, with a receipt for the reporter | Implemented and tested |
| Handling by the responsible people: queue, reading, notes, review, outcome, dismissal, stepping aside | Implemented and tested |
| Sealing by Symfony's KeyManagement, bound to the report and the note | Implemented and tested |
| The stores and the list of responsible people over a database | Ports only; in-memory implementations for tests |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and building the services. |
| [Versions and acceptance](doc/versions-and-acceptance.md) | Publishing, major and minor versions, accepting, the status of a member. |
| [Reports](doc/reports.md) | Filing, the receipt, who handles a report, its statuses and outcomes, the sealing of its texts. |
| [Extension points](doc/extension-points.md) | The ports an application implements, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/code-of-conduct
```

```php
use Phil\CodeOfConduct\ChangeKind;

$code->publish('community-1', 'Code of conduct', $text);
$code->accept('community-1', 'member-7', 1);

if (!$code->status('community-1', 'member-8')->mayTakePart()) {
    // Show the code and ask member-8 to accept it before they post.
}

$report = $reports->file('community-1', 'member-7', ['member-9'], 'What happened, when, where.');
$view = $reports->read($report->id, 'moderator-2'); // a responsible person not named in the report
```

## Requirements

PHP 8.5 or later with `mbstring` and `sodium`; `symfony/key-management` and `symfony/uid` 8.2,
`psr/clock` and `psr/event-dispatcher`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
