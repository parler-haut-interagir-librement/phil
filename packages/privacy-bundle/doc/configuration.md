# Configuration reference

The extension alias is `phil_privacy`. The configuration tree is defined in `PhilPrivacyBundle::configure()`
and has these keys: `log_redaction`, `public_origin`, `retention_schedule_source`, `retention`, `account_deletion`, `consent`,
`cookies`, `guest_requests`, `incidents`, `email_tracking`, `rights`, `pages`, `register`, `database` and
`anonymization`.

## Top-level keys

### `phil_privacy.public_origin`

The canonical address of the site, such as `https://example.org`, that the links sent by email point to, or `null`
(the default): the host of the request, which `framework.trusted_hosts` must then bound. In the `prod` environment,
while `guest_requests` is on with the mailer sender, the bundle refuses to boot when neither is set. See
[Where the link points](requests-without-an-account.md#where-the-link-points).

### `phil_privacy.retention_schedule_source`

A service id of a `RetentionScheduleSource`, or `null` (the default). `ProcessingRegister` reads the schedule from it
to show each rule's store, period, mechanism and answer to erasure; without it, the register names the rules only.

### `phil_privacy.retention`

| Key | Default | Effect |
|---|---|---|
| `frequency` | `1 day` | How often the schedule `phil_privacy` applies the retention schedule. |
| `rules` | `[]` | The retention schedule, by rule identifier, with what the register of processing says of each store (`purpose`, `recipients`, `processors`, `location`); see [retention.md](retention.md). Refused together with `retention_schedule_source`. |

### `phil_privacy.account_deletion`

| Key | Default | Effect |
|---|---|---|
| `delay` | `P30D` | How long a request can be cancelled. A zero duration is refused. |
| `frequency` | `15 minutes` | How often the schedule `phil_privacy` looks for what is due. |
| `store` | `null` | Service id of an `AccountDeletionStore`; `null`, the table of the bundle. |
| `export_archive` | `null` | Service id of an `ExportArchive`; `null`, encrypted files. |
| `export_directory` | `%kernel.project_dir%/var/privacy-exports` | Where the encrypted files are written. |
| `export_retention` | `P7D` | How long an export is kept. |

See [account-deletion.md](account-deletion.md).

### `phil_privacy.consent`

| Key | Default | Effect |
|---|---|---|
| `version` | `1` | The version of the text; change it and everyone is asked again. |
| `cookie_name` | `phil_privacy_consent` | The cookie that keeps the choice. |
| `lifetime` | `P6M` | How long a choice is kept. |
| `proofs` | `null` | Service id of a `ConsentProofs`; `null`, the table of the bundle, or the log without a database. |
| `categories` | `[]` | By name: `necessary`, `labels` and `descriptions` by locale. |

See [consent.md](consent.md).

### `phil_privacy.cookies`

| Key | Default | Effect |
|---|---|---|
| `inventory` | `null` | The YAML file of the cookie inventory; `null`, `config/privacy/cookies.yaml` of the project. A file that does not exist declares nothing. |
| `watch` | `null` | Logs a warning for a response that sets an undeclared cookie; `null`, when `kernel.debug` is true. |

See [cookie-inventory.md](cookie-inventory.md).

### `phil_privacy.guest_requests`

The access and erasure requests of people without an account, off by default; every key is in
[requests-without-an-account.md](requests-without-an-account.md#the-link).

### `phil_privacy.incidents`

`store`: the service id of an `IncidentRegister`; `null` (the default), the table of the bundle. See
[incidents.md](incidents.md).

### `phil_privacy.email_tracking`

Each member's consent to tracked emails, off by default. See [email-tracking.md](email-tracking.md).

| Key | Default | Effect |
|---|---|---|
| `enabled` | `false` | Asks on the preferences page, and registers `EmailTrackingConsents`. |
| `version` | `'1'` | The version of the text the member reads; changing it stops every earlier choice until the member chooses again. |
| `store` | `null` | The service id of an `EmailTrackingStore`; `null`, the table `phil_privacy_email_tracking`. |

### `phil_privacy.rights`

> [!CAUTION]
> **The export and the erasure of a member are on by default, and they should stay on.** Most privacy laws give every
> person the right to obtain and to erase their data; turning one off takes that right away from every member at once.
> The bundle makes it a deliberate, visible gesture: no boolean, the full sentence below, a framed warning at every
> container build and a red `DANGER` line before every console command.

`export.disable` and `erasure.disable` are `null`, the right being on. Each accepts one value: the full sentence of
`PrivacyRights`, which the build error quotes. See [preferences.md](preferences.md).

### `phil_privacy.pages`

| Key | Default | Effect |
|---|---|---|
| `auto_mount` | `true` | Mounts the pages; `false`, the application imports `@PhilPrivacyBundle/config/routes.php`. |
| `prefix` | `/privacy-choices` | The path of the pages. |
| `layout` | `@PhilPrivacy/layout.html.twig` | The template the pages extend. |
| `current_subject` | `null` | Service id of a `CurrentDataSubject`; `null`, the signed-in user through `DataSubjectUser`. |
| `clock` | `clock` | Service id of the PSR-20 clock. |

### `phil_privacy.register.classes`

A list of class names whose `#[PersonalData]` the register reads, beside every Doctrine entity; `[]` by default. A
value that is not a string fails the build: `phil_privacy.register.classes must be a list of strings.`

### `phil_privacy.database`

The database that `phil:privacy:anonymize` works on ([anonymization.md](anonymization.md)).

| Key | Type | Default | Effect |
|---|---|---|---|
| `connection` | non-empty string | `database_connection` | Service id of the Doctrine DBAL connection. When it is not a service of the application, the database services and the command are removed rather than the container failing. |

### `phil_privacy.anonymization`

How `phil:privacy:anonymize` rewrites personal data ([anonymization.md](anonymization.md)).

| Key | Type | Default | Effect |
|---|---|---|---|
| `locale` | non-empty string | `en_US` | The locale pack the anonymizers draw from, unless a column gives a `locale` option. |
| `batch_size` | integer, at least 1 | `500` | Rows read per query. |
| `categories` | map of personal data category to anonymizer type, keys kept as written | `{}` | The anonymizer of each `#[PersonalData]` field without `#[Anonymize]`. |
| `tables` | map of table to map of column to a type or `{ type, options }`, keys kept as written | `{}` | Rules that replace those of the attributes and the categories, and reach tables no entity maps. |
| `guard.environments` | list of strings | `[prod]` | Kernel environments in which anonymization is refused. |
| `guard.hosts` | list of strings | `[]` | Database hosts on which anonymization is refused. |
| `guard.databases` | list of strings | `[]` | Database names, or SQLite files, on which anonymization is refused. |

## Complete annotated example

```yaml
# config/packages/phil_privacy.yaml
phil_privacy:
    log_redaction:
        # Keys whose values never reach a log line. Compared without case or separators
        # ("_", "-", ".", spaces), and exactly: "code" does not cover "postal_code".
        # An entry that starts and/or ends with "*" is a pattern over a family of keys.
        denylist:
            - '*password*'      # password, plainPassword, db_password, --db-password ...
            - '*token*'         # token, refresh_token, csrfToken ...
            - '*secret*'
            - '*seed*'
            - '*authorization*' # Authorization, Proxy-Authorization headers
            - '*cookie*'
            - code              # exact: postal_code and country_code stay readable
            - '*apikey'         # api_key, X-Api-Key
            - query_string      # a raw query string has no "?" to be recognised by
        # Path prefixes whose next segment is a token: "/p/abc/edit" becomes "/p/[redacted]/edit".
        token_path_prefixes:
            - /p/
        # Short command line options: each letter to the long option name it abbreviates.
        # The value is redacted only when the denylist denies that long name.
        short_options:
            p: password
    database:
        connection: database_connection   # a Doctrine DBAL connection service
    anonymization:
        locale: fr_CA
        categories: { email: email, person_name: name, phone: phone }
        tables:
            member:
                note: { type: lorem, options: { words: 6 } }
        guard:
            environments: [prod, staging]
            hosts: [db.example.com]
```

## Keys

### `phil_privacy.log_redaction`

Array node, defaults added if not set: omitting the section, or the whole `phil_privacy` key, is the same as
writing every child with its default. Any other key under `phil_privacy` is refused by the Config component
(`Unrecognized option ... under "phil_privacy"`).

### `phil_privacy.log_redaction.denylist`

| | |
|---|---|
| Type | list of scalars, each one a string |
| Default | `[]` |
| Effect | Builds the `Denylist` used by every redactor. |

- An entry is normalised by trimming it, dropping `_`, `-`, `.` and spaces, and lowercasing it. Two entries
  with the same normalised form are one entry.
- `stem*` denies every key that starts with the stem, `*stem` every key that ends with it, `*stem*` every key
  that contains it. The stem is normalised like a key.
- A `*` anywhere else (`se*ed`) fails the build, and so does an entry that is empty once normalised (`--`, `*`).
- Lists from several configuration files are concatenated, as with any prototyped list in Symfony.
- The same list governs three things: context and `extra` keys, long option names in command lines, and the
  long names that `short_options` points to. See [log-redaction.md](log-redaction.md).

### `phil_privacy.log_redaction.token_path_prefixes`

| | |
|---|---|
| Type | list of scalars, each one a string |
| Default | `[]` |
| Effect | Builds the `UrlRedactor`: the path segment after each prefix is replaced by `[redacted]`. |

- A prefix is one or more path segments between slashes: `/p/`, `/share/link/`. It must start and end with `/`
  and contain no whitespace, `?` or `#`; anything else (`p`, `/p`) fails the build.
- A prefix matches only where a path starts: at the start of a string, after whitespace, a quote, `(`, `=` or
  `<`, or right after a scheme and host. `/shop/p/abc` is not under `/p/`.

### `phil_privacy.log_redaction.short_options`

| | |
|---|---|
| Type | map of letter to string; keys are kept as written (`normalizeKeys(false)`) |
| Default | `{}` |
| Effect | The short option table of the `CommandLineRedactor`. |

- Each key must be exactly one ASCII letter, case-sensitive (`p` and `P` are two options). `pw` or `-p` fails
  the build.
- Each value must be a long option name without its dashes, matching `[A-Za-z0-9][A-Za-z0-9_.-]*`
  (`password`, `db-password`). A non-string value fails the build.
- A letter is redacted only if the denylist denies its long name; a letter mapped to an allowed name
  (`P: port`) is accepted and has no effect.
- Empty, which is the default, no short option is read at all.

## Services wired

| Service id | Class | Visibility | Definition |
|---|---|---|---|
| `Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor` | `RedactingProcessor` | private | Built by the factory `RedactingProcessor::fromConfiguration($denylist, $tokenPathPrefixes, $shortOptions)`, tagged `monolog.processor` with no attributes. |
| `Phil\PrivacyBundle\Application\SubjectExport`, `SubjectErasure` | — | public | Over every service tagged `phil_privacy.exporter`, `phil_privacy.eraser`: the tags autoconfiguration gives `PersonalDataExporter` and `PersonalDataEraser`. |
| `Phil\PrivacyBundle\Application\Register\ProcessingRegister` | — | public | Over `retention_schedule_source`, or none. |
| `ExportSubjectCommand`, `EraseSubjectCommand`, `ProcessingRegisterCommand` | — | private | `phil:privacy:export`, `phil:privacy:erase`, `phil:privacy:register`, with Symfony Console; the last with `register.classes` and the `doctrine` registry when there is one. |
| `Phil\PrivacyBundle\Domain\Anonymization\Anonymizers` | — | private | The built-in anonymizers with every service tagged `phil_privacy.anonymizer`, by its `type` attribute: the tag `#[AsAnonymizer]` gives. |
| `Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePacks` | — | private | The built-in packs with every `LocalePack` service, tagged `phil_privacy.locale_pack` by autoconfiguration. |
| `Phil\PrivacyBundle\Application\Anonymization\AnonymizationRun` | — | public | Over `Anonymizers`, `LocalePacks` and `anonymization.locale`. |
| `phil_privacy.database_identity` | `DatabaseIdentity` | private | Built by `ConnectionIdentity::of()` from `database.connection`, without connecting. |
| `DbalAnonymizationTarget`, `AnonymizationPlanReader`, `ProductionGuard` | — | private | From `database.connection` and `anonymization.batch_size`, the `doctrine` registry when there is one with `categories` and `tables`, `%kernel.environment%` and `guard`. |
| `AnonymizeDatabaseCommand` | — | private | `phil:privacy:anonymize`. |
| `RetentionRun`, `RetentionJob` | — | public | Over `retention_schedule_source` or `retention.rules`, and every service tagged `phil_privacy.retention_purger`; absent without a schedule. |
| `AccountDeletions` | — | public | The workflow, `lock.factory`, `SubjectExport`, `SubjectErasure`, the store and the archive; absent without a store, a lock or a clock. |
| `phil_privacy.account_deletion_workflow` | `StateMachine` | public | Tagged `workflow`, named `phil_privacy_account_deletion`. |
| `ConsentContext`, `ConsentPolicy` | — | public | The consent of the visitor, and the categories and version of the configuration, each category with the names the inventory gives it. |
| `CookieInventory` | — | public | The inventory of `cookies.inventory`, with the consent cookie. |
| `UndeclaredCookieListener` | — | private | On `kernel.response`, with `cookies.watch` or in debug, when there is a logger. |
| `EmbedExtension` | — | private | `privacy_embed()`. |
| `ExportPackage`, `ExportReadme` | — | public, private | The archive of an access answer; the README by Twig, or in plain English without it. |
| `IncidentRegister`, `DbalIncidentRegister`, `IncidentCommand`, `IncidentsCommand` | — | public alias, private | The register of incidents and its commands; absent without a database unless a store is named. |
| `EmailTrackingConsents`, `EmailTrackingStore`, `DbalEmailTrackingConsents` | — | public, private alias, private | The consent to tracked emails, tagged `phil_privacy.exporter` and `phil_privacy.eraser`; only when `email_tracking.enabled`. |
| `GuestRequests`, `GuestRequestController`, `DbalGuestRequests`, `MailerGuestLinkSender`, `phil_privacy.guest_requests.per_client`, `phil_privacy.guest_requests.per_address` | — | public, private | The requests without an account, their pages and their two rate limiter factories; `GuestRequests` and the limiters only when `guest_requests.enabled`. |
| `PrivacySchedule` | — | private | The schedule `phil_privacy`, with `symfony/scheduler` and a message bus. |

The database services and the command are registered when Symfony Console and Doctrine DBAL are installed, and
removed by `DatabaseServicesPass` when `database.connection` is not a service.

`PrivacyServicesPass` takes out what an application cannot run: without a database the proofs of consent become log
lines, and the deletions, the incidents and the requests without an account need a named store; without Twig, CSRF
tokens and a router there are no pages; without a firewall the consent, the cookie policy and the requests without an
account remain. The requests without an account, once enabled, need every one of their parts — a store, a sender, the
lock, the clock, the cache pool, and Twig, the router and the mailer's transports for the mailer sender —, and a
missing one fails the build with a `LogicException` that names it. The consent to tracked emails, once enabled, needs a
store and the clock, in the same way.

With AssetMapper, the bundle adds its `assets/src` directory to the paths of `asset_mapper` under the namespace
`@phil/privacy-bundle`, so that StimulusBundle finds the controllers `consent` and `embed` its recipe enables.

The `monolog.processor` tag carries no `channel`, `handler` or `priority` attribute, so MonologBundle adds the
processor to every channel logger at the default priority. See [log-redaction.md](log-redaction.md) for what
that means for processor order.

## Build-time validation

The extension builds the processor once while loading, so a malformed entry fails the container build rather
than the first log line. Every error is an
`Symfony\Component\Config\Definition\Exception\InvalidConfigurationException`:

| Cause | Message (start) |
|---|---|
| A denylist entry that is not a string (a number, a boolean) | `phil_privacy.log_redaction.denylist must be a list of strings.` |
| A token path prefix that is not a string | `phil_privacy.log_redaction.token_path_prefixes must be a list of strings.` |
| A short option value that is not a string | `phil_privacy.log_redaction.short_options must map letters to long option names.` |
| A `*` inside a denylist stem | `phil_privacy.log_redaction: "se*ed" is not a denylist key: "*" may only open or close a pattern ...` |
| A denylist entry empty once normalised | `phil_privacy.log_redaction: "--" is not a denylist key: nothing is left once case, separators and pattern stars are dropped.` |
| A malformed token path prefix | `phil_privacy.log_redaction: "p" is not a token path prefix: expected one or more path segments between slashes, such as "/p/".` |
| A short option key that is not one letter | `phil_privacy.log_redaction: "pw" is not a short option: a short option is one ASCII letter, such as "p".` |
| A malformed anonymizer type under `anonymization.tables` | `phil_privacy.anonymization: "E-mail" is not an anonymizer type: lowercase letters, digits and underscores, starting with a letter.` |
| A table rule whose options are not named scalars | `phil_privacy.anonymization.tables.member.note needs a type, and named options whose values are scalars or null.` |
| A category mapped to something other than a string | `phil_privacy.anonymization.categories must map names to strings.` |
| A right turned off with anything but its sentence | `phil_privacy.rights.export.disable must be exactly this sentence: "…".` |
| A duration that is not one, or is zero | `phil_privacy.account_deletion.delay: "x" is not an ISO 8601 duration …` |
| A malformed category name or text version | `phil_privacy.consent: "A B" is not a consent category …` |
| A retention line the model refuses | `phil_privacy.retention.rules: …` |
| Both a source and rules | `phil_privacy: name retention_schedule_source or write retention.rules, not both …` |
| A cookie inventory the model refuses, or a file that is not YAML of a map | `phil_privacy.cookies.inventory: …` |
| The requests without an account turned on without a sender | `phil_privacy.guest_requests.from is the sender of the links …` |
| The requests without an account turned on without the rate limiter | `phil_privacy.guest_requests needs symfony/rate-limiter …` |
| A link lifetime that is not a duration | `phil_privacy.guest_requests.link_lifetime: …` |
| An origin with a path, a query, a fragment or an IP address, or `localhost` outside debug mode, read at boot | `"phil_privacy.public_origin" is an origin …`, `"phil_privacy.public_origin" is the canonical address of the site …` |
| In `prod`, the requests on with the mailer sender, neither `public_origin` nor `framework.trusted_hosts`, at boot | `The links of the requests without an account would take the host of the request: …` |
| A malformed version of the text about tracked emails | `phil_privacy.email_tracking.version: letters, digits, ".", "_" and "-", 32 at most, …` |
| A malformed long option name | `phil_privacy.log_redaction: The short option "p" must map to a long option name without its dashes, such as "password".` |

The domain errors behind the `phil_privacy.log_redaction:` messages are `InvalidPrivacyValue`, kept as the
previous exception. A value of the wrong shape (a string where a list is expected, an array as a short option
value) is refused earlier by the Config component itself, with its own `InvalidTypeException`.
