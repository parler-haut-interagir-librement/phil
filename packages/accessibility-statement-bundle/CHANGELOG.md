# Changelog

All notable changes to `phil/accessibility-statement-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-15

### Added

- `PhilAccessibilityStatementBundle` and its configuration `phil_accessibility_statement`: the source of the versions,
  the connection, the clock, the default locale, the wording, the layout, the cache age, the feedback route, the
  feedback form of `phil/forms-bundle`, the subdirectory of the recipes of the kit, the administration page, the
  statement read and checked when the container is built — unquoted YAML dates read back as moments —, and the audit.
- `Domain\Contract\StatementRepository` — `latest()`, `find()`, `publications()`, `writable()`, `add()` — and
  `StatementNotStored`; `Infrastructure\Config\ConfiguredStatements`, the statement of the configuration, read only,
  none published while the configuration holds none;
  `Infrastructure\Doctrine\DbalStatements`, the versions in the table `phil_accessibility_statement`, inserted once,
  listed by number and moment without reading the documents, the configuration a version while the table holds none of
  its number, a newest row that no longer reads leaving no version in force and a warning with its number in the
  log, the moment of each publication written and read back in UTC; `StatementTables` and
  `StatementSchemaListener`, the table added to the schema of the ORM.
- `Infrastructure\Http\StatementController` and `config/routes.php`: the version in force at `/accessibility` and
  `/accessibilite`, each version at `/accessibility/v{n}` and `/accessibilite/v{n}`, 404 when there is none,
  `Cache-Control` from `cache_max_age`.
- Templates `layout.html.twig`, `statement.html.twig`, `_versions.html.twig` and the wordings `wcag`, `en-301-549`
  and `aca`, drawn with the recipes `Card`, `Badge`, `Table` and `Button` of the PHIL UX kit or the Shadcn kit
  installed by the Symfony UX Toolkit, passing only the props both kits share and writing no word of their own, named
  by `KitComponents` after the subdirectory of the Toolkit (`KitSubdirectoryPass`) or `kit_subdirectory`: the
  commitment,
  the status and the recommendation of the W3C, the content not accessible by reason with occurrences, alternatives
  and dates of fix, the alternatives, the feedback channels with the route of a form, the enforcement procedure, the
  preparation and the versions in a table; a button to the form of `feedback_form` or to `feedback_route`;
  translations in English and French, the titles of the success criteria included,
  the French ones those of the W3C Authorized Translation read from `phil/accessibility-statement`.
- `Infrastructure\Twig\StatementExtension`: `phil_accessibility_statement_link()`, a footer link translated and
  escaped with plain attributes only, `phil_accessibility_statement_path()`, `phil_accessibility_kit()` and the filter
  `phil_accessibility_text`.
- `Infrastructure\LegalNotices\StatementFooterLink`: the statement among the legal links of `phil/legal-notices-bundle`.
- `Infrastructure\Audit\ReportDirectory`: the latest audit as a directory of JSON reports of
  `phil/accessibility-check`, dated by the newest, at most 1 000 reports of 5 MiB checked before they are read;
  `audit.source` for an `AuditSource` of the application.
- `Application\StatementRegenerator` and `NothingToRegenerate`: the next version from the version in force and the
  latest audit, shown or published.
- `Application\StatementPublisher` and `PublicationRefused`: the draft of the next version — from the latest audit,
  or the version in force renumbered and dated now, in UTC — and its publication from a document, refused unless it
  reads and carries the next number.
- `Infrastructure\Http\StatementAdminController`, `config/admin_routes.php` and `admin.html.twig`: the
  administration page, behind the role `admin.role` and a CSRF token, drawn with the recipes `Alert`, `Field`,
  `Textarea`, `Button`, `Table` and `Badge`; the versions, the draft, the reason of a refusal; with the `config`
  source, the next version to write there.
- `Infrastructure\Console\RegenerateCommand`, `phil:accessibility-statement:generate`: publishes the next version, or
  shows it with `--dry-run` and with the `config` source, as a summary or as JSON.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_accessibility_statement.yaml` with the
  first version commented out, so that nothing is published until the application writes it, and
  `config/routes/phil_accessibility_statement.yaml` with the page.
