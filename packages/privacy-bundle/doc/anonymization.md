# Anonymization

A copy of the production database is the best data a developer, a tester or a support person can work with, and
the worst thing to leave on a laptop. `phil:privacy:anonymize` rewrites the personal data of a database with
plausible values that tell nothing about the people they belonged to, so the copy can be shared with the team.

## The plan

What is rewritten is a plan: a list of columns, each with an anonymizer type and its options. It is drawn from
three sources, in this order, a later rule replacing an earlier one on the same column:

1. **`#[Anonymize]` on a mapped Doctrine property.**

   ```php
   use Phil\PrivacyBundle\Domain\Attribute\Anonymize;
   use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

   #[ORM\Column]
   #[PersonalData('email', retention: 'R-002')]
   #[Anonymize('email', options: ['domain' => 'example.org'])]
   private string $email;
   ```

2. **The category of a `#[PersonalData]` property without `#[Anonymize]`**, through
   `phil_privacy.anonymization.categories`:

   ```yaml
   phil_privacy:
       anonymization:
           categories: { email: email, person_name: name, phone: phone }
   ```

3. **The configured tables**, which also reach tables no entity maps:

   ```yaml
   phil_privacy:
       anonymization:
           tables:
               member:
                   email: hash
                   note: { type: lorem, options: { words: 6 } }
               legacy_log:
                   remote_ip: null
   ```

A `#[PersonalData]` field of a mapped column that none of the three reaches is **left in clear**, and reported:
`--dry-run` lists it, `--strict` refuses to run while one is left. Personal data the bundle declares is therefore
tied to its anonymization: adding a personal field without saying how it is anonymized shows up in the plan.

Entities are read in the order of their class names. A field inherited from a parent entity is read on the parent,
whose table holds it in single table and joined inheritance alike; a property of a mapped superclass belongs to
each entity that extends it. Properties of embeddables are not read: name their columns under `tables`.

## Anonymizers

| Type | Writes | Options |
|---|---|---|
| `email` | `user.3f9a…@example.com`: a local part derived from the original address under the run's salt, case-insensitively | `domain` (`example.com`), `prefix` (`user`) |
| `firstname`, `lastname`, `name` | A given name, a family name, or both, from the locale pack | — |
| `phone` | A number in one of the pack's fictional ranges, such as `514 555-0142` | `pattern` |
| `address` | A street line, a city, a region, a postal code, the country's name, or a whole address in the pack's format | `part`: `line` (default), `city`, `region`, `postal_code`, `country`, `full` |
| `iban` | An account number of the IBAN shape whose check digits are right | `country` (the pack's), one of AT, BE, CH, DE, ES, FR, GB, IE, IT, LU, NL, PT |
| `date_shift` | The date moved by 1 to `days` days, forward or back, in the format it was read | `days` (30), `format` when it is none of `Y-m-d`, `Y-m-d H:i:s`, `Y-m-d H:i:s.u`, `Y-m-d\TH:i:sP` |
| `constant` | The same value for every row, with its type | `value` (`''`) |
| `null` | Null; the column must accept it | — |
| `hash` | The hexadecimal HMAC of the value under the run's salt | `algorithm` (`sha256`, any of `hash_hmac_algos()`), `length` |
| `lorem` | One sentence of placeholder Latin | `words` (12) |
| `pattern` | A value of a known shape: `#` a digit, `?` an uppercase letter, `*` either, `\` an escape | `pattern`, required |

Every anonymizer also reads `locale`, which replaces the run's locale pack for that column.

**A null value is left null**, whatever the anonymizer: there is nothing personal in it, and a column that was
optional stays optional. A row whose anonymized columns are all null is not written.

**Derived values join.** `email` and `hash` derive their result from the original value under a salt drawn for
the run and never kept: within one run the same address gives the same result in every table, so a value repeated
across tables still matches, and a unique column stays unique; without the salt, no value can be found again by
hashing a list of candidates.

**The other anonymizers draw at random.** Each part of an address is drawn on its own, so the city does not match
the postal code — one more thing the copy does not tell.

## Locale packs

The packs give the names, streets, cities, regions, postal code patterns and fictional telephone ranges the
anonymizers draw from: `fr_CA`, `en_CA`, `fr_FR`, `en_US`, `en_GB`, `en_AU`, `de_DE` and `es_ES`. The run's pack is
`phil_privacy.anonymization.locale` (`en_US` by default).

**Telephone patterns are fictional ranges** that each regulator keeps out of service: 555-01xx in North America,
01 99 00 and its siblings in France, 07700 900 and the drama ranges in the United Kingdom, 5550 and 7010 in
Australia. Germany and Spain reserve none, so their packs carry no telephone pattern, and the `phone` anonymizer
asks for a `pattern` option rather than inventing a number that may ring someone.

An application adds or replaces a pack with a `LocalePack` service, found by autoconfiguration; see
[extension-points.md](extension-points.md).

## The command

```bash
bin/console phil:privacy:anonymize --dry-run
bin/console phil:privacy:anonymize --confirm --seed=7
```

| Option | Effect |
|---|---|
| `--dry-run` | Prints the plan, `table.column: type (origin)`, and each field left in clear; changes nothing |
| `--strict` | Fails while a personal field is left in clear |
| `--seed=N` | Makes the random draws repeatable: the same plan on the same data gives the same copy |
| `--confirm` | Anonymize; without it, nothing is done |

The run is **one transaction**. Everything that can be refused is refused before a row is read: an unknown type
or locale, a table without a primary key, a rule on a key column (rewriting a key would break the rows that refer
to it). An option an anonymizer cannot read is found on the first row, and the transaction is rolled back.

Rows are read in pages of `batch_size` (500), ordered by primary key, and written one by one by key. Names are
read as Doctrine writes them, unquoted — PostgreSQL folds `firstName` to `firstname` —, and a schema may prefix a
table, as in `billing.invoice`.

## The production guard

Anonymization rewrites personal data for good: on production it destroys the business's records. The guard
refuses before anything is written, the dry run excepted:

- when the kernel runs in one of `phil_privacy.anonymization.guard.environments` (`[prod]`);
- when the database's host is in `guard.hosts`, or its name — a SQLite file's path — in `guard.databases`.

The second check catches a developer's machine pointed at the production server by a stray `DATABASE_URL`. There
is no option to bypass the guard: back production up, restore the backup elsewhere, and anonymize there.

## A copy to share with the team

Backing up and restoring is not this bundle's work. With `phil/backup-bundle`, a copy of production to hand to
developers takes three commands, the last two on a machine that is not production:

```bash
bin/console phil:backup:create                                                   # in production
bin/console phil:backup:restore var/backup/app-20261106-031500.sql.enc --confirm --database=app
bin/console phil:privacy:anonymize --confirm
bin/console phil:backup:create --label=anonymized --no-media
```

The restore asks for the database's name again and backs the local content up first; the labelled backup is kept
apart from rotation, ready to share.

## What anonymization does not do

It does not make a copy safe to publish. Names and streets are ordinary ones, and a combination may match a real
person; free text other than the columns of the plan, files on disk, logs and search indexes are untouched. Keep
an anonymized copy within the team, and delete it when it is no longer needed.
