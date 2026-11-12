# Timestamps and blame

| Type | Kind | Role |
|---|---|---|
| `Contract\Timestampable` | interface | `createdAt()`, `updatedAt()`, `stampCreated()`, `stampUpdated()` |
| `TimestampableEntity` | trait | The columns `created_at` and `updated_at`, `datetime_immutable` |
| `TimestampListener` | listener: `prePersist`, `onFlush` | Stamps with the clock's time, in UTC |
| `Contract\Blameable` | interface | `createdBy()`, `updatedBy()`, `blameCreated()`, `blameUpdated()` |
| `BlameableEntity` | trait | The columns `created_by` and `updated_by`, 255 characters, nullable |
| `BlameListener` | listener: `prePersist`, `onFlush` | Blames whoever the resolver names |
| `Contract\BlameResolver` | interface | `current(): ?string`, who is acting now |
| `FixedBlame` | `BlameResolver` | Always the same one, or nobody |
| `BlameEraser` | service | Finds and replaces a user's name in every blamed table |

## When

| Flush | Timestamps | Blame |
|---|---|---|
| an entity persisted | `createdAt`, then `updatedAt`, set to now unless already given | `createdBy`, then `updatedBy`, set to whoever acts unless already given |
| an entity updated | `updatedAt` set to now, unless the update itself sets it | `updatedBy` set to whoever acts, unless the update sets it |
| nobody known | — | nothing written |

What an entity was given stays: an import that keeps its original dates and authors calls `stampCreated()`,
`blameCreated()`, `stampUpdated()` or `blameUpdated()` itself, and the listeners leave those values alone.

Blame is a name of the user — what the resolver gives — not a relation to a user entity: the column outlives the
account, and no entity has to know the user class.

## Privacy

What the resolver gives is copied into every blamed row and kept after the account is gone. When it is an e-mail
address — Symfony Security's `getUserIdentifier()` often is — the blame columns hold personal information. Name users
by a stable identifier that is none, such as a UUID, where the resolver allows it.

`BlameEraser` answers access and erasure requests: `find($who)` counts the rows that blame someone in each blamed table,
and `replace($who, $replacement)` puts a pseudonym, or `null`, in their place, in `created_by` and `updated_by` of every
`Blameable` entity — in the parent's table for a subclass that inherits them. It works on the tables directly, so
soft-deleted rows and rows any SQL filter hides are found too, and no listener stamps the rows it changes. `tables()`
lists every blamed table, found or not, for a report that names each store.

```php
$eraser->find('ada@example.com');                 // ['article' => 12, 'page' => 3]
$eraser->replace('ada@example.com', 'former-member-81');
$eraser->tables();                                // ['article', 'note', 'page']
```
