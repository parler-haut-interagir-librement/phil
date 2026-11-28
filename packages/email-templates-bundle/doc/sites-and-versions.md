# Sites, drafts and versions

## Sites and the shared scope

A template belongs to a **site** — 1 to 64 ASCII letters, digits, `_`, `.`, `:` or `-`, the identifier of a tenant, a
domain or a brand — or to the **shared scope**, the empty site, which every site reads when it publishes nothing of its
own. The current site is the `SiteScope`'s:

| Configuration | Current site |
|---|---|
| `phil/tenancy-bundle` enabled, `site` not set | the active tenant of its context; outside any tenant, the shared scope (`TenancySiteScope`) |
| `site: main` | `main` (`FixedSiteScope`) |
| neither | the shared scope |

The site is a column of the template, not a filter of `phil/tenancy-bundle`: the shared scope belongs to no tenant, and
a filter that hides every row of another tenant would hide it too. Each lookup names the site it reads.

## Drafts, publication and versions

Each site keeps, per template and locale, a **draft** — the working copy its editors change — and the **versions** it
published. Saving writes the draft; nothing a site saves is sent until it is **published**. Publishing freezes the
version the draft belongs to, and the next save starts the next version, so a published version is never changed and the
published versions are the history.

| State of the draft (`PublicationState`) | Means |
|---|---|
| `Unpublished` (`unpublished`) | never published, or taken offline: the site sends the shared template or the default file |
| `Pending` (`draft`) | published, with changes the site does not send yet |
| `Published` (`published`) | the site sends exactly this draft |

`TemplateDraft` holds the definition, the version it belongs to, its **revision** — how many times it was saved —, its
state, when and by whom it was saved last, and `liveVersion`, the version the site sends, `null` while none is.
`TemplateVersion` holds a published version: its definition, its number, when and by whom it was saved, and
`publishedAt`.

## Which template is sent

The locales are tried in turn — the one asked for, its language, the default locale, its language —, and for each the
version the site published, then the one the shared scope published, then the default file. So `fr_CA` published in the
shared scope wins over `fr` published by the site, and a site that published `fr` sends it for `fr_CA` when nothing is
published for `fr_CA`. A draft is never sent: until it is published, the next step of the chain answers. A version asked
for by number is one the site — or the shared scope — published in exactly that locale.

## Keeping the templates

`Application\EmailTemplates` keeps the templates of the sites:

| Method | Does |
|---|---|
| `save($key, $locale, TemplateDefinition $definition, int $expected, ?Site $site, ?string $author)` | Checks the template, then saves the draft, in a new version when the latest one is published; `$expected` is the revision it was edited from, `0` for a template the site never saved |
| `publish($key, $locale, ?Site $site, ?string $author)` | Checks the draft again, then publishes it: the site sends it from now on |
| `revert($key, $locale, int $version, int $expected, ?Site $site, ?string $author)` | Copies a published version into the draft, once it passes the checks again; it is sent once published |
| `discard($key, $locale, ?Site $site, ?string $author)` | Abandons the changes made since the locale was published: the draft is the published version again |
| `reset($key, $locale, ?Site $site, ?string $author)` | Takes the locale offline: the site sends the shared template, or the default file, again; the draft and the versions are kept |
| `current($key, $locale, ?Site $site)` | The `ResolvedTemplate` the site sends, and where it comes from |
| `draft($key, $locale, ?Site $site)` | The `TemplateDraft` the site keeps in exactly that locale, `null` when it never saved one |
| `history($key, $locale, ?Site $site, int $limit = 50)` | The published versions, the latest first |
| `catalog(?Site $site)` | Every template the site sends or keeps a draft of, in every locale, by key: where what it sends comes from, the version sent, and where the site's own draft stands |

`$site` is the current site when `null`. The checks of a save and of a publication are those of `TemplateValidator`:
the sources compile in the sandbox and read only what the schema declares, then every variant — no segment, each
segment — renders with the sample values, the layout, the inlined styles and the embedded images included.
`InvalidTemplate` lists every reason; `VersionConflict` says another revision was saved meanwhile; `UnknownTemplate`
says there is no draft to publish, or no published version of that number. A key no default file declares is refused.
`discard()` and `reset()` return whether there was something to do, and announce nothing otherwise.

The author is the identifier of who saved, kept with the draft and in the events: 1 to 128 ASCII letters, digits, `_`,
`.`, `:` or `-`. Anything else — an email address above all — is not kept.

## Events and the audit trail

Each change dispatches, through the PSR-14 dispatcher when there is one, an event of `Domain\Event` — identifiers only,
never a source:

| Event | Fields |
|---|---|
| `TemplateSaved` | key, site, locale, version, revision, author, and the version copied from by `revert()` |
| `TemplatePublished` | key, site, locale, the version published, author |
| `TemplateDraftDiscarded` | key, site, locale, the version of the draft, author |
| `TemplateReset` | key, site, locale, author |

With `phil/audit-bundle` enabled, `AuditTemplateChanges` records each in the trail, in the segment `audit.segment`, as
`email_templates.saved`, `email_templates.reverted`, `email_templates.published`, `email_templates.discarded` or
`email_templates.reset`, the template its subject and the site its tenant.

## The contents

With the default store, `content`, the templates are contents of `phil/content-bundle` (`ContentTemplateStore`):

- **one root per template, scope and locale**, `Infrastructure\Content\Entity\EmailTemplateContent`, in the table
  `phil_email_template_content`: its identifier, a UUIDv7, and its site — the empty text for the shared scope —, key and
  locale, unique together and compared byte for byte (`utf8mb4_bin` on MySQL and MariaDB, through
  `TemplateKeysCollation`; the deterministic default collations of PostgreSQL and SQLite);
- **its rows**, `EmailTemplateDimension`, in the table `phil_email_template_dimension`, one per stage and version, in the
  locale of the root, and the unlocalized row of each, which a template leaves empty. The draft stage is the working copy,
  the live stage what the site sends. The row is `TemplateCapable`, its field values holding the definition, the
  revision, the moment of the save and the author (`TemplatePayload`), and `WorkflowCapable`, the place of the editorial
  workflow and the publication date.

Writes go through `ContentManager`: `edit()` saves the draft and forks a published version, `publish()`,
`unpublish()` and `removeDraft()` publish, take offline and abandon the changes, each in a transaction of
`ContentTransaction`, with the workflow of `phil/content-bundle`, its guards and its events. A save locks the root of the
template, so that two saves over the same revision cannot both pass the check; two first saves of one template at once
create the same root, and the unique key refuses the second as a `VersionConflict`.

A root holds one locale because the locales of a template are written and published apart: `phil/content-bundle` forks
every locale of a version together and moves each published one to `draft`, so one root for every locale would show the
French template changed each time the English one is saved.

The locales a site writes templates in are among those `phil_content.locales` serves; a locale it does not serve, or one
the contents cannot hold — a script such as `zh_Hant` —, is an `InvalidTemplate`. The fallback from one locale to the
next is the bundle's, as above, not the chains of `phil_content.locales`.

`phil/content-bundle` lists every declared root in its own administration and its search feed: the kind
`phil_email_template` appears there beside the pages. Its rows have no route, no body and no title, so they are never
served as pages, and the feed gives them no text.

## In memory

`InMemoryTemplateStore` keeps the drafts and the versions for the life of the process, with the same rules — a
published version frozen, the next save in the next version, the changes abandoned back to the published version —, for
tests and for an application without a database. Its publications are dated by the configured clock.
