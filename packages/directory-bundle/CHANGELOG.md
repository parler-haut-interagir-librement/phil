# Changelog

All notable changes to `phil/directory-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- The option `allow_localhost`, `false` by default: `true`, in development and tests, lets the pages of the sitemap be
  on `localhost` or under `.localhost`; `DirectoryUrlProvider` takes it as its last argument.
- `Listing` (table `phil_directory_listing`): a genre, an author and up to 50 editors; `ListingDimension` (table
  `phil_directory_listing_dimension`) with the content capabilities workflow, SEO, excerpt, routable and body.
- The capabilities contact, address, location and edited-by, with their mappings, data mappers, mergers and
  normalizers.
- `Directory`: `create()`, `edit()`, `publish()`, `addEditor()`, `removeEditor()`, `changeIndexing()`, `draft()`,
  `published()` (with an offset, a limit and the reader of phil/indexing-consent), `find()`, `history()`, which reads
  a locale's versions in one query; a change by an author or an editor waits for an administrator unless
  `publish_every_change` is on.
- Relations between genres, the key `relations`: `Relation`, `Relations` and `RelatedListings`; the capability
  related (`RelatedCapable`, `RelatedTrait`, `RelatedPipeline`, the JSON column `related`); `Directory::related()` and
  `relationsFrom()`, the related listings checked on `create()` and `edit()`; a choice per relation in `ListingType`,
  the option `relations`; the related listings on the page of a listing. The related listings a listing names are
  read by their identifiers, and so are those that name it, looked up first among the live rows of their genre
  through the port `RelatedLookup` and its Doctrine adapter `DoctrineRelatedLookup`.
- The list of a genre, the related listings and the sitemap read the published listings in a few queries, filtered
  on the genre column, through content-bundle's `PublishedRowQuery`. A listing published without a slug has no page,
  and is left out of them.
- Who finds a listing, as its author decides with phil/indexing-consent: `Listing` implements `HasIndexingConsent`,
  its choice kept in the columns `indexing` and `site_listed` and changed at once by its author or an administrator,
  from the page of its editors; the list of a genre and the related listings leave out an unlisted listing, the
  sitemap any listing that is not listed, in the same query; the page of a listing that is not listed sends
  `noindex, noarchive` in `X-Robots-Tag` and `<meta name="robots">`, and carries the choice under
  `IndexingPolicy::REQUEST_ATTRIBUTE` for the structured data of the page.
- `Directory::eraseUser()`, through the port `UserErasure` and its Doctrine adapter `DoctrineUserErasure`: an erased
  account's identifier leaves the listings it wrote or edits (`Listing::forget()`) and the `editedBy` of every version
  it wrote; the documentation says which identifiers are kept, for how long, and who sees them.
- `PhilDirectoryBundle`, extension alias `phil_directory` (`genres`, `admin_role`, `publish_every_change`,
  `sitemap_locale`, `words`, `page_size`, `relations`); it prepends the Doctrine mapping, the identifier type and the
  content root.
- `ListingVoter`: `phil_directory.edit`, `phil_directory.editors`, `phil_directory.publish`.
- `ListingType`, `EditorsType`, `DirectoryController` and the route loader `phil_directory`: the list, a listing, its
  form, its editors, its history and its publication, for each genre. The list of a genre shows a page of
  `page_size` listings (50 by default, 1 to 500), `?page=2`, with links to the previous and the next page.
- `DirectoryUrlProvider`: one sitemap section per genre, read in chunks of 500; a genre whose key ends in an
  underscore and digits, as `season_2`, is the section `season-2` (`Sitemap::sectionNamed()`).
- The templates under `@PhilDirectory`, the translations `phil_directory` in French and English.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
