# Console commands

With the Console component installed, the bundle registers nine commands. Each works through the same services as
the application's own code, so a certificate awarded from the console is signed, stored and announced like any other.

| Command | What it does | Exit code |
|---|---|---|
| `phil:certificates:course <slug> <name> <narrative>` | Defines a course, or revises it with `--revise`, as `Courses` does | `0` saved, `1` refused, `2` unreadable |
| `phil:certificates:award <course> <name> <identity>` | Awards the certificate of a course, as `Awarding` does | `0` awarded, `1` refused, `2` unreadable |
| `phil:certificates:revoke <code> <reason>` | Revokes a certificate with its reason, as `Revocation` does | `0` revoked, `1` refused, `2` unreadable |
| `phil:certificates:status <code>` | Shows where a certificate stands now, as text or JSON | `0` found, `1` unknown, `2` unreadable |
| `phil:certificates:export <code>` | Writes the signed credential alone | `0` written, `1` unknown, `2` unreadable |
| `phil:certificates:recertify <code> <identity>` | Renews a certificate with a new one linked to it, as `Awarding::recertify()` does | `0` renewed, `1` refused, `2` unreadable |
| `phil:certificates:remind` | Dispatches the reminders of the certificates about to end, as `ExpiryReminders` does | `0` run, `2` unreadable |
| `phil:certificates:credit <member> <title> <credits>` | Records the credits of an activity of continuing education, or removes one with `--remove` | `0` saved, `1` refused, `2` unreadable |
| `phil:certificates:credits [<member>]` | Shows a member's credits over a period, or the progress of a certificate's holder | `0` shown, `1` unknown, `2` unreadable |

A refusal — a course already defined, criteria not met, a retired course, a certificate already revoked, an unknown
code — and an unreadable value — a score in words, a code of the wrong shape — go to the error output, one line naming
what is wrong.

## Courses

```sh
bin/console phil:certificates:course first-aid "First aid" "Score 70 % or more and practise CPR." \
    --description="The gestures that save lives." --min-score=70 --min-minutes=240 --module=cpr --validity=24 \
    --image=https://example.org/badges/first-aid.png --credits=12 --ends=2030-01-01
```

```text
Defined the course "first-aid", First aid.
https://example.org/certificates/courses/first-aid
```

The slug, the name and the narrative of the criteria are required; the address printed is the course's page, which
identifies the achievement of its certificates. `--min-score` is a whole number from 1 to 100, `--min-minutes` and
`--validity` whole numbers of minutes and months; `--module` repeats for each module to complete; a course without
`--validity` gives certificates that do not end. `--credits` is the number of credits of continuing education a
recertification asks for, with up to two decimals after a point or a comma; `--ends` the day the course ends, written
`YYYY-MM-DD`, at midnight UTC; `--completion-source` the key of the course in the system its completions are asked of
([Completion sources](completion-sources.md)). With `--revise`, the course of that slug is revised with the values
given — those left out are cleared, as with `Courses::revise()` — and certificates already awarded keep their
credential; the completion source alone changes only when `--completion-source` is given, and an empty one removes
it. Retiring and reopening a course go through `Courses`.

## Awarding

```sh
bin/console phil:certificates:award first-aid "Ada Lovelace" ada@example.org --score=82 --minutes=240 --module=cpr --module=aed --listed --holder=member-42
```

```text
Awarded the certificate 7KQ2-M9XA-04TZ of "first-aid" to Ada Lovelace.
https://example.org/certificates/7KQ2M9XA04TZ
```

The identity is an e-mail address, a URL or a decentralized identifier, as with `Awarding::award()`; it is never
printed, and the credential keeps an address or a URL as a salted hash only. `--score` is a whole number from 0 to
100, `--minutes` a whole number of minutes, 0 when left out; `--module` repeats for each module completed; `--listed`
lists the certificate on its course's page in the register; `--holder` names the member by the stable identifier the
application gives it, by which credits of continuing education are counted and the completion of a course asked —
never an e-mail address.

A course that names a completion source asks it whether the holder completed the course, and the identifier of the
completion is printed on a third line:

```text
Awarded the certificate 7KQ2-M9XA-04TZ of "first-aid" to Ada Lovelace.
https://example.org/certificates/7KQ2M9XA04TZ
Completion: enrolment-7
```

`--on-completion` awards as `Awarding::awardOnCompletion()` does: it needs `--holder`, and a completion already
awarded prints the certificate that holds it and succeeds, rather than failing; the first line then reads
`The certificate 7KQ2-M9XA-04TZ of "first-aid" is awarded to Ada Lovelace on that completion.`

## Revoking

```sh
bin/console phil:certificates:revoke 7kq2-m9xa-04tz "The assessment was taken by someone else."
```

The code is read as the register reads it: lower case, dashes and spaces are forgiven.

## Status

```sh
bin/console phil:certificates:status 7KQ2-M9XA-04TZ
```

```text
code               7KQ2-M9XA-04TZ
status             valid
course             first-aid
name               Ada Lovelace
awarded_at         2026-11-08T10:00:00+00:00
valid_until        2027-11-08T10:00:00+00:00
```

Below them come, when they apply, `revoked_at` and `revocation_reason`, `holder`, `completion`, `renews` and
`renewed_by` — the
codes of the certificate it renews and of the one that renews it — and `reminded_at`. `--format=json` gives the same
fields as one JSON object, `null` for those that do not apply — `valid_until` of a certificate that does not end,
`revoked_at` and `revocation_reason` of one that is not revoked. The status is
computed at the moment of the clock service: a certificate past its `valid_until` reads `expired`.

## Exporting a credential

```sh
bin/console phil:certificates:export 7KQ2-M9XA-04TZ > credential.jwt
```

The output is the credential exactly as it was signed — a compact JSON Web Token, or the JSON of a document with its
proof — with no line ending added, so that the file is what a validator or a wallet reads. With `-v`, the kind of
credential and the file name it calls for go to the error output: `credential.jwt` (`application/vc+jwt`) or
`credential.json` (`application/vc`). [Validation](validation.md) uses it.

## Recertifying

```sh
bin/console phil:certificates:recertify 7KQ2-M9XA-04TZ ada@example.org
bin/console phil:certificates:recertify 7KQ2-M9XA-04TZ ada@example.org --score=85
```

```text
Renewed the certificate 7KQ2-M9XA-04TZ with M3XQ-7TZA-9KQ2, valid until 2028-11-08.
https://example.org/certificates/M3XQ7TZA9KQ2
```

The identity is the one the certificate was issued to; it is never printed. A course that asks for credits counts the
holder's; `--score`, `--minutes` and `--module` give an attainment, checked against the criteria, which a course
without credits requires. [Recertification](recertification.md) says what is refused and why.

## Reminders

```sh
bin/console phil:certificates:remind --limit=1000
```

```text
Dispatched 3 reminders.
```

Run it once a day, from cron or the application's scheduler. It dispatches `CertificateExpiring` for each certificate
that reached a threshold of `reminders.days` since its holder was last reminded, `--limit` at most per run, 1000 by
default; the next run takes the rest. The application's listener sends the reminder.

## Credits of continuing education

```sh
bin/console phil:certificates:credit member-42 "Workshop on triage" 3.5 --completed=2026-11-12 --course=first-aid --source=workshop-17
bin/console phil:certificates:credit --remove=0190f1d2-3c4b-7a8e-9f00-112233445566
```

```text
Recorded 3.5 credits for member-42: 0190f1d2-3c4b-7a8e-9f00-112233445566.
```

`--completed` is the day it was completed, now when left out; `--course` the course whose recertification it counts
toward, every course when left out; `--source` where it comes from, unique for the member: the same source recorded
again prints the activity already recorded.

```sh
bin/console phil:certificates:credits member-42 --from=2026-01-01 --until=2027-01-01 --course=first-aid
bin/console phil:certificates:credits --certificate=7KQ2-M9XA-04TZ
```

```text
member     member-42
from       2026-01-01
until      2027-01-01
total      7.5
  2026-11-12     3.5  Workshop on triage
  2026-06-01       4  First aid refresher
```

The period starts on `--from` and ends before `--until`; without them, it is the twelve months before today. With
`--course`, only the credits that count toward that course are summed and listed. With `--certificate`, the command
shows the credits the holder gathered since the certificate was awarded, those its course asks for and those still
missing. `--format=json` gives the same fields as one JSON object.
