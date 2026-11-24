# Changelog

All notable changes to `phil/newsletter-archive` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.0-alpha.

### Added

- `PhilNewsletterArchiveBundle`, a Symfony bundle on `phil/newsletter-bundle` 0.3 or later, and its configuration:
  `enabled`, `connection`, `page_size`, `slugger` and `slug_max_length`.
- `ArchivedIssue`: the web version of one sent campaign, its subject, preheader, list, tags and slug, published,
  shared by a link, or both; `unpublish()` withdraws both. `Tag`, `ArchiveFilter`, `IssuePage`, the port
  `ArchiveRepository`, `NewsletterArchiveException` and `InvalidArchiveValue`.
- `Archive`: `publish()`, `unpublish()`, `share()`, `revokeLink()` and `retag()`; only a sent campaign is archived, and
  only when an editor says so, `NotArchivable` otherwise. A slug made from the subject by a `phil/slug` slugger, set
  apart by `-2`, `-3`…, kept once given.
- Links by opaque tokens of 32 random bytes, of which the archive keeps the SHA-256 alone: `LinkTokens`; sharing again
  replaces the link.
- `NeutralWebVersion`, behind the port `WebVersionRenderer`: the web version rendered with neutral data in place of
  the subscriber's, without unsubscription or preference link, token, tracked link, tracking pixel or hidden element,
  purified by `phil/markup`, frozen at publication. Erasing a subscriber leaves the archive as it is.
- `ArchiveReader`, and `IssueFeed` with `FeedItem`, the latest published issues for a feed; the port `IssueUrls` and
  `RouterIssueUrls`.
- `DbalArchiveRepository`, `ArchiveSchema` on the tables `phil_newsletter_archive` and `phil_newsletter_archive_tag`,
  instants in `datetime_utc_immutable`, and `ArchiveSchemaListener` for the ORM.
- The pages: the list, a page at a time, filtered by list and by tag; an issue at its slug, with its canonical address
  and Open Graph data; a shared issue, never cached nor indexed, without `Referer`; 404 for what is not public. Their Twig
  templates and their texts in English and French.
- The command `phil:newsletter-archive`: publish, unpublish, share, revoke and retag.
- The Flex recipe, in `recipe/0.1`.
- The fuzz target of the link tokens.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
