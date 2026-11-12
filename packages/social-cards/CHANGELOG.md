# Changelog

All notable changes to `phil/social-cards` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `ShareMetadata`: the title, description, canonical address, image, type, locale and alternate locales, site, author,
  published and modified times, and accounts on X and the fediverse of a page; texts put on one line and cut,
  addresses, locales and handles checked; `fromArray()` from the keys of a configuration or a template. `ShareImage`,
  `ShareType` and `TwitterCard`.
- `MetaTags` and `MetaTag`: the canonical link, the description, Open Graph, the `article:` tags of an article, the
  card of X and `fediverse:creator`, in that order, escaped for HTML.
- `CardTemplate`, `CardFont`, `CardImage` and `Color`: a declarative template of 1200×630 cards — background colour and
  image, accent band, logo, fonts and colours of the title and subtitle, margin, title sizes and lines —, its fonts read
  by phil/fonts, its images bounded, its fingerprint made of hashes rather than paths; `fromArray()`.
- `CardContent`, `LineBreaker`, `TextFitter` and `FittedText`: the title fitted by halving the range of its sizes, its
  lines broken by the rules of its language through ICU, a word wider than the card cut between two characters, the
  end cut with an ellipsis at the smallest size.
- `CardRenderer`, `TextMeasure` and `CardStore`, the ports of the drawing; `GdCardRenderer` and `GdTextMeasure`, the
  drawing with GD and FreeType, deterministic, as an opaque PNG at the highest compression.
- `CardGenerator`, `GeneratedCard` and `Fingerprint`: each card drawn once, under a fingerprint of the renderer, the
  template and the content; `DirectoryCardStore`, written atomically, and `InMemoryCardStore`.
- `TagReader` and `PageTags`: the title, canonical link and `<meta>` tags of a page of another site, bounded in bytes,
  tags, values and lengths; `LinkPreview`, the card of a link from them; `TagCheck`, `Finding` and `Severity`, what is
  missing or wrong.
- `LinkPreview` shows a link under the address of the page read: its `og:url` or canonical link replaces it only on
  the same host and port, so that a page cannot pass for another site; every address read in a page, and every
  address of a kept preview, goes through phil/url, which refuses an IP address, `localhost` and credentials.
- `CardImage` refuses an image above 16,777,216 pixels in all, read in its header before GD decodes it.
- The texts put on one line lose their invisible formatting characters, the controls of the direction of the text
  first, but for the zero-width joiner and non-joiner.
- `LinkFinder`: the links of a plain text, in order and once, without the punctuation that follows them nor a closing
  parenthesis or bracket they did not open, bounded in bytes, length and count; `first()`, the link a message
  previews.
- `SocialCardsException`, `InvalidShareMetadata`, `InvalidCardTemplate` and `CardRenderingFailed`.
- The benchmarks of the drawing and of the reader, and the fuzz targets of the reader and of `LinkFinder`.
- The scope of the drawing with GD written down: the scripts it draws, and those it cannot shape.
- `BoxFont`, the font of boxes the tests write, with the ellipsis and the guillemets the fixture font lacks.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
