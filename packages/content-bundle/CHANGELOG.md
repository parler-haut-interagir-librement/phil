# Changelog

All notable changes to `phil/content-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0-alpha - 2026-11-18

The next version is 0.3.0-alpha: the keys of the routes, single roots, working copies and rows compare byte by byte on
MySQL and MariaDB. See [Upgrading from 0.2](doc/storage.md#upgrading-from-02).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event, its `FIELDS`, the fields of
  the bundle's entities, and its `DIMENSION_FIELDS`, the fields of every subclass of `AbstractDimensionContent`, that
  compare byte by byte on MySQL and MariaDB.

### Changed

- `phil/markup` 0.3 is accepted beside 0.2.1.
- On MySQL and MariaDB, `resource_key`, `resource_id`, `slug` and `locale` of `phil_content_route`, `resource_key` and
  `resource_id` of `phil_content_single_root`, `resource_key`, `resource_id`, `locale` and `author` of
  `phil_content_working_copy`, and `locale`, `locale_key` and `stage` of the table of each row class take the `utf8mb4`
  character set and the `utf8mb4_bin` collation in the schema the ORM generates, through
  `Infrastructure\Doctrine\BinaryKeysListener`. PostgreSQL and SQLite keep their default, which compares bytes already.
- `phil/scheduled-transitions-bundle` 0.2 is accepted beside 0.1.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, the routes of the content `news:Doc-A` were found
  for `news:doc-a`, releasing the single root of one identifier freed the kind held under another case, and the
  autosave of the author `Ann` was given to `ann`.

## 0.2.0-alpha - 2026-11-17

The next version is 0.2.0-alpha: a new table, `phil_content_single_root`, to migrate, and preview links signed over
their tenant or host, which refuses the links made before. See [Upgrading from 0.1](doc/storage.md#upgrading-from-01)
and the [preview links](doc/sitemap-feeds-previews.md#upgrading-from-01).

### Added

- `SingleRoot`, the entity of `phil_content_single_root`, whose primary key over the resource key refuses a second
  root of a single kind; the port `Domain\Contract\SingleRootRepository` and its adapter
  `DoctrineSingleRootRepository`; `InvalidContent::singleKindTaken()`.
- `Application\Exception\WriteConflict`, raised by `ContentTransaction::flush()` when a unique key it writes was
  taken by another transaction meanwhile, and `SlugTaken::meanwhile()`.
- `PreviewLinks::VERSION`, the version written before each signature.

### Changed

- `ContentManager::create()` records the root of a single kind in the transaction that creates it, and
  `ContentManager::delete()` frees the kind with its root; `ContentManager` takes the `SingleRootRepository` as its
  last, optional argument.
- A preview link is signed over its scope too — the active tenant of `phil/tenancy-bundle` when there is one, its host
  otherwise — and its signature starts with `v2.`: a link opened in another tenant or on another host is a `404`, and
  so are the links made by 0.1. `PreviewLinks` takes the `TenantContext` as its last, optional argument, and
  `PreviewLinks::verify()` the host the link was opened on as its last argument. `phil/tenancy-bundle` is suggested.
- `ContentPublisher` flushes the route of a publication apart from its rows.
- `phil/markup` 0.2.1 and `phil/ujo` 0.1.2 are required: bold and italic written in Markdown and the wikis reach the
  published HTML as `<strong>` and `<em>`, and the items of a checklist as disabled checkboxes in their labels.

### Fixed

- Two creations of one single kind at once no longer both create a root: the second is refused with `InvalidContent`,
  as a creation that finds the root already there.
- Two publications that take one slug in one locale at once end in `SlugTaken` for the second, instead of an
  exception of the database; the administration shows it as it shows a slug found taken.
- A preview link replayed on another tenant's host, with a shared secret, no longer shows that tenant's draft of the
  content of the same name.

## 0.1.1-alpha - 2026-11-14

### Fixed

- Publishing an HTML body keeps `<strong>` and `<em>` in its UJO and in the HTML compiled from it, apart from `<b>`
  and `<i>`, instead of turning them into `<b>` and `<i>`; publishing that UJO or that HTML again gives the same
  document. Brought by `phil/ujo` and `phil/markup`.

### Changed

- Requires `phil/ujo` `^0.1.1` and `phil/markup` `^0.2`, the versions that keep `<strong>` and `<em>`.

## 0.1.0-alpha - 2026-11-12

### Added

- Dimensions: a content root and its rows by locale, stage and version, the shared row, fallback chains and
  aggregation; the locale, the version and the chains are those of `phil/locale-dimensions`, and
  `DimensionLocales` raises their refusals as `InvalidDimension`.
- Capabilities: template, workflow, SEO, excerpt, link, slug and purified body, each with its data mapper (none for
  the workflow), merger, normalizer and mapping. A link to another site and a canonical URL are `Phil\Url\Url`, from
  `phil/url`, normalised; a slug is `Phil\Slug\Slug`, from `phil/slug`; a submitted value that is neither is an
  `InvalidContent`.
- The body's markup is `phil/markup`'s: the name of a format, a value of `MarkupFormat` — `markdown`, `html`, `bbcode`,
  `mediawiki`, `dokuwiki`, `creole`, `text` and `ujo` — or a format an add-on brings, the ports `MarkupCompiler` and
  `HtmlPurifier` (purifying at `Level::All`), and their adapters `DefaultCompiler` and `SanitizerPurifier`. A draft
  keeps the format its author writes in; a format the compiler does not know, and a UJO source that is not a valid
  document, are refused with `InvalidContent`.
- PHP interface for migrations (`ContentManager`), deletion of a root.
- Versions: freezing on publication, fork to the next version, latest version.
- Publication draft → live on `symfony/workflow`, the workflow `phil_content_publication`: `Publication`, a content
  in one locale; `PublicationWorkflow`; `PublicationMarkingStore`, which reads the draft row's place and publishes or
  takes offline through `ContentManager`; the service `phil_content.publication_workflow`, added to
  `workflow.registry` by `PublicationWorkflowPass` when the container has it. The publication date comes from the
  required `ClockInterface`, the container's `clock` service. Tested over in-memory ports with several locales, and
  in a kernel on SQLite, MySQL 8 and PostgreSQL.
- The body's lifecycle: publishing stores on the live row the draft's body converted into UJO — the UJO as its source,
  the format `ujo`, the HTML compiled from it and purified —, through the port `Application\Publication\BodyConverter`
  and its adapter `UjoBodyConverter` (`phil/markup`'s readers at `Level::Forum`, `phil/ujo`'s encoder). What the
  conversion does not keep, through the port `Application\Contract\ConversionLosses` and its `ConversionLoss` values,
  which `UjoBodyConverter` implements for a format of `phil/markup` or the name of a format an add-on brings, refuses
  the publication with `Application\Exception\PublicationLosesContent`, which lists the losses, until the editor
  accepts them: `ContentManager::publish(..., acceptLosses: true)`, `phil:content:publish --accept-losses` or a yes to
  its question in a terminal, the context `accept_losses` of the `phil_content_publication` workflow, or the box of the
  administration. The UJO's `time` is the moment of the
  publication, in milliseconds, from the clock. Raw HTML is published as text, a `raw` block of UJO included, unless
  `raw_html_role` names a trusted role (off by default), checked by Symfony's authorization checker (`RawHtmlRole`,
  with the optional `symfony/security-core`, behind the port `Application\Contract\RawHtmlPermission`) **on the
  author of each revision, never on the publisher**: the body records whether the editor who wrote its source held
  the role (`Body::$rawHtml`, the column `body_raw_html`, `bodyRawHtml` in the normalized form), a save that leaves the
  source and the format unchanged keeps that author, and a working copy keeps the role of the editor who saved it,
  whoever applies it (`Editing\AuthorRawHtml`, wired by `Infrastructure\Markup\RawHtmlAuthorPass`, the column
  `raw_html` of `phil_content_working_copy`). The raw HTML of an author who held the role is published as `raw`
  blocks, kept as `phil/ujo`'s `raw` profile allows, by any editor, and the draft, which its preview shows, renders it
  the same way (`BodyDataMapper` compiles it for `Level::All`); the raw HTML of any other author is published as text,
  even by a publisher who holds the role. The conversion runs before the
  publication changes anything; the columns are unchanged. Removing a draft puts back the frozen draft rows of the
  published version, localized and shared, so the author's text returns in its own format; a frozen row that no longer
  exists is replaced by its live twin.
- The events of `phil/publishing`: `ContentManager` dispatches `PagePublished` after a publication, and `PageRetired`
  after a locale is taken offline or for each live locale of a deleted root, through `Application\Publication\PageEvents`
  — the SEO canonical URL or else the absolute URL of the current route, the BCP 47 tag of the locale, « indexable »
  unless `noindex`, the content reference as owner identifier —, read from the live row and sent once the transaction
  returned; the port `Application\Contract\CanonicalAddresses` and its adapter `ContentCanonicalAddresses` over the
  router, `localhost` accepted in the kernel's debug mode only.
- Scheduled publication: the port `Application\Contract\PublicationSchedule` (`schedule`, `cancel`, `pending`) and
  `ScheduledPublication`; with `phil/scheduled-transitions-bundle`, its adapter `ScheduledTransitionsPublicationSchedule`
  over that bundle's `Scheduler`, `PublicationReferences` (`news:42@fr`) and `PublicationSubjects`, the resolver its
  workflow handler finds the publications by.
- The dated archive of the kinds under `phil_content.archives` — a « now » page —: `ContentArchive` (`history()`,
  `read()`) and `ArchivedVersion`, read from the frozen draft rows; `ArchiveController`, which serves one version with
  the content's template; the Twig function `content_archive`.
- Single kinds, `phil_content.singles` and `SingleKinds`: a second root refused, `ContentManager::single()` and the
  Twig function `content_single`.
- Working copies: `Domain\Draft\WorkingCopy` (table `phil_content_working_copy`, DBAL type
  `phil_content_working_copy_id`), the private autosave of each author and the named drafts of a content, 256 KiB of
  JSON at most; the port `Domain\Contract\WorkingCopyRepository` and its adapter `DoctrineWorkingCopyRepository`;
  `Application\Editing\WorkingCopies`, which applies them through `ContentManager::edit()`; `ContentManager::delete()`
  removes them.
- The administration (`phil_content.admin`): `ContentAdminController` — the list of contents by kind, creation, the
  editing page that saves, publishes, takes offline, discards, schedules, keeps and applies named drafts and restores
  the author's autosave, every form with its CSRF token —, its routes in `config/routes_admin.php`, its templates and
  its labels in the `phil_content` domain in English and French; `ContentAdmin::menuItems()` for an EasyAdmin
  dashboard; the Stimulus controller `phil--content-bundle--autosave`.
- The search feed of `phil/search-bundle`: `ContentDocumentProvider`, one document per live localized row — title, the
  UJO passage of the row's language as `body` and the others as `body_<language>`, the filters `kind`, `locale` and
  `reference` —, `noindex` left out; the key `phil_content.search_index`, `content` by default
  (`PhilContentBundle::SEARCH_INDEX`), read without loading the provider when the search bundle is not installed.
- `ContentManager::servedLocale()` and `exists()`.
- `PublishedContents` and `PublishedContent`: what a locale publishes, the latest first, read a page at a time in a
  few queries whatever their number (`find(PublishedRowQuery)`, `chunks()`, `latest(..., $withSlug)`), and the draft
  of a content.
- The port `PublishedRowRepository`, with `PublishedRowQuery` (filters on the root's fields and identifiers, rows
  with a slug, offset and limit), `PublishedOrder` (`Root`, `LatestPublished`, `Slug`) and `PublishedRow`; its
  Doctrine adapter `DoctrinePublishedRowRepository`, two queries per page; `RootIdentifiers`, shared with
  `DoctrineContentRootRepository`; `ContentAggregator::mergeRows()`, which merges rows already loaded;
  `DimensionContentRepository::findVersions()`, the rows of one locale in a stage, latest first.
- Doctrine storage: base classes, mapping listener, DBAL types, repositories and transaction, tested on SQLite,
  MySQL 8 and PostgreSQL.
- Route table, slug history and 301 redirects, resolver, tested on SQLite, MySQL 8 and PostgreSQL;
  `RouteRepository::findCurrentAmong()`, the current routes of many contents of one kind in one lookup.
- Controller and Twig rendering (template by key, content, SEO tags, URLs), tested in a kernel;
  `ContentUrlGenerator::generateAll()`, the URLs of many contents in one lookup of routes.
- `ContentSchema` and the Twig function `content_schema`: a normalized content as a schema.org `WebPage`,
  `Article` or the type a template asks for.
- The sitemap through `phil/sitemap-bundle`: `ContentUrlProvider`, a `Phil\Sitemap\UrlProvider` tagged
  `phil_sitemap.url_provider` after the sitemap's `RouteUrlProvider`, gives the published contents, one section per
  kind, each locale walked in chunks; a kind whose key ends in an underscore and digits, as `news_2026`, is the
  section `news-2026`.
- `FeedController` (the Atom feed of a kind, its `feed_limit` entries with a slug read in one page),
  `PreviewLinks`, `PreviewController` and `phil:content:preview-link` (signed links to a draft); their routes in
  `config/routes.php`; the keys `preview` and `feed_limit`; `ResourceRoutes::resourceKeys()`.
- Console commands: create, edit, publish, unpublish, list, tested in a kernel.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
