# Reference

Every public type of the package, with its role.

## Domain

| Type | Role |
|---|---|
| `Domain\ValueObject\TemplateKey` | The name of a template: 1 to 64 lowercase ASCII letters, digits, `_`, `.` or `-`, starting with a letter. |
| `Domain\ValueObject\Site` | A site, or the shared scope, the empty text. |
| `Domain\ValueObject\Locale` | A locale, `fr_CA`; `language()` gives `fr`. |
| `Domain\ValueObject\TemplateDefinition` | The fields of a template, checked; `create()`, `fromArray()`, `toArray()`, `hasSegment()`. |
| `Domain\ValueObject\TemplateVersion` | One published version: key, site, locale, number, definition, moment of the save, author, moment of the publication. |
| `Domain\ValueObject\TemplateDraft` | The working copy of a template for a site and a locale: version, revision, definition, state, moment, author, version sent; `isPending()`. |
| `Domain\Schema\VariableSchema` | The named slots of a template; `fromArray()`, `toArray()`, `validate()`, `samples()`, `names()`. |
| `Domain\Schema\Variable` | One slot: type, required, default, sample, description, fields, items, limits. |
| `Domain\Schema\VariableType` | `string`, `integer`, `number`, `boolean`, `datetime`, `url`, `email`, `list`, `object`. |
| `Domain\Status\BodyFormat` | `html` or `markdown`. |
| `Domain\Status\TemplateOrigin` | `site`, `shared` or `default`. |
| `Domain\Status\PublicationState` | Where a draft stands: `unpublished`, `draft` (published, with changes) or `published`. |
| `Domain\Event\TemplateSaved`, `TemplatePublished`, `TemplateDraftDiscarded`, `TemplateReset` | The changes, identifiers only. |
| `Domain\Exception\EmailTemplatesException` | What every exception of the bundle implements. |
| `Domain\Exception\InvalidTemplate` | A template refused, every reason in `errors`. |
| `Domain\Exception\InvalidVariables` | Values that do not match the schema, each path in `violations`. |
| `Domain\Exception\UnknownTemplate` | No template, no such published version, or no draft to publish. |
| `Domain\Exception\VersionConflict` | Another revision of the draft was saved meanwhile. |
| `Domain\Exception\RenderingFailed` | A render that failed. |

## Application

| Type | Role |
|---|---|
| `Application\Rendering\EmailRenderer` | Renders an email without sending it; `render()`, `renderSamples()`, `renderResolved()`. |
| `Application\Rendering\RenderOptions` | Site, locale, segments and version of a render. |
| `Application\Rendering\RenderContext` | What a render is for, read as `email` by a template. |
| `Application\Rendering\RenderedParts` | The sources rendered, before the layout. |
| `Application\Rendering\RenderedEmail` | The email rendered: subject, preheader, HTML, text, images; `isClippedByGmail()`, `htmlForBrowser()`. |
| `Application\Rendering\EmbeddedImage` | An image attached inline; `dataUri()`. |
| `Application\Rendering\HtmlToText` | The plain-text part written from HTML. |
| `Application\Rendering\Theme` | The colours, light and dark, and the font, checked. |
| `Application\EmailComposer` | A Symfony Mailer `Email` ready to send. |
| `Application\EmailTemplates` | Save, publish, revert, discard, reset, current, draft, history, catalog. |
| `Application\TemplateValidator` | The checks of a save and of a publication. |
| `Application\TemplateResolver` | Which template a site sends in a locale; the draft a preview renders; a published version. |
| `Application\ResolvedTemplate` | A template found, where, and whether it is a draft. |
| `Application\CatalogEntry`, `CatalogRow` | The draft a site keeps of a template in a locale; a row of the catalog of a site. |
| `Application\Delivery` | Recipients, values, locale, segments and site of a message. |
| `Application\FixedSiteScope` | Always the same site. |
| `Application\Preview\SamplePreviews` | The preview of the bundle, with the sample values. |
| `Application\Preview\PreviewVariant` | One variant of a template. |
| `Application\Contract\TemplateStore` | Where the drafts and the published versions are kept. |
| `Application\Contract\DefaultTemplates` | The default files. |
| `Application\Contract\TemplateEngine` | Renders and checks the sources in a sandbox. |
| `Application\Contract\EmailLayout` | The house layout. |
| `Application\Contract\StyleInliner` | Inlines the styles. |
| `Application\Contract\EmbedLoader` | Reads the embedded images. |
| `Application\Contract\MarkdownRenderer` | Turns a Markdown body into safe HTML. |
| `Application\Contract\SiteScope` | The current site. |
| `Application\Contract\PreviewRenderer` | The variants of a template and their render, for the preview. |
| `Application\Contract\DeliveryResolver` | The delivery of a reference a message carries. |

## Infrastructure

| Type | Role |
|---|---|
| `Infrastructure\Twig\SandboxedEngine` | The engine: Twig's `Sandbox` in strict mode, its own environment, a cache by hash; reset between requests. |
| `Infrastructure\Twig\SandboxRules` | The allow-list. |
| `Infrastructure\Twig\LimitsVisitor` | The loop depth, the variables read, the calls made. |
| `Infrastructure\Twig\SourceLoader` | The sources by the hash of their content. |
| `Infrastructure\Twig\EmailComponents` | The components, through Twig's `AttributeExtension`. |
| `Infrastructure\Twig\RenderStash`, `RenderMode` | What the components of one render share. |
| `Infrastructure\Twig\TwigLayout` | The house layout in the application's Twig. |
| `Infrastructure\Twig\CssInlinerStyles`, `NoStyleInliner` | The styles inlined by `twig/cssinliner-extra`, or left as they are. |
| `Infrastructure\Twig\KitComponents`, `AdminExtension` | The names of the recipes of the kit, `phil_email_templates_kit()`. |
| `Infrastructure\Markup\MarkupMarkdown` | Markdown by `phil/markup`. |
| `Infrastructure\Filesystem\DirectoryEmbedLoader` | The images of one directory. |
| `Infrastructure\Yaml\YamlDefaultTemplates` | The default files in YAML. |
| `Infrastructure\Content\ContentTemplateStore` | The templates as contents of `phil/content-bundle`. |
| `Infrastructure\Content\Entity\EmailTemplateContent`, `EmailTemplateDimension` | The root of a template, scope and locale, and its rows by stage and version. |
| `Infrastructure\Content\TemplatePayload` | A template as the field values of its row. |
| `Infrastructure\Content\TemplateKeysCollation` | The site, key and locale of a root in `utf8mb4_bin` on MySQL and MariaDB. |
| `Infrastructure\Memory\InMemoryTemplateStore` | The drafts and the versions in memory. |
| `Infrastructure\Tenancy\TenancySiteScope` | The active tenant as the site. |
| `Infrastructure\Audit\AuditTemplateChanges` | The changes in the audit trail. |
| `Infrastructure\Messenger\SendTemplatedEmail`, `SendTemplatedEmailHandler` | The message and its handler. |
| `Infrastructure\Http\AdminController` | The administration and its preview frames. |
| `Infrastructure\Console\ListTemplatesCommand`, `CheckTemplatesCommand`, `RenderTemplateCommand` | The commands. |
| `Infrastructure\DependencyInjection\EmailTemplatesServices`, `CsrfProtectionPass`, `KitSubdirectoryPass` | The wiring. |
| `PhilEmailTemplatesBundle` | The bundle: its configuration `phil_email_templates` and its services. |

## Services

Every class above that a service holds is registered under its name; the interfaces of `Application\Contract` are
aliases to their implementation. Public: `EmailRenderer`, `EmailComposer`, `EmailTemplates`, `TemplateValidator`,
`TemplateResolver` and the aliases of `TemplateStore`, `DefaultTemplates`, `SiteScope`, `TemplateEngine` and
`PreviewRenderer`.
