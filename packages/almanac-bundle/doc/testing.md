# Testing

| Area | What is covered |
|---|---|
| `StoredDate`, `Definitions`, `ConfiguredDates`, `AlmanacFactory` | A definition kept and given back, changed; stored dates replacing configured ones; the almanac seeing a date at once, in its time zone |
| `DateDraft` | A draft of a definition and back; each refusal, on its field, and the whole date checked only once its fields are valid |
| `TagsTransformer`, `AlmanacExtension`, `SkyExtension`, `Places` | Tags read and written; the dates of a period in Twig; the solar day of a place by name or as a `Place`, text read in its time zone; the eclipses of a period, of one body or both, and an unknown body refused; an unknown place named with those there are |
| `BinaryKeysListener` | `date_key` in `utf8mb4_bin` in the schema generated for MySQL and MariaDB, the default collation on SQLite, where keys that differ only by case or accent are four rows, the options a field already has kept, another entity left as it is |
| `PhilAlmanacBundle` | The services with and without the database, configured dates built as definitions, the declared eclipses and a date that replaces one, the places and their default time zone, Doctrine found in a kernel's bundles, every configuration refused |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and the bundle: stored
dates saved, read, replacing a configured one and removed; `almanac_dates` rendered, the declared eclipses among them;
`sun_times` and `almanac_eclipses` rendered; the form submitted, refused in French and rendered. Installed on its own,
where FrameworkBundle is a development requirement, it is skipped.

```sh
vendor/bin/phpunit packages/almanac-bundle/tests
```

The suite `tests/Integration` runs `BinaryKeysListener` on a real MySQL 8.4 and PostgreSQL 17: the keys `harvest`,
`Harvest`, `HARVEST` and `harvést` are four dates under the unique key, each found by its own key only, the same key
written twice is still refused, and on MySQL `date_key` is in `utf8mb4_bin` and the table of the previous version
reaches it through the schema diff. Each server's test reads its URL from `PHIL_TEST_MYSQL_URL` or
`PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/almanac-bundle/tests/Integration
```
