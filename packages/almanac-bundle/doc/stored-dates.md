# Stored dates and the form

## The table

With `database: true`, the dates of `phil_almanac_date` join those of the configuration, and **a stored date replaces
the configured one of its key**: an administrator changes a date without a deployment. A stored date keeps its key:
`StoredDate::change()` refuses a definition of another key with an `InvalidRule`. A row whose rule no longer reads —
a column changed by hand — is left out of the almanac, for one row not to stop every page.

| Column | Type | |
|---|---|---|
| `id` | UUID v7 | |
| `date_key` | `VARCHAR(100)`, unique | The date's key |
| `title` | `VARCHAR(200)` | |
| `rule`, `until_rule` | `VARCHAR(200)`, the second nullable | The rules' canonical text |
| `days` | `SMALLINT` | |
| `all_day` | boolean | |
| `tags` | JSON | |

`StoredDate` is built from a `Definition` and gives it back, `definition()`; `change()` takes another's title, rules,
length and tags, the key staying. The port `StoredDateRepository` — `all()`, by key, `ofKey()`, `save()`, `remove()` —
is `DoctrineStoredDateRepository`, which flushes at each save and removal. The almanac is built from the definitions
each time it is asked for, so a date saved is in the next almanac.

`DoctrineStoredDateRepository` reads the table once and keeps what it read until a date is saved or removed through it,
or the kernel resets its services — the service is tagged `kernel.reset`, so each request, and each message of a
Messenger worker, reads it once however many `almanac_dates()` a page calls. A row written by another process, or
through the entity manager without the repository, is seen at the next reset. A long-lived service that shows the
almanac asks for it at each use, `AlmanacFactory::almanac()` or a service closure, rather than keeping the one it was
given.

```php
$dates->save(new StoredDate($draft->definition()));
$dates->ofKey('thanksgiving')?->change($draft->definition());
```

`Definitions::isConfigured($key)` says whether a key is the configuration's, which a stored date of the same key
replaces — an admin page can say so; `stored()` lists the stored dates.

## The form

`StoredDateType` fills a `DateDraft`: key, title, rule, until, days, all day, and tags separated by commas. With
`key_locked: true` — for a date that exists —, the key cannot be changed. Its labels are in the `phil_almanac`
domain.

```php
$form = $this->createForm(StoredDateType::class, DateDraft::of($stored->definition()), ['key_locked' => true]);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $stored->change($form->getData()->definition());
    $dates->save($stored);
}
```

A `DateDraft` is valid when the almanac can read it: each field on its own — a key of the right shape, a title, a
rule of 200 characters at most, 1 to 366 days —, each rule read, then the date as a whole once every field is valid.
A rule that cannot be read is refused on its field, *This rule cannot be read: …*; a date that cannot be computed,
on the form, *This date cannot be computed: …*; both in French too.
