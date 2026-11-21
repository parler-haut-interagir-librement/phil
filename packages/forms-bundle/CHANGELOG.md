# Changelog

All notable changes to `phil/forms-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.1-alpha.

### Changed

- The development requirements accept `phil/privacy-bundle` 0.2 and 0.3, so that the package installs on its own beside
  the published version of that bundle.

### Fixed

- `phil/spam-protection-bundle` 0.3 is accepted beside 0.1 and 0.2: the package did not install beside the version
  published on 2026-11-20.

## 0.2.0-alpha - 2026-11-18

The keys of the forms, entries and counts of places compare byte by byte on MySQL and
MariaDB, and the tables can be created on MySQL. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event, and its `FIELDS`: the fields of
  the bundle's entities that compare byte by byte on MySQL and MariaDB.

### Changed

- On MySQL and MariaDB, `id` and `form_key` of `phil_form`, `id`, `form`, `locale`, `subject` and `email_digest` of
  `phil_form_entry`, and `form`, `field_name` and `choice` of `phil_form_choice_count` take the `utf8mb4` character set
  and the `utf8mb4_bin` collation in the schema the ORM generates, through `Infrastructure\Doctrine\BinaryKeysListener`.
  PostgreSQL and SQLite keep their default, which compares bytes already.
- `phil_form.notifications` and `phil_form_entry.sealed_fields` have no default: the bundle writes both on every row.

### Fixed

- MySQL refused to create `phil_form` and `phil_form_entry`: their JSON columns carried the literal default `'[]'`.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, the form `Contact` was found for `contact`, the
  entries of the person `Ann` were listed and erased for `ann`, and the places taken by the choice `Yes` were counted
  for `yes`.

## 0.1.1-alpha - 2026-11-17

### Changed

- It accepts `phil/spam-protection-bundle` 0.2 beside 0.1.

## 0.1.0-alpha - 2026-11-14

### Added

- `Form`, whose webhook is an `https` URL on a public host and whose `fields()` are built once while their
  definitions hold, and `Form::identifyBy()`, an email field whose address the entry keeps as `Entry::$emailDigest`,
  by `HmacEmailDigester` under the key `digest_key`; `Entry`, whose times read in UTC whatever PHP's default time
  zone, and `Entry::$subject`, who the entry is about: the identifier of the person when known, as
  `Entry::subjectKey()` keeps it — an identifier with whitespace, or longer than 255 bytes, by its SHA-256 —, from
  `CurrentSubject` — `TokenCurrentSubject` with Symfony Security —; `Field`, `FieldType`, `FormRepository`, with
  `about()`, `FormsException`, `InvalidForm` and `InvalidEntry`.
- `FieldType::BirthDate`, a date not after the day the entry comes nor more than 130 years before it, refused as
  `birth_date`; its ages, `Field::$minAge` and `Field::$maxAge` (`min_age`, `max_age`, 0 to 130, inclusive), counted
  in full years by `Field::age()` on `Field::$referenceDate` (`reference_date`) or the day the entry comes, refused as
  `too_young` or `too_old` with the age as `%age%`, a person born on 29 February a year older on 1 March of a common
  year; `Field::errorParameters()`; choices of 1 to 255 characters, at most 500.
- Questions asked under a condition: `Field::$shownWhen`, a condition whose JSON is a subset of JsonLogic, read by the
  port `ConditionEvaluator` — `BasicConditions`, `==`, `!=` and `in` on one field, the default —, checked against
  the types of the fields before it by `Form::define()`, and `Form::holds()`; a field asked when its condition holds
  and every field it reads was asked, a field not asked keeping no value; `phil/form-logic` suggested, whose
  `JsonLogicConditions` reads its whole subset.
- `BasicConditions` reads `!=` as JsonLogic and `phil/form-logic` do: a field left empty is not `"Blue"`, so the
  condition holds; a field not asked still satisfies no condition.
- Tiers: `Field::$tiers`, `Field::isFor()`, `Form::fieldsFor()`, the `$tiers` of `Form::accept()`, `Form::submit()`
  and `Submissions::submit()`, and the option `tiers` of `EntryType`.
- Disqualifying choices: `Field::$disqualifying` and `Field::disqualification()`, the entry refused as
  `disqualified`, the form's message on the field of the page.
- Places in choices: `Field::$capacities` and `Field::capacity()`, the port `ChoicePlaces`, implemented by
  `DoctrineFormRepository` on the table `phil_form_choice_count` (`ChoiceCount`), counted in one statement under
  the capacity and given back when an entry is refused, cannot be kept or is removed; the entry refused as `full`,
  `Submissions::fullChoices()`, and the option `full_choices` of `EntryType`, the choices offered disabled.
- Other notifications: `Notification`, its recipients, its subject with `{field}` placeholders and its condition,
  `Form::notifyAlso()` and `Form::notifications()`, at most 20, kept in the column `notifications`.
- Sensitive fields: `Field::$sensitive`, never sent by email or webhook — `data.withheld` names them —, sealed by the
  port `FieldSealer`, `Entry::$sealed`, `Entry::context()`, `SealedValues` and `UnreadableValue`;
  `KeyManagementFieldSealer`, the default sealer, on the envelope encryption of Symfony's KeyManagement component
  (`phil_forms.sealing.key` and `sealing.kms`, `symfony/key-management` suggested), its values `phs1.<key>.<envelope>`,
  wired by `FieldSealerPass`, which stops the container when the component or its client is missing. Without a
  sealer, a form with a sensitive field is refused by `EntryType` and `Form::submit()` — `Form::checkSealing()`,
  `UnsealedSensitiveField`, whose message says what to configure —, unless `allow_unsealed_sensitive` is true.
- `Submissions`, which takes a required `ClockInterface` and an `EmailDigester`, then the optional
  `ConditionEvaluator`, `FieldSealer`, `ChoicePlaces` and whether sensitive values may be kept as sent, and whose
  `submit()` takes the subject and the tiers; a notifier's error of any kind is logged, its class and message without
  the values, and the other notifiers still run. `Retention`, `EntryNotifier` and `NotificationFailed`.
- `DoctrineFormRepository`.
- `PhilFormsBundle`, extension alias `phil_forms`, keys `sender`, `subject_prefix`, `webhook_secret`, `template`,
  `digest_key`, `allow_unsealed_sensitive` and `sealing`, whose `key` and `kms` take null or a non-empty text;
  `NOTIFIER_TAG`; the bundle wires the container's `clock` service, through `FormsServices`.
- `EntryType`, `FormController` and its route.
- Conditions applied in the browser when `phil/form-logic` is installed: `LogicSteps`, the conditions of the fields a
  page shows as the steps of that library's Stimulus controller, a field shown only when the fields its condition
  reads are; `EntryType`, built with `$browserConditions` and the `ConditionEvaluator`, names each conditional row in
  `data-form-logic-field` and gives its view `phil_form_logic_steps`, which `_form.html.twig` writes on the form with
  the controller; without the library, or without conditions, the page is rendered as before. The browser suite of
  the page, under Panther.
- `EmailNotifier`, which also sends the other notifications whose condition holds, every email tried, and
  `WebhookNotifier`, with a required `ClockInterface`, posting through the service
  `phil_forms.webhook_client`, `NoPrivateNetworkHttpClient` when `symfony/http-client` is installed.
- `PurgeEntriesCommand`.
- Templates and translations, English and French.
- The Flex recipe, `recipe/0.1`: the bundle registered, `config/packages/phil_forms.yaml` with the defaults and
  `config/routes/phil_forms.yaml`, the pages under `/forms`.
- `FormEntriesPrivacy`, the module `forms` of `phil/privacy-bundle`'s access and erasure answers, sealed values
  opened or listed under `unreadable`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
