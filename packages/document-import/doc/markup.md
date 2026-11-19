# Markdown and HTML

Markdown and HTML are read by the readers of `phil/markup`, which write the same UJO blocks as the rest of PHIL.

## Choosing the readers

By default, Markdown is read by `MarkdownReader` — CommonMark, the GitHub extensions, footnotes, attributes, front
matter and the other extensions its documentation lists — with raw HTML escaped, and HTML by `HtmlReader`. Any
`Phil\Markup\Reader\MarkupReader` can be given instead:

```php
use Phil\DocumentImport\DocumentImporter;
use Phil\Markup\Reader\Markdown\MarkdownReader;

$importer = new DocumentImporter(markdown: new MarkdownReader(rawHtml: true));
$importer = new DocumentImporter(markdown: new MarkdownReader(dialect: $dialect));   // a dialect of an add-on
```

The document is the reader's, normalized by it; the metadata are its `Reading::$metadata`. Images of Markdown keep
their addresses: the importer does not fetch them.

## The images of an HTML paste

A word processor that copies to the clipboard writes its images in `data:` addresses. Before the HTML is read, each
image in a `data:` address — in Base64 or percent-encoded — becomes a `Media` named `image-1.png`, `image-2.jpg`…,
given to the media store, and the image points to the address the store answers. An image that is not one a browser
shows is reported and left to the reader, which drops `data:` addresses. Images at `http` or `https` addresses keep
them.

## The report

The elements the HTML reader leaves out with their content are reported by name, as `Omitted::Element`: `script`,
`style`, `iframe`, `frame`, `frameset`, `object`, `embed`, `applet`, `template`, `noscript`, `form`, `input`,
`button`, `select`, `textarea`, `svg`, `math`, `audio`, `video`, `canvas`. A Markdown import has an empty report:
the reader keeps every construct, as a block or as text.
