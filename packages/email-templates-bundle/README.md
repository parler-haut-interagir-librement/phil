# phil/email-templates-bundle

**Email templates that several sites share and each site may change — a confirmation, a ticket, a cancellation —,
written in sandboxed Twig with email-safe components, drafted, published and versioned per site and locale, previewed,
and rendered into a Symfony Mailer `Email`.**

phil/email-templates-bundle renders the emails an application sends from templates that people other than its
developers may edit. A template has a **subject**, a **preheader**, a **body** in HTML or in Markdown, an optional
hand-written **plain-text part**, the **schema of its variables** — named slots with their types, whether they are
required, their defaults and sample values — and the **segments** its conditional blocks test. The defaults ship as
YAML files, the bundle's and the application's, the application's winning. Each **site**, or the scope every site
shares, keeps per locale a **draft** of its own and the **versions** it published, as contents of `phil/content-bundle`:
a draft is sent only once published, a published version is never changed, and the latest published one is sent. While a
site publishes nothing in a locale, the shared template or the default file is sent. A locale falls back to its
language, then to the default locale.

The sources render in a **Twig sandbox** of their own, in Twig's strict mode: an allow-list of tags, filters,
functions, tests and methods, nothing included, nothing of the application's Twig in reach, loops nested at most two
deep, an output ceiling. The **components** — `button()`, `table()`, `image()`, `panel`, `footer` and `segment()` —
draw tables with inline styles that the email clients render alike. A Markdown body is read by `phil/markup` and
purified, its components kept whole. The **house layout**, a template of the application's Twig, wraps every email:
the hidden preheader, the brand, the body, the colours of a light and a **dark scheme** through `color-scheme` and
`prefers-color-scheme`; the styles are then inlined by `twig/cssinliner-extra`, and the plain-text part is written from
the HTML unless the template writes its own. Images are embedded by content identifier, `cid:logo`.

Every value given to a render is **checked against the schema**; every template is **checked when it is saved and
when it is published** — its
sources compile in the sandbox, read only what the schema declares, and render in every variant with the sample
values. The result is a `RenderedEmail` — subject, preheader, HTML, text, images — or an `Email` ready to send; a
message, `SendTemplatedEmail`, carries identifiers only and sends it through Messenger. An **administration** for a
configurable role edits, publishes, reverts, discards and takes offline the templates of a site, and **previews** the
draft in each variant — no segment, each segment, all of them — in an `<iframe sandbox>` served with a policy that runs
no script; its pages are drawn by the recipes of the PHIL UX kit or of the Shadcn kit, in English and French.

It is a Symfony bundle on Twig, `phil/content-bundle` and the Doctrine ORM, `phil/markup`, `phil/url`,
`phil/email-address` and Symfony Mime.
`phil/tenancy-bundle` makes the active tenant the site; `phil/audit-bundle` records every change in its trail; the
console, Mailer, Messenger, Security, the CSRF protection, Symfony UX Twig Component, `twig/cssinliner-extra` and
`twig/intl-extra` are optional. The templates live in two tables beside those of the contents, on SQLite, MySQL, MariaDB
or PostgreSQL, their sites and keys compared byte for byte, tested on MySQL 8.4 and PostgreSQL 17; a store in memory
serves tests. Its services keep no state between requests and serve a long-running worker.

## Status

| Part | State |
|---|---|
| `TemplateKey`, `Site`, `Locale`, `TemplateDefinition`, `TemplateDraft`, `TemplateVersion`, `BodyFormat`, `TemplateOrigin`, `PublicationState`, the exceptions and the events | Implemented and tested |
| `VariableSchema`, `Variable`, `VariableType`: named slots, nine types, defaults, samples, limits, values checked and normalized | Implemented and tested |
| `TemplateResolver`: the site's published version, the shared one, the default files, locale by locale; the draft of a preview | Implemented and tested |
| `SandboxedEngine`, `SandboxRules`, `LimitsVisitor`, `SourceLoader`: the strict Twig sandbox, its checks and its cache | Implemented and tested |
| `EmailComponents`: button, table, image, panel, footer, segment, in HTML, Markdown and text | Implemented and tested |
| `EmailRenderer`, `HtmlToText`, `TwigLayout`, `CssInlinerStyles`, `DirectoryEmbedLoader`, `MarkupMarkdown`: render without sending | Implemented and tested |
| `EmailComposer`, `SendTemplatedEmail` and its handler: an `Email` ready to send, a message with identifiers only | Implemented and tested |
| `EmailTemplates`, `TemplateValidator`: save, publish, revert, discard, reset, history, catalog of a site | Implemented and tested |
| `ContentTemplateStore`, `EmailTemplateContent`, `EmailTemplateDimension`, `TemplatePayload`, `TemplateKeysCollation`, `InMemoryTemplateStore`, `YamlDefaultTemplates` | Implemented and tested |
| `PreviewRenderer`, `SamplePreviews`, `AdminController` and its pages, PHIL UX and Shadcn | Implemented and tested |
| `TenancySiteScope`, `AuditTemplateChanges`; the commands `list`, `check` and `render`; the Flex recipe | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the contents and their locales, templates kept in a table of versions, the recipes of the kit. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Templates](doc/templates.md) | The default files, the fields of a template, the variable schema, segments and images. |
| [The sandbox and the components](doc/sandbox.md) | What a template may write, the components, Markdown, the limits. |
| [Rendering and sending](doc/rendering.md) | Finding a template, the layout, the dark mode, the inlined styles, the plain text, Mailer and Messenger. |
| [Sites, drafts and versions](doc/sites-and-versions.md) | The sites, the shared scope, drafts, publication and versions, the contents, tenancy and audit. |
| [Administration and preview](doc/administration.md) | The pages, the preview frames, the role, the CSRF token, the kit. |
| [Console](doc/console.md) | `phil:email-templates:list`, `check` and `render`. |
| [Security](doc/security.md) | What the sandbox, the checks and the preview protect against, and what is left to the application. |
| [Extension points](doc/extension-points.md) | The services to replace, the tags to add to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
Phil\ContentBundle\PhilContentBundle::class => ['all' => true],
Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_content.yaml
phil_content:
    locales: { en: [], fr: [] }

# config/packages/phil_email_templates.yaml
phil_email_templates:
    from: 'Shop <shop@example.com>'
    brand: { name: 'Shop' }
```

```php
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;

$email = $composer->compose('ticket', [
    'recipient' => ['name' => 'Ada'],
    'event' => ['title' => 'Autumn concert', 'starts_at' => '2026-10-03T20:00:00-04:00'],
    'ticket' => ['number' => 'T-7781'],
    'ticket_url' => 'https://example.com/tickets/T-7781',
], new RenderOptions(locale: 'fr'));

$mailer->send($email->to('ada@example.com'));
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Twig 3.31 or later, and `phil/content-bundle` with DoctrineBundle for the
templates the sites write.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
