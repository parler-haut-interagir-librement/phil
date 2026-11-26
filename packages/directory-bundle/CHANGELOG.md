# Changelog

All notable changes to `phil/directory-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-25

### Changed

- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `twig/twig` 3.31,
  `phil/content-bundle` 0.3), and of each development requirement (`doctrine/doctrine-bundle` 3.4).

### Security

- Requires `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on 2026-11-24.

## 0.2.0-alpha - 2026-11-20

The genre and the author of a listing, and who wrote a version, compare byte by byte
on MySQL and MariaDB. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event after the mapping listener of
  content-bundle, its `FIELDS` and its `EDITED_BY_FIELDS`: the fields that compare byte by byte on MySQL and MariaDB,
  by entity and on every class that implements `EditedByCapable`.

### Changed

- On MySQL and MariaDB, `genre` and `author` of `phil_directory_listing`, and `edited_by` of
  `phil_directory_listing_dimension`, take the `utf8mb4` character set and the `utf8mb4_bin` collation in the schema
  the ORM generates, through `Infrastructure\Doctrine\BinaryKeysListener`.
  PostgreSQL and SQLite keep their default, which compares bytes already. The migration, which
  `doctrine:migrations:diff` writes:

  ```sql
  -- MySQL and MariaDB
  ALTER TABLE phil_directory_listing
      MODIFY genre VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
      MODIFY author VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL;
  ALTER TABLE phil_directory_listing_dimension
      MODIFY edited_by VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL;
  -- PostgreSQL and SQLite: nothing to run.
  ```

  Apply it with `doctrine:migrations:migrate`, or run the statements by hand on MySQL and MariaDB; the duplicate check
  to run first is in the upgrade guide.

- `doctrine/dbal` is required, already required by `doctrine/orm`: `BinaryKeysListener` reads the platform of the
  connection.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, a query on `author` for the user `Ann` — an
  application's list of a user's listings — also matched the listings of `ann`, and a row written in the genre
  `Podcast` outside the bundle was listed under `podcast`. Erasing the user `Ann` also emptied `edited_by` on the
  versions `ann` wrote.

## 0.1.2-alpha - 2026-11-18

### Changed

- `phil/content-bundle` 0.3 is accepted beside 0.1 and 0.2.

## 0.1.1-alpha - 2026-11-17

### Changed

- `phil/content-bundle` 0.2 is accepted beside 0.1.

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
