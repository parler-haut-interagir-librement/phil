# phil/privacy-bundle

**Declare personal fields, redact logs, honour the rights of members and of people without an account, apply
retention periods, keep the registers, ask consent to cookies, and anonymize database copies from code.**

phil/privacy-bundle puts privacy law into a Symfony application that holds personal information. A `#[PersonalData]`
attribute declares a personal field, and an application test can check that every one has a retention rule. A
Monolog processor redacts denied keys, query strings, URL credentials, token path segments, command line options and
exception messages before a line is written. Subject access and erasure answers are assembled from one contract per
module, wired by autoconfiguration and run from the console. The retention schedule declares the register of
processing — purpose, recipients, processors and location of each store —, which the command prints with the
declared fields and fails on in CI while a gap remains; it also becomes purge orders, with a run refused when a job
line has no purger. A register keeps every confidentiality incident with the eight paragraphs Québec asks of each
line, names the notifications and paragraphs still owed, keeps its corrections and a journal of the communications to
a third party, the notices put off for an investigation and the consultations of the person in charge, and keeps each
line at least five years; drafts of the notices to the authority and to the people are written from it in Markdown,
and sent by no one but the person in charge. A copy of the database is anonymized column by column — emails, names,
telephones in fictional ranges, addresses, IBANs, shifted dates, hashes, placeholder text — from `#[Anonymize]`, the
declared categories and the configuration, with locale packs for eight locales and a guard that refuses to touch a
production database.

Out of the box, a member downloads their data, as a ZIP archive with a readable README and one JSON file per module,
and deletes their account from their preferences. A deletion is a workflow: a request, a delay during which it can be
cancelled, an export, then the erasure of every module, one execution per person, confirmed by Symfony's sudo mode.
Once turned on, the erasure itself runs as a workflow: one step per module, sent through phil/outbox-bundle in the
transaction of the request, retried and finished on its own, external services acknowledging theirs, with a page and a
JSON answer where the person follows it.
Once turned on, a person without an account asks for the same through a link sent to their address, behind rate
limits and with the same answer whether the address is known or not. Retention periods are applied by a command or a
scheduled message. Consent to cookies is refused by default, as easy to refuse as to accept, can be withdrawn at any
time, respects the Global Privacy Control, and leaves a dated proof of the version of the text. Every cookie is
declared in a YAML inventory from which the banner and the cookie policy are drawn, and a functional test fails on a
cookie it does not declare. A video or a map of another site waits for its consent behind a preview the site serves
itself. Once turned on, each member chooses on the same page whether the emails sent to them may count their opens or
follow their clicks, each purpose refused until they tick it. The schema gets only the tables of the features in use:
the account deletions where symfony/security-bundle gives the site accounts, none for a feature kept by a store of the
application.

It exists because privacy laws such as Québec's Law 25 give a person the right to see and erase what a business holds
about them, and expect the business to know what it keeps and for how long. Keeping that knowledge in the code keeps
it true as the code changes, and a developer who needs real data gets a copy in which no person can be found. It is
for the developers who build those applications, and for the person in charge of privacy who answers the requests
and keeps the register.

The bundle does not decide your denylist, category vocabulary or retention schedule. It does not know what a module
holds: each module exports and erases its own records through a port, and each store is purged by its own adapter. It
does not verify the identity of someone who writes in by other means than the site, nor back the database up. For a
tamper-evident record of who did what, pair it with `phil/audit-bundle`; to back the database up and restore a copy to
anonymize, with `phil/backup-bundle`.

It is a Symfony bundle on Monolog, Workflow, Lock, Messenger, Yaml, the Security components, Twig and
`phil/email-address` and `phil/url`, with the Console, Scheduler, Rate Limiter and Mailer components, Doctrine
Persistence, DBAL and ORM as options. It is a skeleton of ports and adapters: it carries what the design decides, and
ports such as `PersonalDataExporter`, `PersonalDataEraser`, `RetentionPurger`, `Anonymizer` and `AnonymizationTarget`
for what the application decides.

> [!CAUTION]
> A member's export and erasure are **on by default** on every site. Turning one off takes a legal right away from every
> member and needs the full sentence of [Turning a right off](doc/preferences.md#turning-a-right-off); every build and
> console command then warns about it.

## Status

| Area | State |
|---|---|
| `#[PersonalData]`, `DeclaredPersonalFields`, `PersonalField` | Implemented and tested |
| `DataSubject`, `DataCategory`, `ModuleName`, `ErasureReport` | Implemented and tested |
| `Denylist`, `UrlRedactor`, `CommandLineRedactor`, `ContextRedactor` | Implemented and tested |
| `RetentionPeriod`, `RetentionRule`, `RetentionSchedule`, `PurgeOrder` and the enums | Implemented and tested |
| `RedactingProcessor` | Implemented, tested, wired from configuration and tagged `monolog.processor` |
| `SubjectExport`, `SubjectErasure` | Implemented, tested, wired over every tagged module |
| `phil:privacy:export`, `phil:privacy:erase` | Implemented and tested |
| `ProcessingRegister`, `ProcessingActivity`, `phil:privacy:register` | Implemented and tested: the register of processing from the schedule and the declarations, Markdown or JSON, `--strict` |
| `RetentionRun`, `RetentionJob`, `phil:privacy:retention`, the schedule `phil_privacy` | Implemented, tested and wired from a schedule |
| `AccountDeletions`, the workflow, `phil:privacy:deletions` | Implemented, tested and wired |
| The erasure workflow: `ErasureRequests`, one outbox message per module, the progress page and JSON, `phil:privacy:erasure` | Implemented, tested and wired; off until enabled |
| The consent to cookies: banner, page, Twig functions, Stimulus controller, proofs | Implemented, tested and wired |
| The cookie inventory, the cookie policy, `AssertsDeclaredCookies` | Implemented, tested and wired |
| `privacy_embed()`: third-party frames held until consent | Implemented, tested and wired |
| A member's export and erasure from their preferences, as a ZIP archive | Implemented, tested and on by default |
| The requests of people without an account, by a verified link | Implemented, tested and wired; off until enabled |
| The register of confidentiality incidents, `phil:privacy:incident`, `phil:privacy:incidents`: the eight paragraphs of section 7 of the Québec regulation (r. 3.1), corrections kept, five years at least (section 8) | Implemented, tested and wired |
| The journal of an incident, `phil:privacy:incident-journal`: communications to a third party, notices put off for an investigation and consultations of the person in charge (P-39.1, sections 3.5 and 3.7) | Implemented, tested and wired |
| Drafts of the notices of an incident, `phil:privacy:incident-notice`: to the Commission, its follow-ups, to each person and in public (r. 3.1, sections 3 to 6), in Markdown, French and English, filled from the register; nothing is sent | Implemented, tested and wired |
| Each member's consent to tracked emails, `EmailTrackingConsents` | Implemented, tested and wired; off until enabled |
| The Stimulus controllers through AssetMapper and StimulusBundle | Implemented and tested |
| `PersonalDataExporter`, `PersonalDataEraser`, `ExternalPersonalDataEraser`, `RetentionScheduleSource`, `RetentionPurger`, `ErasureRequestStore` | Ports |
| `#[Anonymize]`, `#[AsAnonymizer]`, the thirteen anonymizers, the eight locale packs | Implemented and tested |
| `AnonymizationPlanReader`, `AnonymizationRun`, `ProductionGuard`, `ConnectionIdentity` | Implemented, tested and wired |
| `phil:privacy:anonymize` | Implemented and tested on SQLite; MySQL, MariaDB and PostgreSQL through Doctrine DBAL |
| `PhilPrivacyBundle` | Everything above, with its configuration |

## Documentation

- [Installation](doc/installation.md) — requirements, installing with Composer, registering the bundle
  alongside MonologBundle.
- [Configuration](doc/configuration.md) — every key of `phil_privacy`, its default and effect, the services it
  wires, and each build-time error.
- [Declaring personal data](doc/personal-data-declaration.md) — the `#[PersonalData]` attribute, reading
  declarations through parents, and the inventory test that fails on uncovered fields.
- [Encrypting personal data at rest](doc/encrypted-columns.md) — which columns to encrypt with `#[Encrypted]` of
  Symfony's KeyManagement component, blind indexes with `#[BlindIndexed]` for searching, and rotation.
- [Log redaction](doc/log-redaction.md) — what the processor redacts and why, the denylist comparison and
  patterns, URL and command line rules, throwables, and processor order in Monolog.
- [Subject export and erasure](doc/export-and-erasure.md) — `DataSubject`, the per-module contracts,
  `SubjectExport`, `SubjectErasure` with failure isolation, how the bundle wires them, the register of processing,
  and the ZIP archive of an access answer.
- [Retention schedule and purges](doc/retention.md) — periods and UTC cutoffs with month clamping, schedule
  lines and their refused contradictions, `RetentionRun`, and running the job.
- [Erasure as a workflow](doc/erasure-workflow.md) — the erasure inventory and its version, one outbox message per
  module, the states, failures and replays, external services and their acknowledgement, the progress page, and the
  migrations for MySQL and PostgreSQL.
- [Choosing a control](doc/choosing-a-control.md) — which tool answers which problem and where it stops: erasing,
  retention, redacting, pseudonymising and anonymising, encryption and blind indexes, backups.
- [Deleting an account](doc/account-deletion.md) — the workflow, the delay and the cancellation, what guarantees one
  execution per person, running the sweep, and where the export goes.
- [Consent to cookies](doc/consent.md) — declaring categories, the Twig functions, the rules, the cookie and the
  proof, working without JavaScript, and overriding the templates.
- [The cookie inventory](doc/cookie-inventory.md) — the YAML file, the banner, the page and the policy drawn from it,
  and the test that fails on an undeclared cookie.
- [Third-party frames](doc/third-party-frames.md) — `privacy_embed()`, the preview served by the site, the players
  that write fewer trackers, and the Stimulus controller.
- [Requests without an account](doc/requests-without-an-account.md) — the verified link, the steps, the guards
  against abuse, and who a verified address is.
- [The register of incidents](doc/incidents.md) — what the Québec law and regulation ask (P-39.1, sections 3.5 to
  3.8; r. 3.1), recording, completing, correcting and printing a line, the notifications a serious risk asks for, the
  journal of an incident, the five years it is kept, and the migration of its tables.
- [The drafts of the notices of an incident](doc/incident-notices.md) — the notices to the Commission, their
  follow-ups, to each person and in public (r. 3.1, sections 3 to 6), the rubrics filled from the register and those
  left to complete, who decides and who sends, and overriding the templates.
- [Tracked emails](doc/email-tracking.md) — each member's consent to the counting of opens and the following of
  clicks, what a sender asks, the version of the text, and its export and erasure.
- [Legal framework](doc/legal-framework.md) — ePrivacy and the CNIL, Québec's Law 25, the GDPR and the proposed
  Digital Omnibus, and what the default configuration does about each.
- [Export and erasure from the preferences](doc/preferences.md) — the routes, the current member, the sudo mode, and
  what it takes to turn a right off.
- [Anonymization](doc/anonymization.md) — the plan from attributes, categories and configuration, every
  anonymizer and its options, the locale packs, the command, the production guard, and a copy to share.
- [Extension points](doc/extension-points.md) — the ports method by method, custom anonymizers and locale packs, the
  invariants an adapter keeps, example adapters, and the service ids you can rely on.
- [Testing](doc/testing.md) — how the bundle is tested, running its tests in a monorepo or alone, and how to
  test your adapters and configuration.
- [Threat model](doc/threat-model.md) — what the bundle protects, from whom, the keys it derives and the risks it
  leaves to the application.
- [API reference](doc/reference.md) — every public type by layer, with its role and state.

## Quick start

```bash
composer require phil/privacy-bundle:^0.1@dev
```

```yaml
# config/packages/phil_privacy.yaml
phil_privacy:
    log_redaction:
        denylist: ['*password*', '*token*', '*secret*', '*authorization*', '*cookie*', query_string]
        token_path_prefixes: ['/p/']
        short_options: { p: password }
```

```php
final class Member
{
    #[PersonalData('email', retention: 'R-002')]
    private string $email;
}

DeclaredPersonalFields::uncoveredIn(Member::class, $schedule); // [] once every field has a rule
```

```php
#[PersonalData('email', retention: 'R-002')]
#[Anonymize('email', options: ['domain' => 'example.org'])]
private string $email;
```

```bash
bin/console phil:privacy:anonymize --dry-run           # the plan, and what it leaves in clear
bin/console phil:privacy:anonymize --confirm           # never in production
```

A malformed configuration entry fails the container build, not the first log line.

## Requirements

PHP 8.5 or later with `ext-sodium` and `ext-intl`, `monolog/monolog` 3, Symfony 8.2 and Twig 3. The requests without an
account need `symfony/rate-limiter`, and `symfony/mailer` for the bundle's sender, whose links point to `public_origin`
or to a host `framework.trusted_hosts` accepts: in production the bundle refuses to boot without either (see
[Where the link points](doc/requests-without-an-account.md#where-the-link-points)). MonologBundle is needed for the
processor to reach your loggers through its tag, a firewall for a member to reach their preferences, and a database for
the account deletions unless a store is named. See [Installation](doc/installation.md).

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
