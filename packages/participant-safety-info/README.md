# phil/participant-safety-info

**Keeps a participant's emergency contacts, allergies and needs for an event encrypted, readable only by its
responsible people, every reading journaled.**

phil/participant-safety-info holds what a participant tells the organizers of an event so that it goes safely: who to
call in an emergency, allergies and what to do about them, needs during the event. The participant submits, reads,
copies to another event and erases their own. The responsible people of the event read it during its access window,
from a few days before the event until it ends; a member of the team reads it only by breaking the glass, with a
written reason the responsible people are told about. Every reading by someone else is written to a journal before
the information is opened, and the participant sees who read it. Once the window has closed, the information is
purged.

The information is sealed by the envelope encryption of Symfony's KeyManagement component: a fresh data key per
record, wrapped by a master key the KMS holds, and the event and participant authenticated with the text, so that a
record moved to another row does not open. The store never sees a plaintext.

It is a plain PHP library. The store, the roles, the windows and the journal are ports the application implements.
It keeps only what serves safety during the event: no diagnosis field, no list of medications.

## Status

| Part | State |
|---|---|
| The safety information: up to three emergency contacts, allergies, needs, notes, bounded | Implemented and tested |
| The participant's own: submit, read, copy to another event, erase, see who read it | Implemented and tested |
| Reading by the responsible people during the access window, journaled first | Implemented and tested |
| Breaking the glass by the team, with a reason, journaled and announced | Implemented and tested |
| Sealing by Symfony's KeyManagement, bound to the event and participant | Implemented and tested |
| The purge once the window has closed | Implemented and tested |
| The store, the journal, the roles and the windows over a database | Ports only; in-memory implementations for tests |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and building the vault. |
| [Access rules](doc/access-rules.md) | Who reads what, when, and what each path journals and announces. |
| [Encryption](doc/encryption.md) | How a record is sealed, what binds it to its row, the master key and its rotation. |
| [Extension points](doc/extension-points.md) | The ports an application implements, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/participant-safety-info
```

```php
use Phil\ParticipantSafetyInfo\EmergencyContact;
use Phil\ParticipantSafetyInfo\SafetyInfo;

$vault->submit('event-42', 'member-7', new SafetyInfo(
    [new EmergencyContact('Alex Tremblay', '+1 514 555-0199', 'sibling')],
    allergies: 'Peanuts: epinephrine pen in the left pocket.',
));

$info = $vault->read('event-42', 'member-7', 'organizer-3'); // a responsible person, during the window
```

## Requirements

PHP 8.5 or later with `mbstring` and `sodium`; `symfony/key-management` 8.2, `psr/clock` and
`psr/event-dispatcher`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
