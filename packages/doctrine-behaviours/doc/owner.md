# Owner

| Type | Kind | Role |
|---|---|---|
| `OwnerId` | value | A stable identifier of at most 255 bytes, without `@` |
| `Contract\Ownable` | interface | `ownerId()`, `claimOwner()`, `transferOwner()` |
| `OwnableEntity` | trait | The column `owner_id`, 255 characters, nullable |
| `OwnerListener` | listener: `prePersist` | Gives a new record the owner the resolver names |
| `Contract\OwnerResolver` | interface | `current(): ?OwnerId`, who new records belong to now |
| `FixedOwner` | `OwnerResolver` | Always the same owner, or nobody |
| `OwnerEraser` | service | Finds and replaces an owner in every owned table |
| `Exception\InvalidOwner` | exception | What cannot be an owner |

An owner is who a record belongs to, where blame is who last touched it. It is set once, as the record is persisted,
unless it was given one, and no update changes it. Handing a record over is the application's call, `transferOwner()`.

## An identifier, not an e-mail address

An owner is copied into every row the person owns and kept for as long as the row, so `OwnerId` refuses anything that
holds an `@` as it is built. The error never repeats the value. A resolver names people by something stable that is no
personal information, such as a UUID.

```php
new OwnerId('0192f0a0-0000-7000-8000-000000000001');   // fine
new OwnerId('ada@example.com');                        // InvalidOwner
```

There is deliberately no fallback on an account's login: `SecurityOwnerResolver`, in the bundle, requires a method to be
named. A row that was written with an `@` by other means makes `ownerId()` throw when it is read.

## Access and erasure requests

`OwnerEraser` answers them over every `Ownable` entity that maps the field `ownerId`. It works on the tables directly,
so soft-deleted and deactivated rows are found too, and no listener runs. `tables()` lists every owned table, found
or not, for a report that names each store.

```php
$eraser->find($owner);                             // ['article' => 12, 'page' => 3]
$eraser->replace($owner, new OwnerId('former-81'));
$eraser->replace($owner);                          // nobody owns them any more
$eraser->tables();                                 // ['article', 'page']
```
