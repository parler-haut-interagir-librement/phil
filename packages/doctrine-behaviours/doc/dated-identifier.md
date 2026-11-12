# Dated identifier

| Type | Kind | Role |
|---|---|---|
| `Contract\DatedIdentifiable` | interface | `datedId()`, `assignDatedId()`, `datedIdTime()` |
| `DatedIdentifierEntity` | trait | The primary key `id`, a `guid` column |
| `DatedIdentifierListener` | listener: `prePersist` | Gives a new record a UUID v7 of the clock's time |

The identifier is a UUID v7 of `symfony/uid`: 48 bits of milliseconds followed by random bits. Identifiers sort in the
order they were made, a primary key on them stays compact, and any tool that reads a UUID reads this one, with no format
of our own to document or parse.

```php
$ticket->datedId();                                // '019a4f2e-…', known once persisted
$ticket->datedIdTime();                            // the moment it holds
```

An identifier given beforehand is kept, and must be a UUID v7: `assignDatedId()` refuses anything else.

The time of creation can be read from the identifier: where that time is itself sensitive, use a random identifier. The
listener has to be registered, since nothing else gives the entity its key.
