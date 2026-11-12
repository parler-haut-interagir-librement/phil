# Slug

| Type | Kind | Role |
|---|---|---|
| `Contract\Sluggable` | interface | `slug()`, `slugSource()`, `sluggerName()`, `slugScope()`, `assignSlug()` |
| `SluggableEntity` | trait | The column `slug`, 255 characters, with a unique index; no scope |
| `ScopedSluggableEntity` | trait | The column `slug`, 255 characters, with no index; the entity declares its scope and index |
| `SlugListener` | listener: `onFlush` | Makes the slug with `phil/slug` and keeps it unique |
| `Exception\SlugUnavailable` | exception | Every numbered form is taken |
| `Exception\InvalidScope` | exception | A scope the listener cannot compare |

```php
#[ORM\Entity]
class Article implements Sluggable
{
    use SluggableEntity;

    public function slugSource(): string { return $this->title; }
}
```

The text becomes a slug through the `Sluggers` of `phil/slug`: its transliteration, length and named sluggers are all
there, and `sluggerName()` picks one by name, the default one when it gives `null`.

## When a slug is made

| Flush | Slug |
|---|---|
| an entity inserted without one | made from `slugSource()` |
| an entity inserted with one | kept, once unique |
| an entity updated, its slug untouched | left alone, whatever its title became |
| an entity updated with a new slug | kept, once unique |
| an entity after `assignSlug(null)` | made again; it may come out the same |
| an entity moved to another scope | kept when free there, numbered when not |

An address that changes with each edit of a title is a broken link: a slug stays until the application asks otherwise.

## Uniqueness

A slug that is taken gets a number: `about`, `about-2`, `about-3`, each cut to fit the column, up to
`SlugListener::MAX_ATTEMPTS`, 100, and then `SlugUnavailable` asks for another title. Finding the first free one is a
single `IN` query on the table itself, so a soft-deleted or deactivated row keeps its slug, as its index entry does. Rows
of the same flush count too. A subclass counts the table that holds the column.

The query cannot see an insert of another connection that has not committed. The unique index of the column holds then,
and the flush raises `UniqueConstraintViolationException`, which the application retries. An entity implementing
`Sluggable` by hand declares that index itself, on a field named `slug`.

A text with nothing to make a slug of raises the `InvalidSlug` of `phil/slug`.

## Scope

A slug can be unique within a scope instead of the whole table: one `about` per tenant, per locale, or under each
parent. `slugScope()` names the fields of the scope, each a column of the record, an enum column included, or a to-one
relation it holds by one column; `SluggableEntity` gives `[]`. A null value is a scope of its own, compared with
`IS NULL`.

```php
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['tenant_id', 'locale', 'slug'])]
class Page implements Sluggable
{
    use ScopedSluggableEntity;

    public function slugSource(): string { return $this->title; }
    public function slugScope(): array { return ['tenant', 'locale']; }
}
```

The query that finds the first free form adds the values of the scope to its condition; rows of the same flush count in
their own scope only, and a scope whose relation points to a record of the same flush, not stored yet, holds nothing
yet. The unique index on the columns of the scope and the slug holds against concurrent inserts, as the index of the
column does without a scope. Most platforms let two rows share a slug when a column of the index is null: give the
scope non-null columns, or a partial index of the platform, where that matters.

The slug of a `Translation` is unique within a scope that names its locale, or `SlugListener` raises `InvalidScope`:
see [Translation](translation.md#slugs).

A to-many relation, a relation held by several columns, or a relation to a record whose identifier is itself a
relation raises `InvalidScope` at the flush that needs it.
