# Changelog

All notable changes to `phil/sitemap` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
