# Configuration

```yaml
phil_accessibility_statement:
    source: config              # or doctrine
    connection: default         # the DBAL connection of the doctrine source
    clock: clock                # the PSR-20 clock that dates a regenerated version
    default_locale: en          # the language a text falls back to
    wording: wcag               # or en-301-549, aca
    layout: '@PhilAccessibilityStatement/layout.html.twig'
    cache_max_age: 3600         # seconds; 0 makes caches ask each time
    feedback_route: ~           # such as phil_contact_form
    feedback_form: ~            # the key of a form of phil/forms-bundle
    kit_subdirectory: ~         # the subdirectory of the recipes; the Toolkit's when null
    statement: ~                # the statement, as StatementReader reads it
    audit:
        enabled: false
        source: ~               # the service id of an AuditSource of the application's own
        directory: '%kernel.project_dir%/var/accessibility'
        minimum_severity: error # or warning, notice
        method: automated       # or self-assessment, third-party
        report_url: ~
    admin:
        enabled: false
        role: ROLE_ADMIN        # the role Symfony Security grants to open the page
```

| Key | Means |
|---|---|
| `source` | `config`: the statement below is the only version, read only. `doctrine`: the versions are kept in the table `phil_accessibility_statement` ([Doctrine](doctrine.md)), the statement below being a version while the table holds none of its number. |
| `connection` | The DBAL connection of the table. |
| `clock` | The service id of the clock that dates a regenerated version. |
| `default_locale` | The language whose text the page shows when a text has none in the request's language. |
| `wording` | The wording of the page ([The page](page.md#wording)). |
| `layout` | The Twig layout the page extends; it defines the `title` and `content` blocks. |
| `cache_max_age` | How long browsers and shared caches may keep the page: `Cache-Control: public, max-age=…`, or `no-cache` for 0. |
| `feedback_route` | The route of a page that takes feedback, linked first among the channels: the contact form of `phil/contact-form-bundle`, `phil_contact_form`, or a page of the application. |
| `feedback_form` | The key of a form of `phil/forms-bundle`, 1 to 64 lower-case letters, digits or `-`: the page links to its page, the route `phil_forms_form`, before `feedback_route`. |
| `kit_subdirectory` | Where the recipes of the kit are, under the components directory: `ui` for `ui:Card`. Null reads the subdirectory of the `ux_toolkit` configuration; set it where the Toolkit is not enabled. |
| `admin` | The administration page ([Administration](administration.md)): `enabled`, and the `role` that opens it. |
| `statement` | The statement with the keys `StatementReader` of `phil/accessibility-statement` reads (its documentation, Plain data, lists them). With the `config` source and no statement, none is published yet: the page answers 404 and the footer does not link to it. |
| `audit` | The latest audit the command reads ([Console](console.md)): a directory of reports, or an `AuditSource` service. |

The statement is read when the container is built: a statement that cannot be published fails the build with the
reason, as `phil_accessibility_statement.statement: A statement that lists no criterion not met claims full
conformance.`

YAML reads an unquoted date such as `2026-11-08` as a timestamp: the bundle writes `published_at`,
`evaluation.evaluated_at` and the `fix_by` of each non-conformity back as the moment they are, at midnight UTC. Quote
them to keep the date as it is written.

The container reads `%name%` in a string of the configuration as a parameter: a percent sign of a text is written
`%%`, as in `Captions cover 80%% of the videos.`

## A complete statement

```yaml
phil_accessibility_statement:
    wording: en-301-549
    feedback_route: phil_contact_form
    statement:
        version: 3
        published_at: '2026-11-08'
        standard: wcag-2.1
        level: AA
        entity: Example Org
        scope: { en: the site example.com and its booking application }
        feedback:
            email: accessibility@example.com
            phone: '+1 514 555 0100'
            postal_address: { en: "1 Main Street\nMontréal QC" }
            response_days: 10
        non_conformities:
            - criterion: '1.2.2'
              description: { en: Videos published before 2024 have no captions. }
              alternative: { en: A transcript is sent on request. }
              fix_by: '2027-06-30'
            - criterion: '2.1.1'
              reason: out-of-scope
              description: { en: The map of a partner cannot be used with a keyboard. }
        alternatives:
            - { en: 'Call us: we book for you by telephone.' }
        enforcement:
            procedure: { en: If our answer does not satisfy you, you may refer the matter to the authority. }
            authority: { en: The national accessibility authority }
            url: https://example.org/complaints
        evaluation: { evaluated_at: '2026-10-30', method: third-party, pages: 40, report_url: /accessibility/report.pdf }
```

## The services

| Service | When |
|---|---|
| `StatementRepository` (alias, public) | Always: `ConfiguredStatements` or `DbalStatements`. |
| `StatementController` | Always: the page. |
| `StatementExtension`, `KitComponents` | Always: the Twig functions and filter, the names of the recipes. |
| `StatementSchemaListener` | With the `doctrine` source. |
| `AuditSource` (alias) | With `audit`: `ReportDirectory`, or the service named. |
| `StatementRegenerator` (public) | With `audit`. |
| `RegenerateCommand` | With `audit`, when `symfony/console` is installed. |
| `StatementPublisher` (public), `StatementAdminController` | With `admin`. |
