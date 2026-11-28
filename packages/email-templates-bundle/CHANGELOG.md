# Changelog

All notable changes to `phil/email-templates-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Domain\ValueObject\TemplateKey`, `Site` — a site or the shared scope —, `Locale` — `fr-CA` read as `fr_CA`, its
  language —, `TemplateDefinition` — subject, preheader, HTML or Markdown body, plain-text part, variable schema,
  segments, embedded images, every limit checked and every reason listed —, `TemplateDraft` — version, revision, state,
  version sent — and `TemplateVersion`, a published version; `BodyFormat`, `TemplateOrigin`, `PublicationState`; the
  exceptions `InvalidTemplate`, `InvalidVariables`, `UnknownTemplate`, `VersionConflict` and `RenderingFailed`, all
  `EmailTemplatesException`; the events `TemplateSaved`, `TemplatePublished`, `TemplateDraftDiscarded` and
  `TemplateReset`, identifiers only.
- `Domain\Schema\VariableSchema`, `Variable` and `VariableType`: named slots of nine types — text, integer, number,
  boolean, date, `http(s)` address read by `phil/url`, email address read by `phil/email-address`, list, object —,
  required or not, with defaults and sample values checked against their type, a description and limits; at most 100
  variables, nested 3 deep; values checked and normalized, each violation named by its path and never by its value.
- `Application\TemplateResolver`: locale by locale — the one asked for, its language, the default locale —, the version
  the site published, the shared one, then the default file, a draft never sent; the draft of a preview; a published
  version by number.
- `Infrastructure\Twig\SandboxedEngine`: every source in Twig's `Sandbox`, strict, in an environment of its own, with the
  allow-list of `SandboxRules` and the Intl filters when installed; strict variables; a body escaped as HTML, the
  subject, the preheader and the plain text as text; a Markdown body read by `phil/markup` and purified, its components
  kept whole; `check()` — syntax, policy, loops nested at most two deep, variables the schema does not declare —;
  an output ceiling; compiled templates cached by the hash of their source; `reset()` between requests. The filters and
  functions of the application's extensions tagged `phil_email_templates.sandbox_extension` once listed; with
  `twig/html-extra` 3.31.1 or later, whose `html_cva()` keeps to the sandbox's property allow-list.
- `Infrastructure\Twig\EmailComponents`: `button()`, `table()`, `image()`, `panel`, `footer` and `segment()`, drawn with
  tables and inline styles, writing text in the subject, the preheader and the plain-text part; addresses checked,
  every text escaped.
- `Application\Rendering\EmailRenderer`: render without sending, the values checked against the schema, the house
  layout, the inlined styles, the plain-text part written by `HtmlToText` unless the template writes its own, the
  embedded images; `RenderedEmail` with `isClippedByGmail()` and `htmlForBrowser()`; `RenderOptions`, `RenderContext`,
  `RenderedParts`, `EmbeddedImage` and `Theme`, the colours light and dark and the font, checked.
- `templates/email/layout.html.twig`, the house layout of the application's Twig: hidden preheader, brand, a column of
  600 pixels, `color-scheme` and a dark scheme through `prefers-color-scheme`; `TwigLayout`; `CssInlinerStyles` on
  `twig/cssinliner-extra`, `NoStyleInliner` without it; `DirectoryEmbedLoader` for PNG, JPEG and GIF images of one
  directory; `MarkupMarkdown`.
- `Application\EmailComposer`: a Symfony Mailer `Email` ready to send, its images attached inline, the configured sender
  when it has none. `Infrastructure\Messenger\SendTemplatedEmail`, identifiers only, and its handler through the
  application's `DeliveryResolver` and Mailer; `Application\Delivery`.
- `Application\EmailTemplates` and `TemplateValidator`: save the draft after the checks — compile, policy, declared
  variables, every variant rendered with the sample values —, a save over a stale revision refused; publish after the
  checks again; revert by copying a published version into the draft; discard the changes back to the published
  version; reset by taking the locale offline, draft and versions kept; current, draft, history of the published versions
  and the catalog of a site — origin of what is sent, version sent, state of the site's draft —; only keys a default file
  declares; the author an identifier or nobody; the events dispatched through PSR-14.
- `Infrastructure\Yaml\YamlDefaultTemplates`: one YAML file per key and locale, the application's directories before
  the bundle's; the defaults `confirmation`, `ticket` and `cancellation` in English and French.
- `Infrastructure\Content\ContentTemplateStore`, the default store: the templates as contents of
  `phil/content-bundle`, one root per template, scope and locale (`EmailTemplateContent`, its site, key and locale unique
  and in `utf8mb4_bin` on MySQL and MariaDB through `TemplateKeysCollation`) and its rows by stage and version
  (`EmailTemplateDimension`, `TemplateCapable` and `WorkflowCapable`, the template in its field values through
  `TemplatePayload`); writes through `ContentManager` and its workflow, a save locking the root; the mapping and the root
  prepended to `doctrine` and `phil_content` when DoctrineBundle and PhilContentBundle are enabled; the locales those of
  `phil_content.locales`. `InMemoryTemplateStore`, with the same rules, its publications dated by the clock.
- `Application\Contract\SiteScope`, `FixedSiteScope` and `TenancySiteScope`, the active tenant of `phil/tenancy-bundle`;
  `AuditTemplateChanges`, each change in the trail of `phil/audit-bundle`.
- `Application\Contract\PreviewRenderer`, `SamplePreviews` and `PreviewVariant`: every variant — no segment, each
  segment, all of them — with its sample values.
- `Infrastructure\Http\AdminController` and `config/routes_admin.php`: the list of the templates of a site or of the
  shared scope with the state of each draft, the page of a template — form of the draft, save, publish, revert, discard,
  take offline, published versions, preview of the draft —, the frame of a variant, as sent, as a draft or at a version,
  in an `<iframe sandbox>` served with a policy that runs no script; a configurable role, the stateless CSRF token
  `phil_email_templates_admin`; pages drawn by the recipes of the PHIL UX kit or of the Shadcn kit, in English and
  French; `KitComponents`, `AdminExtension`, `KitSubdirectoryPass`, `CsrfProtectionPass`.
- `phil:email-templates:list`, with the state of each draft, `phil:email-templates:check` and
  `phil:email-templates:render`, with `--draft` and `--template-version`.
- `PhilEmailTemplatesBundle` and its configuration `phil_email_templates`, `store: content` and `entity_manager`; the
  Flex recipe.
- The fuzz target `render` and its named seeds; the benchmark `RenderBench`.
- The documentation in `doc/`, one file per topic, indexed by the README, with the copy of templates kept in a table of
  versions into the contents; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
