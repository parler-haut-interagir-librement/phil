# phil/accessibility-statement-bundle

**The accessibility statement of a Symfony site: a page in English and French, its versions, and a command that keeps
it true to the latest audit.**

phil/accessibility-statement-bundle publishes the accessibility statement of an application at `/accessibility` and
`/accessibilite`: the commitment, the conformance status against a version of WCAG such as WCAG 2.2 AA, the content
that is not accessible grouped by reason with the alternatives offered and the dates of fix, the feedback channels,
the enforcement procedure, how the statement was prepared, and its versions, each at an address that never changes.
The wording follows WCAG alone, the European standard EN 301 549 or the Accessible Canada Act.

It is for any Symfony site that owes its readers — or its regulator — a statement: write the first version in the
configuration, link it from the footer with `{{ phil_accessibility_statement_link() }}`, and let
`phil:accessibility-statement:generate` write the next version from the reports of `phil/accessibility-check`, keeping
what people wrote of each criterion. The versions stay in the configuration, or in a table with Doctrine.

The statement itself is `phil/accessibility-statement`, a plain PHP library; the bundle adds the page, the stores, the
command, an administration page that publishes the next version, and the Twig functions. The templates draw their parts
with recipes of the PHIL UX kit — or the Shadcn kit — installed by the Symfony UX Toolkit. The feedback channel may be
a form of `phil/forms-bundle` or the contact form of `phil/contact-form-bundle`.

## Status

| Part | State |
|---|---|
| The statement of the configuration, checked when the container is built | Implemented and tested |
| The versions in the table `phil_accessibility_statement`, the configuration as a version | Implemented and tested |
| The page at `/accessibility` and `/accessibilite`, each version at its own address, cached | Implemented and tested |
| English and French, the wordings of WCAG, EN 301 549 and the Accessible Canada Act | Implemented and tested |
| The footer link, the path and the text filter of Twig | Implemented and tested |
| The statement among the legal links of `phil/legal-notices-bundle` | Implemented and tested |
| The audit read from a directory of JSON reports, or from an `AuditSource` of the application | Implemented and tested |
| `phil:accessibility-statement:generate` | Implemented and tested |
| The templates drawn with the recipes of the PHIL UX kit or the Shadcn kit, each rendered with both in the tests; the feedback form of `phil/forms-bundle` | Implemented and tested |
| The administration page that publishes the next version, behind a role and a CSRF token | Implemented and tested |
| The moments of publication kept in UTC | Implemented and tested |
| The Flex recipe: the bundle, a configuration that publishes nothing until the statement is written, and the route | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the recipe, registering, the first version, the routes. |
| [Configuration](doc/configuration.md) | Every key, a complete statement, the services. |
| [The page](doc/page.md) | The routes, what the page shows, languages, wording, templates, the footer link. |
| [Doctrine](doc/doctrine.md) | The table, its versions and the version of the configuration. |
| [Administration](doc/administration.md) | The page that publishes the next version, who opens it, what it refuses. |
| [Console](doc/console.md) | The command, its exit codes and the audit directory. |
| [Extension points](doc/extension-points.md) | The latest audit, the versions, regenerating, the page. |
| [Testing](doc/testing.md) | How the bundle is tested, and how to test an application with it. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/accessibility-statement-bundle
```

```yaml
# config/packages/phil_accessibility_statement.yaml
phil_accessibility_statement:
    feedback_route: phil_contact_form
    statement:
        version: 1
        published_at: '2026-11-08'
        entity: Example Org
        feedback: { email: accessibility@example.com, response_days: 10 }
    audit: { enabled: true }

# config/routes/phil_accessibility_statement.yaml
phil_accessibility_statement:
    resource: '@PhilAccessibilityStatementBundle/config/routes.php'
```

```sh
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit   # and badge, table, button
```

```twig
<footer>{{ phil_accessibility_statement_link() }}</footer>
```

```sh
bin/console phil:accessibility:check templates/pages/home.html --format=json > var/accessibility/home.json
bin/console phil:accessibility-statement:generate --format=json   # the next version, to write in the configuration
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later with FrameworkBundle, TwigBundle and `symfony/ux-twig-component`,
`phil/accessibility-statement` and `phil/accessibility-check`, and the recipes of the kit; `symfony/translation` for
the languages, `doctrine/orm` for the table, `symfony/console` for the command, Symfony Security and
`symfony/security-csrf` for the administration page.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
