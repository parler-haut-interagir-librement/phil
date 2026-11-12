# Changelog

All notable changes to `phil/structured-data-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- The option `allow_localhost`, `false` by default, also the container parameter
  `phil_structured_data.allow_localhost`: `true`, in development and tests, lets the factories of `Schema` (set when
  the bundle boots), the nodes of the configuration built with the container, its addresses, and the validator of
  `phil:structured-data:check` take an address on `localhost` or under `.localhost`.
- The addresses of the configuration read by `phil/url`: an address with a user name or a password, or on an IP
  address, is refused, and an international host accepted.
- `PageStructuredData`, the `NodeCollector` of the current page: the nodes added by controllers, templates and
  packages, those of the providers and of the `CollectStructuredData` listeners asked once per page, held by the main
  request so that a fragment adds to its page, reset by the kernel between requests; `about()`, `indexing()` and
  `isPrintable()`, the reach of the records the page shows and of the `Indexing` its request carries, read from
  phil/indexing-consent.
- `StructuredDataProvider`, autoconfigured, run after the nodes of the configuration; `PageContext`, the addresses,
  language and request of the page, with its stable identifiers; the event `CollectStructuredData`.
- `DefaultNodes`: the site as a `WebSite` with its search, its owner as an `Organization` or one of its kinds with its
  address and profiles, and each page as a `WebPage` part of the site, built by `Schema` once when the container is
  built.
- `MenuBreadcrumbProvider`: the breadcrumb of a phil/menu-bundle menu as the page's `BreadcrumbList`, built by
  `Schema`, referred to by its `WebPage`.
- `PageShareMetadata`: the title, description and image of phil/social-cards-bundle read from the graph of the page,
  whose providers are asked once.
- `StructuredDataExtension`: the Twig functions `structured_data()`, one script element with the nonce given or that
  of a `ScriptNonceProvider`, nothing for a page that shows a record withdrawn from search engines,
  `structured_data_add()` and `structured_data_about()`; `RequestAttributeNonce`, the nonce held by an attribute of
  the request; `ContentSecurityPolicyNonce`, the script nonce of phil/browser-security-bundle's policy.
- `CheckStructuredDataCommand`, `phil:structured-data:check`: the JSON-LD of a page at its address or in a file,
  bounded in size and time, reported as text or JSON, failing on an error or, with `--strict`, a warning.
- `PhilStructuredDataBundle`, extension alias `phil_structured_data`, keys `base_url`, `language`,
  `request_language`, `website`, `organization`, `web_page`, `breadcrumb`, `nonce` (`request_attribute`,
  `content_security_policy`) and `check`, each address, language and node of the configuration checked when the
  container is built.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
