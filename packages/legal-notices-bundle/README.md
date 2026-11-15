# phil/legal-notices-bundle

**The legal notice and the privacy policy of a Symfony site, versioned, in each locale, at `/legal` and `/privacy`.**

phil/legal-notices-bundle brings phil/legal-notices to a Symfony application. The identity of the site and the
versions of its two documents are written in the configuration — each body inline or in a file, in Markdown, UJO
(Universal JSON Output), HTML or plain text — or read from a source of the application. The container build checks
every version and refuses a published document whose identity lacks a field it requires.

The pages show the version in force at `/legal` and `/privacy`, each version the public sees at an address that never
changes, `/legal/v2`, and the history of the versions, in English and French and in every locale the routes are
mounted for. The privacy policy lists the retention periods of the schedule of phil/privacy-bundle and the consent
categories of the cookies; its body names them as placeholders too. The Twig function `phil_legal_notices_links()`
gives the links of a footer, the policies of phil/terms-bundle among them; `phil:legal-notices:check` reports what a
public document lacks, and `phil:legal-notices:announce` dispatches an event for each version published lately.

It is for a site that must publish who it is and how it treats personal information, keep every version it
published, and show the periods and cookies its code enforces. The bodies are purified by phil/markup, every value
filled in is escaped, and the services keep nothing between two requests.

## Status

| Part | State |
|---|---|
| Configuration: identity, versions inline or in files, sources, retention, consent, pages, footer | Implemented and tested |
| Pages: the version in force, each version, the history, in each locale | Implemented and tested |
| phil/markup bodies, phil/privacy-bundle retention, consent categories, phil/terms-bundle footer links, phil/sitemap-bundle pages, phil/structured-data-bundle nodes | Implemented and tested |
| `phil:legal-notices:check`, `phil:legal-notices:announce` and `DocumentPublished` | Implemented and tested |
| The pages drawn by the recipes `badge` and `table` of the PHIL UX kit or of the Shadcn kit, every word from the translations, lists in the language of the page | Implemented and tested |
| The Flex recipe: the bundle registered, its routes, a configuration with two drafts and their bodies | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the routes and a first document. |
| [Configuration](doc/configuration.md) | Every option, the versions and the checks of the container build. |
| [Pages](doc/pages.md) | The routes, the pages, the recipes of the kit, their templates and caching, the footer links. |
| [Integrations](doc/integrations.md) | phil/markup, phil/privacy-bundle, consent, phil/terms-bundle, phil/sitemap-bundle, phil/structured-data-bundle, phil/accessibility-statement-bundle, PHIL UX, EasyAdmin. |
| [Console](doc/console.md) | The check and the announcements. |
| [Extension points](doc/extension-points.md) | The services, ports and templates an application replaces. |
| [Testing](doc/testing.md) | How the bundle is tested. |

## Quick start

```sh
composer require phil/legal-notices-bundle
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The recipe of the bundle registers it, mounts its routes, and writes its configuration with the two documents as
drafts and their bodies in `legal/`. Fill in the identity, then publish the first version:

```yaml
# config/packages/phil_legal_notices.yaml
phil_legal_notices:
    identity:
        publisher_name: Example Inc.
        publisher_address: "1 Main Street\nMontréal QC"
        publication_director: Jo Smith
        contact_email: hello@example.com
        host_name: Example Hosting
        host_address: 2 Server Road, Toronto ON
        host_contact: support@hosting.example
    documents:
        legal_notice:
            - number: 1
              format: markdown
              published_at: '2026-12-01'
              files:
                  en: '%kernel.project_dir%/legal/notice.en.md'
                  fr: '%kernel.project_dir%/legal/notice.fr.md'
```

A body names the fields of the identity as placeholders between `${` and `}`, a marker that none of the formats of
phil/markup reads, the wiki formats included:

```markdown
# Legal notice

This site is published by **${publisher_name}**, ${publisher_address}.
```

## Requirements

PHP 8.5 or later with `ext-intl`, Symfony 8.2 or later, Twig with twig/intl-extra, Symfony UX Twig Components and the
recipes `badge` and `table` of the PHIL UX kit, or of the Shadcn kit on Tailwind; phil/markup, phil/privacy-bundle,
phil/terms-bundle and phil/sitemap-bundle are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
