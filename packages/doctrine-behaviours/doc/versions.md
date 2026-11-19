# Versions

| Type | Kind | Role |
|---|---|---|
| `Contract\Versionable` | interface | The number, the frozen flag, what a version keeps |
| `VersionableEntity` | trait | The columns `version_number` and `version_frozen` |
| `VersionListener` | listener: `onFlush` | Forks a frozen record as a versioned field changes |
| `Versions` | service | Lists, freezes, goes back |
| `Version\RecordVersion` | entity | A frozen version, in the table `phil_record_version`, its `frozen_at` and `redacted_at` of the type `datetime_utc_immutable` |
| `Contract\VersionRestoreGuard` | interface | The right to go back to a past version |
| `AllowVersionRestore`, `RefuseVersionRestore` | guards | Everyone, or no one |
| `Contract\PersonalFields` | interface | Which fields of a class hold personal information |
| `NoPersonalFields`, `ListedPersonalFields` | personal fields | None, or those listed by class |
| `PersonalFieldPolicy` | enum | What a version keeps of the personal fields: `Exclude`, `Encrypt` or `Keep` |
| `Contract\VersionCipher` | interface | Seals and opens the personal fields under `Encrypt` |
| `VersionEraser` | service | Takes the personal fields out of the versions of a person, of a record, or of every version |
| `Exception\UnknownVersion`, `Exception\VersionRestoreDenied`, `Exception\InvalidVersionedState`, `Exception\VersionErased` | exceptions | |

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

Going back to a version an erasure redacted is refused with `VersionErased`, and so is going back to a version older
than a redacted one of the same record: either would put personal information erased back on the record. The versions
frozen after the erasure go back as before.

## Personal fields

A snapshot stays as long as its row. The fields `PersonalFields` names for the class of a record follow the policy
`Versions` is given:

```php
$versions = new Versions($manager, $clock, $guard, new ListedPersonalFields([
    Member::class => ['name', 'email'],
]), PersonalFieldPolicy::Exclude);
```

| Policy | What `freeze()` writes | What `restore()` puts back |
|---|---|---|
| `Exclude`, the default | The other fields only | The values the record holds for the personal fields |
| `Encrypt` | Each personal field as `{"@sealed": "…"}`, sealed by the `VersionCipher` | The values the cipher opens |
| `Keep` | Every field as `versionedState()` gives it | The values of the version |

`Encrypt` needs a cipher: `Versions` throws `InvalidVersionedState` when none is given, and so does `restore()` of a
version that holds a sealed field when no cipher is given. The cipher binds each value to its record, its version
number and its field: the context passed to `seal()` and `open()` is
`phil_record_version|<table>|<identifier>|<number>|<field>`, and a value moved to another place does not open. Without
`PersonalFields`, `Versions` takes `NoPersonalFields`, and every field is kept: `versionedState()` should then leave out
anything personal that should not outlive an erasure request.

## Erasure

`created_by` of a version, which names who froze it, is blame: `BlameEraser` reaches it. `VersionEraser` takes the
personal fields out of the snapshots themselves, in the table, and dates each version it redacts in `redacted_at`:

| Call | Versions it redacts |
|---|---|
| `find($who)` | None: how many versions of the person still hold personal fields |
| `redact($who)` | Those of the records the person owns (`owner_id`) or is blamed for (`created_by`, `updated_by`), and those the person froze, for the classes that have personal fields |
| `redactRecord($record)` | Every version of one record, whatever its fields: an erasure of the record itself |
| `stripPersonalFields($dryRun)` | None: the personal fields held in plain leave every version, a sealed value stays |
| `tables()` | None: the tables that hold versions, for an erasure report |

`redact()` finds the versions of a person through the owner and the blame: run it before `OwnerEraser` and
`BlameEraser` replace the person's name. A redacted version loses the keys of the personal fields of its record's
classes, sealed or not; the rest of its state stays, and `restore()` refuses it. A second run finds nothing left and
redacts nothing.

```php
$versions = new VersionEraser($registry, $personalFields, $clock);
$versions->redact('ada');
$owners->replace(new OwnerId('ada'));
$blame->replace('ada');
```

`stripPersonalFields()` is what an upgrade runs once over the versions written before the personal fields were left
out: it changes no `redacted_at`, and the record can still go back to those versions, with its own values for the
fields taken out. It reads the versions by batches of 500.

`VersionEraser` writes the table directly, like the other erasers: given a `ManagerRegistry`, it reaches the table once
per connection, in one transaction of that connection, then evicts the second-level cache region of
`RecordVersion` and detaches the versions it changed. See [Bulk writes](bulk-writes.md) for what the listeners do not
see.
