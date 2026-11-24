# Rotation

## The rules

`RotationPolicy(last, daily, weekly, monthly)` keeps:

| Rule | Keeps |
|---|---|
| `last: n` | the `n` most recent sets |
| `daily: n` | the most recent set of each of the latest `n` days that have one |
| `weekly: n` | the most recent set of each of the latest `n` ISO weeks that have one |
| `monthly: n` | the most recent set of each of the latest `n` months that have one |

A set kept by any rule is kept; the others have expired. This is the grandfather-father-son scheme: with
`last: 3, daily: 7, weekly: 4, monthly: 12` and a backup every night, the directory holds about two dozen sets
reaching a year back.

Each count is zero or more, and one at least is above zero; a rotation that would keep nothing is refused with
`InvalidBackupValue`. The most recent set is always kept, since every rule keeps at least one.

## Periods

Days, weeks and months are those of UTC, the time of the file names, so a server's change of time zone or of summer
time moves no backup from one day to another. Weeks are ISO weeks, Monday to Sunday: a Sunday backup belongs to the
week of the Monday before it. A period without a backup is not counted: after a week of outage, `daily: 7` still
keeps seven sets.

## What is rotated

`expired($sets)` gives the expired sets, oldest first; `Backups::prune()` removes them and gives them back. Only
sets **without a label** are rotated: the copy taken before a restore, an anonymized copy or one taken before a
migration stays until it is removed by hand, or by `Backups::keepLatest()` for one label. Files of the directory that
are not sets are never touched.

## Labelled sets and their retention

A labelled set has no period of its own: it stays until something removes it. Give each label a period, written down
beside the application's other retention periods, and remove its sets when it ends:

- **by count**: `Backups::keepLatest($label, $keep)` removes the sets of the label but the `keep` most recent ones,
  and gives them back, oldest first;
- **by age**: list the sets of the label and remove those taken before the period:

```php
$cutoff = new DateTimeImmutable('-30 days');

foreach ($backups->directory()->labelled($backups->client()->extension(), 'anonymized') as $set) {
    if ($set->time < $cutoff) {
        $backups->directory()->remove($set);
    }
}
```

**A copy labelled `anonymized` is personal information.** A database whose columns were rewritten to work on a copy
of production keeps its primary keys, the columns no rule names and its free text: it can be tied back to the people
of production. Under Quebec's law on the protection of personal information in the private sector it is at best
de-identified, not anonymized, and other laws see it the same way. Such a copy needs a short period — the time of
the work it was made for —, encryption like every other set, and its leak is a confidentiality incident. Removing it
at the end of its period is the default; nothing in this library makes a copy anonymous.

## Rotation beside a backup

`Backups` takes no lock: `prune()` and `keepLatest()` remove sets while another process may be writing a new one or
testing an old one. A set being written is never removed — it has no name until it is complete, and the most recent
set is always kept —, but one under test may be. An application that runs backups, rotations and tests from more
than one process serializes them with one lock per directory; phil/backup-bundle does so with Symfony Lock.
