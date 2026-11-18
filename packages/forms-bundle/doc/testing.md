# Testing

| Area | What is covered |
|---|---|
| `Field`, `FieldType` | Every rule; what each type accepts and refuses, trimmed; birth dates bounded by the day; ages counted in full years, both bounds included, on the day of the entry or a reference date, the birthday, the day before, 29 February in common and leap years; tiers, disqualifying choices, capacities, sensitivity, and a definition read back from JSON |
| `BasicConditions`, `Notification` | The three shapes, a field left empty satisfying `!=` only, every malformed condition, the types checked; recipients, subjects on one line, placeholders |
| `Form`, `Entry`, `HmacEmailDigester` | Keys, titles, fields built once while their definitions hold, recipients, webhooks, retention, opening and closing; every error at once; the tables and columns; the subject, the designated email field and the digest of its address; conditions in cascade, tiers, other notifications, sealed values |
| `Submissions`, `Retention`, `DoctrineFormRepository` | Entries kept then notified, a failing notifier logged; listing; deletion at the end of retention and with the form; places taken under the capacity, given back on a refusal, a failed save and a removal |
| `EmailNotifier`, `WebhookNotifier` | Sender, recipients, `Reply-To`; notifications under conditions, every email tried; sensitive values withheld; the signed body verified, answers, refusals; a webhook host that resolves to a private address, IPv4 or hidden in IPv6, refused |
| `KeyManagementFieldSealer`, `SealedValues`, `UnsealedSensitiveField` | Sealing through the local libsodium KMS of Symfony's KeyManagement component, an entry sealed, kept and opened again, the context bound, another master key, the wrong key under the same name, a missing master key keeping no entry, a key name that is not the envelope's, a KMS that fails, every malformed value; a form with a sensitive field refused without a sealer, at build and at submission, unless the application allows it |
| `EntryType`, `FormController`, `LogicSteps` | Fields and options from the definition, the protection, tiers, full choices disabled, the birth date; the page, the redirection, errors on their fields, the age of a refused birth date, the disqualifying message, `404`s; with the browser's conditions, the conditional rows named and the steps, a field read by another condition required shown, a field of another tier read as `false`, nothing for a form without conditions, nor without `phil/form-logic` |
| `PurgeEntriesCommand`, `PhilFormsBundle` | Batches; services, defaults, the spam protection required, the mapping, the signed-in user as the subject, the sealer wired from `sealing`, null or refused when empty, refused without the component or its client, `allow_unsealed_sensitive` |
| The Flex recipe | The bundle registered, its configuration read as the bundle's defaults, the routes under `/forms` |
| `FormEntriesPrivacy`, `TokenCurrentSubject` | Export and erasure by identifier and by address, an erasure run twice, sealed values opened or listed as unreadable; the user signed in |
| `BinaryKeysListener` | The keys in `utf8mb4_bin` in the schema generated for MySQL and MariaDB, the default collation on SQLite, the options a field already has kept |

The suite `tests/Integration` runs `BinaryKeysListener` on a real MySQL 8.4 and PostgreSQL 17: four form keys that differ only by case or accent are four forms, each found by its own key only, and on
MySQL the keys are in `utf8mb4_bin` and the tables of the previous version reach it through the schema diff. Each
server's test reads its URL from `PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/forms-bundle/tests/Integration
```

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and the spam protection,
the bundle configured in YAML as an application does: a page is rendered in French, an entry submitted as a browser
would, kept, emailed and posted; the page of a form with conditions carries the controller of `phil/form-logic` and
its steps, and without the browser's conditions is rendered as before. In the monorepo,
`vendor/bin/phil-dev lint-apps` runs `lint:container`, `lint:expressions` and `lint:yaml` on that kernel.

`JsonLogicConditions`, the adapter of `phil/form-logic`, is tested in that library, on the fields of this bundle,
which also checks that the fields the steps of `LogicSteps` hide are the ones the bundle does not ask.

```sh
vendor/bin/phpunit packages/forms-bundle/tests
```

## The browser suite

`tests/Browser/FormConditionsBrowserTest.php` drives the page of a form in Chrome with Panther, in an application
where `phil/form-logic` is installed as that library describes: AssetMapper maps its `assets/src` as
`@phil/form-logic`, StimulusBundle enables its controller in `assets/controllers.json`, and the application's page
loads the import map and includes `_form.html.twig`. The form lives in SQLite. A field asked of team Blue is hidden,
then shown when Blue is chosen; the field that depends on it follows in cascade, their controls disabled while
hidden; a field asked unless the team is Green is shown while nothing is chosen.

It needs the chromedriver of the system and Stimulus, installed in `tests/Browser` by `npm ci --ignore-scripts`; it is
skipped without it. From the monorepo root:

```sh
vendor/bin/phpunit --configuration=phpunit.browser.dist.xml packages/forms-bundle/tests/Browser
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/forms-bundle/tests/Benchmark
```
