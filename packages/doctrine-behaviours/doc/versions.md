# Versions

| Type | Kind | Role |
|---|---|---|
| `Contract\Versionable` | interface | The number, the frozen flag, what a version keeps |
| `VersionableEntity` | trait | The columns `version_number` and `version_frozen` |
| `VersionListener` | listener: `onFlush` | Forks a frozen record as a versioned field changes |
| `Versions` | service | Lists, freezes, goes back |
| `Version\RecordVersion` | entity | A frozen version, in the table `phil_record_version` |
| `Contract\VersionRestoreGuard` | interface | The right to go back to a past version |
| `AllowVersionRestore`, `RefuseVersionRestore` | guards | Everyone, or no one |
| `Exception\UnknownVersion`, `Exception\VersionRestoreDenied`, `Exception\InvalidVersionedState` | exceptions | |

Each entity activates versions by implementing `Versionable`, and says what a version keeps:

```php
#[ORM\Entity]
class Rule implements Versionable
{
    use VersionableEntity;

    public function versionSubjectId(): string { return (string) $this->id; }
    public function versionedState(): array { return ['title' => $this->title, 'body' => $this->body]; }
    public function applyVersionedState(array $state): void { $this->title = $state['title']; $this->body = $state['body']; }
}
```

## The principle

- The record is the version being worked on, numbered from one.
- **A frozen version is never edited.** `Versions::freeze()` writes a snapshot of the record; from then on the next change
  to a versioned field forks the next number, and the snapshot stays what it was.
- A change to anything else, a timestamp, a publication state, forks nothing.
- Which event freezes a version is the application's decision. Publishing is the usual one; a listener on
  `workflow.<name>.entered.published` calls `freeze()`.

| Call | Effect |
|---|---|
| `freeze($record)` | Flushes what is pending, then writes the snapshot, or gives back the one that exists |
| `list($record, $limit)` | The frozen versions, the latest first, 50 by default |
| `get($record, $number)` | One frozen version, or `null` |
| `isFrozen($record, $number)` | Whether that number has a snapshot |
| `restore($record, $number)` | Goes back to a past version, under the guard, and flushes |

`versionedState()` is keyed by mapped fields and associations, the only changes that can fork: a key the entity does not
map is refused by `InvalidVersionedState`. Its values are what JSON holds, and a database may give the keys back in
another order, MySQL for one. A record names its versions by its root table and its identifier, which last through a
rename of the class.

## Going back

`restore()` rewrites no history. The version being worked on is frozen first, so nothing is lost, and the state of the
past version becomes the next number: going back to 1 from 2 leaves 1 and 2 frozen, and the record at 3.

Going back takes a right of its own, separate from the right to edit: `VersionRestoreGuard::check()` runs first and
throws when it is missing. There is no default: `Versions` takes a guard, `RefuseVersionRestore` where no one can be
asked, `AllowVersionRestore` for a command an operator runs.

## Privacy

`created_by` of a version, which names who froze it, is blame: `BlameEraser` reaches it. A snapshot holds what
`versionedState()` gives and stays as long as its row, so that method leaves out anything personal that should not
outlive an erasure request.
