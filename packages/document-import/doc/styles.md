# Style map

A word processor names the role of a paragraph by its style. The importer knows the names word processors give their
headings, quotations and code — see [Word processing documents](word-processing.md) — but a publisher's template has
its own: `Chapter Title`, `Rules Box`, `Author Note`. A `StyleMap` tells the importer what each of them becomes.

```php
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;

$importer = new DocumentImporter(styles: new StyleMap([
    'Chapter Title' => StyleRole::Heading1,
    'Section' => StyleRole::Heading2,
    'Rules Box' => StyleRole::Quote,
    'Listing' => StyleRole::Code,
    'Author Note' => StyleRole::Omit,
    'Heading 1' => StyleRole::Paragraph,
]));

$map = new StyleMap()->with('Sidebar', StyleRole::Quote);
```

| `StyleRole` | Block |
|---|---|
| `Paragraph` | `paragraph` |
| `Heading1` to `Heading6` | `header` of that level |
| `Quote` | `quote` |
| `Code` | `code`, consecutive paragraphs of the style joined |
| `Omit` | Nothing: the paragraph is left out and reported as `style`, with the style's name |

## How styles are matched

A style is named as the word processor shows it — `Chapter Title`, `Heading 1` — or by its identifier in the file:
`ChapterTitle` in a DOCX, `Chapter_20_Title` in an ODT; an RTF style by its name only. Case and the spaces around
the name do not matter.

The map is read before the importer's own rules. A paragraph's style is looked up first, then the style it is based
on (`w:basedOn`, `style:parent-style-name`, `\sbasedon`), and the first name the map holds decides; only when the map
holds none of them do the built-in names and the outline levels apply. In a DOCX or an RTF, a paragraph whose style
the map names is never a list item, even when the style carries a numbering; in an ODT, a heading (`text:h`) whose
style the map names takes the map's block, while the paragraphs of a list stay its items.

## Formats

DOCX, strict DOCX, ODT, flat ODT and RTF read the map. Markdown and HTML carry no paragraph styles, and a PDF has
none: the map does not apply to them.

The map holds names only: it is a value, built once and shared by every import.
