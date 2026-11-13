# Installation

```sh
composer require phil/citations
```

The package needs PHP 8.5 or later with `ext-intl` (the collation of bibliographies, Unicode normalisation,
transliteration of made ids), `ext-mbstring` and `ext-ctype`.

No configuration and no service are needed. Every class keeps nothing between two texts: a reader, a processor or a
style built once can serve every request of a worker. The package holds no CSL style file and calls no outside
service; references come from the application, from CSL-JSON, BibTeX or RIS.
