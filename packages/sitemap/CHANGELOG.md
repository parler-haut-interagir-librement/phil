# Changelog

All notable changes to `phil/sitemap` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-11

### Security

- An address — of a page, an alternate, an image, a sitemap file — is read by `phil/url` as a browser reads it, no
  longer by `filter_var(FILTER_VALIDATE_URL)`, which took a user name for a host: `https://example.com@evil.example/`,
  a page on evil.example, passed as a page of example.com. An address with a user name or a password, or whose host
  is an IP address (`https://127.0.0.1/`, `https://2130706433/`) or a single name (`http://intranet/`), or with
  port 0, is now an `InvalidSitemap`.

### Added

- `allowLocalhost`, `false` by default, on `Url`, `Alternate`, `Image` and `XmlWriter::index()`: `true`, in
  development and tests, lets an address be on `localhost` or under `.localhost`; an IP address stays refused.

### Changed

- `Url::$loc`, `Alternate::$href` and `Image::$loc` hold the address in its canonical form, which the XML writes:
  scheme and host in lower case, an international host in its ASCII form (accepted, where it was refused), the
  default port dropped, the root path written `/`, the segments `.` and `..` resolved, escaped or not, and the
  characters a part cannot hold percent-encoded, `"` among them. The constructors keep their arguments, `allowLocalhost` added.
- The package requires `phil/url` 0.1.3 instead of `ext-filter`.

## 0.1.0-alpha - 2026-11-11

### Added

- `Url`, `ChangeFrequency`, `Alternate` (`hreflang` alternates) and `Image`, checked as the protocol and the search
  engines expect; `InvalidSitemap`.
- `UrlContainer` and `UrlProvider`, the contract of the parts of a site that add pages.
- `Sitemap`: pages by section, maybe of one section only, cut into files of 50,000 pages at most. A section may not
  end in an underscore and digits (`news_1`), the names of the numbered parts of a section;
  `Sitemap::sectionOfFile()` reads the section of a file's name, and `Sitemap::sectionNamed()` gives a key that ends
  so — `news_2026` — the section `news-2026`.
- `XmlWriter`: the `urlset` of a file, with alternates and images, and the `sitemapindex` of the files.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
