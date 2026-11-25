# Changelog

All notable changes to `phil/legal-notices-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Changed

- The development requirements accept `phil/markup` 0.3 and `phil/privacy-bundle` 0.3, the minor versions of their main
  branches and no earlier one, so that the package installs on its own beside those packages.
- Requires the latest minor of each dependency (`twig/twig` 3.31, `symfony/ux-twig-component` 3.8,
  `symfony/translation-contracts` 3.8, `twig/intl-extra` 3.31), and of each development requirement
  (`symfony/ux-toolkit` 3.8, `twig/extra-bundle` 3.31, `twig/html-extra` 3.31).
- The development requirements take `twig/html-extra` 3.31.1 or later, which fixes the advisories of its sandbox
  published on 2026-11-24.

### Security

- Requires `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on 2026-11-24.

## 0.1.0-alpha - 2026-11-15

### Added

- `PhilLegalNoticesBundle` and its configuration `phil_legal_notices`: the identity of the site, the versions of the
  legal notice and the privacy policy with their bodies inline or in files, a source of the application instead, the
  retention periods, the consent categories, the renderer, the pages, the footer and the sitemap section.
- The checks of the container build: the identity, every version, the consent categories, the files of the bodies,
  and the fields each published or withdrawn document requires.
- `ConfigDocumentSource`, the versions of the configuration, built once.
- `MarkupBody`, the bodies rendered and purified by phil/markup; the plain text renderer without it.
- `ScheduleRetentionDurations`, the retention periods of the schedule of phil/privacy-bundle, with their labels by
  locale; `ConfiguredConsentCategories`, the consent categories of the configuration.
- `DocumentController` and the routes of `config/routes.php`: `/legal` and `/privacy`, their `/history` and each
  version at `/v{number}`, cached, with their `Content-Language`; the templates in English and French, every word and
  mark of punctuation from the `phil_legal_notices` translation domain, the retention periods and the consent
  categories under the privacy policy, their cookies as a list in the language of the page by the `format_list` filter
  of twig/intl-extra, whose extension the bundle registers when TwigExtraBundle does not.
- The pages drawn by the recipes `badge` and `table` of the PHIL UX kit, or of the Shadcn kit of the Toolkit on
  Tailwind, installed by the Symfony UX Toolkit and listed in `PhilLegalNoticesBundle::RECIPES`; `KitComponents`,
  `KitSubdirectoryPass` and the option `pages.kit_subdirectory`, which name them after the components subdirectory of
  the Toolkit.
- `FooterLinks` and the Twig functions `phil_legal_notices_links()` and `phil_legal_notices_kit()`, declared by
  attributes on the runtime `LegalNoticesExtension`, with the policies of phil/terms-bundle and the links
  of the services tagged `phil_legal_notices.footer_link`, through `FooterLinkProvider`; the template `_links.html.twig`.
- `phil:legal-notices:check`, what the public documents lack; `phil:legal-notices:announce`, a `DocumentPublished` for
  each version published in the last days.
- `LegalUrlProvider`, the pages of the documents in force for phil/sitemap-bundle.
- `LegalPagesStructuredData`, the pages of the documents as `WebPage` nodes for phil/structured-data-bundle.
- The Flex recipe in `recipe/0.1`: the bundle registered, the routes in English and French, a configuration whose
  legal notice and privacy policy are drafts, and their bodies in `legal/`, in English and French.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
