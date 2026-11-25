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

## The tables

`doctrine:migrations:diff` writes the tables of the features the application uses with the table of the bundle as
their store, and only those:

| Table | Feature | Added when |
|---|---|---|
| `phil_privacy_account_deletion` | [The account deletions](account-deletion.md) | `account_deletion.enabled` is true, or null with `symfony/security-bundle` in the kernel; `account_deletion.store` is null; the lock and the clock are there. |
| `phil_privacy_consent_proof` | [The proofs of consent](consent.md) | The consent page is there (Twig, CSRF tokens, the router and the clock) and `consent.proofs` is null. |
| `phil_privacy_guest_request` | [The requests without an account](requests-without-an-account.md) | `guest_requests.enabled` is true and `guest_requests.store` is null. |
| `phil_privacy_incident`, `phil_privacy_incident_revision`, `phil_privacy_incident_journal` | [The register of incidents](incidents.md) | `incidents.enabled` is true, its default, and `incidents.store` is null. |
| `phil_privacy_email_tracking` | [The consent to tracked emails](email-tracking.md) | `email_tracking.enabled` is true and `email_tracking.store` is null. |
| `phil_privacy_erasure_request`, `phil_privacy_erasure_step` | [Erasure as a workflow](erasure-workflow.md) | `erasure_workflow.enabled` is true and `erasure_workflow.store` is null. |

An application with a firewall and the defaults thus gets the account deletions, the proofs of consent and the
register of incidents; one without `symfony/security-bundle` gets the proofs and the register. Without Doctrine DBAL,
none: the proofs of consent become log lines, and the other features need a store of the application.

## Upgrading to 0.4

**The tables follow the features.** A table of a feature that is off, or kept by a store of the application, is no
longer in the schema of the ORM: the next `doctrine:migrations:diff` writes a `DROP TABLE` for it. Read that migration
before running it. An application without `symfony/security-bundle` that used the account deletions keeps them, and
`phil_privacy_account_deletion`, with `account_deletion.enabled: true`; a table the application wants to keep without
its feature leaves the comparison with the `schema_filter` of DoctrineBundle.

**The keys.** Every key the bundle derives from the application secret (`kernel.secret`) is now its HKDF-SHA-256
under a label of its own (see [Threat model](threat-model.md#the-keys)). What was sealed or signed under the keys of
0.3 no longer opens:

- **Requests without an account** in `phil_privacy_guest_request` read as absent: their links no longer open them.
  Empty the table, or let `link_lifetime` and the purge at each new request remove them, and ask the people who were
  waiting to make their request again.
- **Export archives** kept by `EncryptedExportArchive` (`account_deletion.export_directory`) read as absent. Delete
  the files of that directory, or let the sweep remove them at their expiry.
- **The consent cookie** reads as no choice: each visitor sees the banner once more and chooses again. The proofs of
  `phil_privacy_consent_proof` are kept as they are.
- **The rate limits** of requests without an account start again from zero.

An empty `kernel.secret` now stops the bundle with `InvalidPrivacyValue` when one of these services is built, instead
of deriving keys anyone can compute.

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
