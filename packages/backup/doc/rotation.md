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
migration stays until it is removed by hand, or by `Backups::keepLatest()` for one label. Files of the directory that are not sets are never touched.
