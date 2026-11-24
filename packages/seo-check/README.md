# phil/seo-check

**Find what keeps a page out of search results when it is published: title, description, language, canonical,
indexing, hreflang, links and images.**

phil/seo-check is a plain PHP library. It reads a page as the application renders it, at the moment phil/publishing
announces it with `PagePublished`, and lists the points to correct, after the SEO audits of Lighthouse:

- the response answers with a success, and the page is not larger than the check reads;
- one `<title>` and one meta description, of a length a results page shows whole;
- the `lang` of the document, and its `Content-Language` header, are the language the page was published in;
- one first-level heading;
- one canonical link, absolute, naming the address the page was published at — and every other path that leads to the
  page, `/` and `/fr/` for a home page, names the same;
- the indexing directives, `<meta name="robots">` and `X-Robots-Tag`, agree with the flag « indexable », in each
  storefront or channel the application serves the page in;
- the `hreflang` translations are valid and reciprocal, `x-default` optional;
- links search engines can follow, with a text that says where they lead, and images with an `alt`;
- no dead link, from the statuses a link checker last saw;
- with phil/structured-data, valid JSON-LD; with phil/social-cards, complete share tags; with phil/link-checker, the
  statuses of the links from its checker.

The report is a list, rendered as text, JSON or a fragment of HTML for an administration. It has no overall score, and
it never stops a publication: the page is already online when it is checked. It measures no keyword density and no
readability score.

The HTML is read by PHP's HTML5 parser, `Dom\HTMLDocument`, at a bounded cost; addresses by phil/url. The check sends
no request: the application gives the HTML of its pages through the port `RenderedPages`, and the statuses of links
through `LinkStatuses`.

## Status

| Part | State |
|---|---|
| `SeoChecker`, `Report`, the default rules, the markup reader | Implemented, tested and fuzzed |
| `PageCheckFactory`: the page from `PagePublished` and `RenderedPages`, indexing by channel | Implemented and tested |
| The dead links, through `LinkStatuses` | Implemented and tested |
| `Bridge\LinkChecker\LinkCheckerStatuses`, `LinkStatuses` by phil/link-checker | Implemented and tested |
| The rules of `Bridge\`: structured data, share tags | Implemented and tested |
| The text, JSON and HTML renderers; the messages in English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and checking each page as it is published. |
| [Rules](doc/rules.md) | Each rule, what it reports, and why. |
| [Extension points](doc/extension-points.md) | Writing a rule, the ports, the messages, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests, its fuzz target and its benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/seo-check
```

```php
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Render\TextRenderer;
use Phil\SeoCheck\RenderedPage;
use Phil\SeoCheck\SeoChecker;
use Phil\Url\Url;

$url = Url::fromString('https://example.org/fr/news/opening');
$page = CheckedPage::of($url, 'fr', true, [new RenderedPage($url, $html, 200, $headers)]);

echo new TextRenderer()->render(new SeoChecker()->check($page));
```

```text
2 point(s) to correct.
[error] canonical: The canonical link names https://example.org/fr/news, but the page was published at https://example.org/fr/news/opening.
[warning] image-alt: The image /logo.png has no alt attribute: describe it, or give it alt="" when it is only decoration.
```

`check($page, 'fr')` writes the same report in French. [Installation](doc/installation.md) shows a listener of
`PagePublished` that checks each page as it is published.

## Requirements

PHP 8.5 or later, with the extensions `dom`, `libxml`, `mbstring` and `uri`; phil/publishing, phil/url and
symfony/translation 8.2. phil/structured-data, phil/social-cards and phil/link-checker are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
