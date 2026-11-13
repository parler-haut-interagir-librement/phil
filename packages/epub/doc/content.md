# Content documents

Each chapter's HTML becomes an XHTML content document: an XML document in the XHTML namespace, with the EPUB namespace
declared for `epub:type`, a `<title>`, the book's stylesheets and the converted body.

## From HTML to XHTML

The fragment is parsed by PHP's HTML5 parser (`Dom\HTMLDocument`), exactly as a browser reads it — unclosed elements
closed, tables completed, misnested tags repaired — then written back as XML:

- elements in their namespace: HTML in XHTML, `<svg>` with the SVG and XLink namespaces declared, `<math>` with the
  MathML namespace; void elements closed (`<br/>`); empty SVG and MathML elements closed;
- text and attribute values escaped, characters XML forbids removed;
- `epub:type`, `xml:lang` and `xml:space` kept; other prefixed attributes, attributes whose name XML refuses and
  namespace declarations of the source dropped;
- elements whose name XML refuses replaced by their content.

What a content document should not hold is left out: `script`, `noscript`, `template`, `style`, `link`, `meta`,
`title`, `base`, `object` and `embed` elements, `on…` event handler attributes, `srcset` and `sizes`. A duplicate
identifier is dropped after its first use, and an empty one dropped.

The input is expected to be purified HTML, such as the output of an HTML sanitizer or of `phil/ujo`. The conversion
removes what would make the document invalid or scripted; it is not a sanitizer of untrusted HTML.

The manifest declares `svg`, `mathml` and `remote-resources` on each document that holds them.

## Links

Every `href` of `<a>` and `<area>`, and of `<a>` in SVG, is checked:

| Address | Written |
|---|---|
| `#id` of the same chapter | as is |
| `#id` of another chapter | the path to that chapter and the fragment; the first chapter holding the identifier wins |
| a relative path to a chapter, with or without fragment | the path relative to the current chapter; a fragment the chapter does not hold is dropped, the link kept |
| a relative path to an asset — an image, an audio or a video file, a text track | the path relative to the current chapter |
| `http:`, `https:`, `mailto:`, `tel:` | as is |
| anything else: `/site/path`, `//host`, `javascript:`, `data:`, an unknown path, an unknown fragment | dropped; the text stays |

Query strings of relative paths are dropped. Percent-encoded paths and fragments are decoded before they are matched.

## Resources

Every `src` of `<img>`, `<audio>`, `<video>`, `<source>`, `<track>` and `<iframe>`, the `poster` of `<video>` and
the `href` of an SVG `<image>`, is checked against the book's assets:

| Address | Written |
|---|---|
| a relative path to an asset — an image, an audio or a video file, a text track | the path relative to the current chapter |
| an `http:` or `https:` address on audio or video | as is; the document is marked `remote-resources` |
| anything else | the element is left out — an image leaves its text alternative —, a poster is dropped |

EPUB does not allow remote images, nor files missing from the package: dropping them keeps the book valid.

## Mapping addresses

An application's HTML refers to its own addresses: `/media/42/map.png`, `/rules/combat`. `Book::withUrls()` maps each
address before the checks above; it returns the address to use — a path relative to the chapter, or an absolute URL —
or null to drop it:

```php
$book = $book
    ->withAsset(Asset::image('images/map.png', $storage->read('42/map.png')))
    ->withUrls(static fn (string $url): ?string => match (true) {
        str_starts_with($url, '/media/42/') => 'images/'.basename($url),
        str_starts_with($url, '/') => 'https://example.com'.$url,
        default => $url,
    });
```

## Splitting a fragment into chapters

`HtmlSplitter::split($html, $level)` cuts a fragment at its top-level headings of level `$level` or higher (`<h1>`
alone by default). Whatever comes before the first heading is a part of its own, unless it is only white space;
headings nested in other elements do not split.

```php
$chapters = array_map(
    static fn (string $part): Chapter => new Chapter($part),
    HtmlSplitter::split($articleHtml, 2),
);
```
