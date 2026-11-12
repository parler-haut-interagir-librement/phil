# Testing

| Area | What is covered |
|---|---|
| `ShareMetadata`, `ShareImage`, `Text` | Every field and its bounds, texts put on one line and cut, the addresses, locales and handles refused, `fromArray()` and what it refuses, addresses resolved in a page and read by phil/url, the invisible formatting characters removed |
| `MetaTags`, `MetaTag` | The exact tags of an article and of a plain page, their order, the card of X, escaping |
| `Color`, `CardTemplate`, `CardFont`, `CardImage` | The spellings of a colour, the defaults and bounds of a template, fonts read and refused, images within and beyond their bounds, the fingerprint |
| `LineBreaker`, `TextFitter`, `FittedText` | Line breaks in English, French and Japanese, the halving checked against a linear search, words cut, the ellipsis, the measures counted; the cut of a French title measured by GD in the box font |
| `GdCardRenderer`, `GdTextMeasure` | Margins, the accent, the background image, the logo, the subtitle's room, the compression, and three whole cards compared pixel by pixel with the cards GD 2.3.3 drew |
| `CardGenerator`, the stores, `Fingerprint` | Each card drawn once, every part of the fingerprint, atomic files, fingerprints refused |
| `TagReader`, `PageTags`, `LinkPreview`, `TagCheck` | The tags of a page, the bounds of the reader, pages that are not HTML, character sets, the fallbacks of a preview, every finding |
| `LinkFinder` | Links in order and once, the punctuation after them, parentheses and brackets opened or not, other schemes, the bounds of the text, of a link and of the count, invalid UTF-8 |

The tests ship their own font, a few kilobytes drawn for them, so that they draw the same pixels everywhere GD is the
same. It covers printable ASCII and `é`. The cut of a title by the measures of GD needs the ellipsis, and French titles
need the guillemets: `Tests\Support\BoxFont` writes, for the tests that need them, a TrueType font of boxes — each
character a filled box of its own advance — that covers printable ASCII, `é`, `«`, `»` and `…`, the ellipsis wider
than two letters, so that the last line of a cut title loses a letter to make room for it.

```sh
vendor/bin/phpunit packages/social-cards/tests
```

## Benchmarks

`CardRendererBench` draws a short title, a long one fitted by halving and one cut at its smallest size, plain or over
a background image with a logo. `TagReaderBench` reads a small head, a head of hundreds of tags and a page of 512 KiB
without a head. From the monorepo root:

```sh
composer bench -- packages/social-cards/tests/Benchmark
```

## Fuzzing

The target `social-cards-tags` reads pages nobody wrote, then makes their preview and their check, from the seeds of
`tests/Fuzz/corpus/social-cards-tags`:

```sh
composer fuzz -- social-cards-tags
```

The target `social-cards-links` finds the links of texts nobody wrote, from the seeds of
`tests/Fuzz/corpus/social-cards-links`: at most the links asked for, each once, each an `http` or `https` piece of the
text.

```sh
composer fuzz -- social-cards-links
```
