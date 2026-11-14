# Installation

## Requirements

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The declared floor. The code relies on recent language features such as `array_any()` and typed class constants, and the tests on PHP 8.4+ member access on `new` without parentheses. |
| `monolog/monolog` | `^3.0` | `RedactingProcessor` implements `Monolog\Processor\ProcessorInterface` and works on the immutable `Monolog\LogRecord` of Monolog 3. |
| `symfony/config` | `^8.2` | The configuration tree (`DefinitionConfigurator`, `InvalidConfigurationException`). |
| `symfony/dependency-injection` | `^8.2` | The bundle class extends `AbstractBundle`. |
| `symfony/workflow`, `symfony/lock` | `^8.2` | The account deletion, one execution per person. |
| `symfony/messenger`, `symfony/clock` | `^8.2` | Identifier-only messages and the dates of a request. |
| `symfony/http-kernel`, `symfony/http-foundation`, `symfony/routing`, `twig/twig` | `^8.2`, `^3.21` | The pages and the banner. |
| `symfony/security-core`, `symfony/security-http`, `symfony/security-csrf` | `^8.2` | The member, the sudo mode and the stateless form tokens. |
| `ext-sodium`, `symfony/filesystem` | `*`, `^8.2` | The encrypted exports. |
| `phil/url` | `^0.1@dev` | `CookielessVideo`, the players without cookies of YouTube and Vimeo in third-party frames. |

The bundle does **not** require `symfony/monolog-bundle`, but the processor reaches your loggers only through
the `monolog.processor` tag, which MonologBundle reads. Without MonologBundle the service is defined and never
used; you can still push it onto a `Monolog\Logger` by hand (see [log-redaction.md](log-redaction.md)).

The commands and anonymization need optional packages, suggested by the bundle and installed by the application:

| Package | Constraint | For |
|---|---|---|
| `symfony/console` | `^8.2` | Every command of the bundle. |
| `symfony/scheduler` | `^8.2` | The schedule `phil_privacy`. |
| `doctrine/dbal` | `^4.5` | The connection that is anonymized, and the tables of the deletions, the proofs, the requests without an account, the incidents and the consent to tracked emails, their instants in `datetime_utc_immutable` columns. |
| `doctrine/orm` | `^3.7` | Drawing the anonymization plan from `#[Anonymize]` and `#[PersonalData]` on entities. |
| `symfony/asset-mapper`, `symfony/stimulus-bundle` | `^8.2`, `^3.7` | The Stimulus controllers `consent` and `embed`, served without a build step: the bundle adds its `assets/src` to the paths of AssetMapper under `@phil/privacy-bundle`. |

Backing the database up and restoring a copy to anonymize is the work of `phil/backup-bundle`; see
[anonymization.md](anonymization.md).

The domain layer (`Domain\`) depends on nothing but PHP: the attribute, the redactors and the retention model
can be used in any PHP code, with or without Symfony.

## Installing

```bash
composer require phil/privacy-bundle
```

The package declares `"minimum-stability": "dev"` and a branch alias `dev-main` → `0.1.x-dev`; until a tagged
release exists, require it as `phil/privacy-bundle:^0.1@dev`.

## Registering the bundle

With Symfony Flex the bundle is registered automatically. Otherwise add it to `config/bundles.php`:

```php
return [
    // ...
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Phil\PrivacyBundle\PhilPrivacyBundle::class => ['all' => true],
];
```

Register it in every environment: log redaction matters in `dev` and `test` as much as in `prod`, because
those logs are copied into tickets and CI artefacts.

## Next steps

1. Write `config/packages/phil_privacy.yaml` with your denylist — see [configuration.md](configuration.md).
   With no configuration the processor is registered but denies no key; query strings and URL user
   information are still redacted.
2. Declare personal fields with `#[PersonalData]` — see [personal-data-declaration.md](personal-data-declaration.md).
3. Implement `PersonalDataExporter` and `PersonalDataEraser` in each module: with autoconfiguration, the bundle
   finds them and wires `SubjectExport`, `SubjectErasure` and their commands — see
   [extension-points.md](extension-points.md).
4. Have the user class implement `DataSubjectUser` and create the tables: a member's export and erasure, and the
   consent page, are mounted already — see [preferences.md](preferences.md) and [consent.md](consent.md).
5. Write the retention schedule, with a `RetentionPurger` per store, and run the schedule `phil_privacy` — see
   [retention.md](retention.md).
6. Declare how each personal field is anonymized with `#[Anonymize]`, or by category, and try
   `bin/console phil:privacy:anonymize --dry-run` — see [anonymization.md](anonymization.md).
