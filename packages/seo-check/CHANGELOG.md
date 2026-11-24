# Changelog

All notable changes to `phil/seo-check` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-24

### Added

- `SeoChecker` and `Report`: the rules run on a published page, in order, and the points to correct listed by rule,
  severity — error, warning, information — and subject, with no overall score and never a block on the publication; a
  rule that throws is reported as a warning naming its class, and the other rules still run.
- `CheckedPage`, `RenderedPage` and `Rendering`: the page as published — canonical address, language, flag « indexable
  », whether it is indexable in each channel — and its renderings, at its own address, at the other paths that lead to
  it and for each channel, with their status and headers; `InvalidPage` and `SeoCheckException`.
- `PageCheckFactory`: the page to check built from phil/publishing's `PagePublished`, the port `RenderedPages`, which
  gives the HTML without a request, and the port `ChannelIndexing`.
- `MarkupReader` and `PageMarkup`: the page read once by `Dom\HTMLDocument`, as a browser parses it, its size, its
  elements and its texts bounded, its character set from `Content-Type` or the page; `Address`, the addresses of the
  page resolved and read by phil/url; `RobotsDirectives`, the directives of `<meta name="robots">`, of the `<meta>` of
  each crawler and of `X-Robots-Tag`; the fuzz target `seo-check-page`.
- The default rules, after the SEO audits of Lighthouse, through `DefaultRules`: `http-status`, `page-size`,
  `document-title`, `meta-description`, `document-language`, `single-h1`, `canonical` — on every path to the page —,
  `indexing` — by channel —, `hreflang` — valid and reciprocal through `RenderedPages` —, `crawlable-links`,
  `link-text` — with the generic texts of six languages in `GenericLinkTexts` — and `image-alt`; `broken-links`, from
  the port `LinkStatuses`; the public interface `SeoRule`.
- The classes of `Bridge\`, for the libraries suggested and installed in development: `structured-data`, by the
  `Validator` of phil/structured-data, `share-tags`, by the `TagCheck` of phil/social-cards, and
  `LinkCheckerStatuses`, the port `LinkStatuses` by the `LinkChecker` of phil/link-checker — a redirect `301` or `302`,
  a host that does not exist `404`, what the checker could not tell left out.
- `TextRenderer`, `JsonRenderer` and `HtmlRenderer` behind `ReportRenderer`; the messages of the domain
  `phil_seo_check` in English and French, by `Translation\Messages`.
- The documentation: installation with a listener of `PagePublished`, rules, extension points, testing and reference;
  the benchmark of a long page.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
