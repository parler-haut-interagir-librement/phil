# Conversion into UJO

`Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter`, a public service, converts a source in any enabled format
into UJO (Universal JSON Output), the block document format of `phil/ujo`: what a block editor opens, and what a text
keeps once it is published.

| Method | Gives |
|---|---|
| `reader($format = null, $level = null)` | The `MarkupReader` of a format, at a level |
| `read($source, $format = null, $level = null)` | The `Reading`: the UJO `Document` and the metadata written apart from the text |
| `toJson($source, $format = null, $level = null, $pretty = false)` | The UJO JSON of the document |

A format and a level are given as the enums of `phil/markup` or as their values — a format of an add-on by its name —; `null` takes the `default_format`
and the `default_level` of the [configuration](configuration.md). A format not enabled throws `UnavailableFormat`, a
UJO source that is not valid `InvalidSource`; the readers of the formats people write never throw.

```php
$reading = $converter->read("---\ntitle: Notes\n---\n# Hello", 'markdown');
$reading->metadata;                         // ['title' => 'Notes']

$json = $converter->toJson('[b]Bonjour[/b]', 'bbcode');
// '{"blocks":[{"id":"b1","type":"paragraph","data":{"text":"<b>Bonjour</b>"}}]}'
```

## Every format

Markdown, BBCode, MediaWiki, DokuWiki, Creole and HTML are read by the readers of `phil/markup`, and the formats of
add-ons by the readers they declare, each
feature mapped to the UJO block of the same role; plain text becomes one paragraph per run of lines; UJO is read and
checked by `phil/ujo` and written back in canonical form. The correspondence of every feature in every format is in
`phil/markup`'s documentation of the formats.

## The level

The level a source is read at decides one thing: raw HTML written in it — a `<div>` in Markdown, `[html]` in BBCode,
`<html>` in a wiki — becomes a `raw` block at `all` and escaped text below. The level asked is lowered to the ceiling
of the format, as for the filters: with `levels: { bbcode: forum }`, the raw HTML of a BBCode source is text whatever
the call asks. The document is not purified: its blocks keep their headings and tables, and the purifier applies when
the document is rendered and shown.
