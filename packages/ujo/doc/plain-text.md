# Plain text

`PlainText::convert($document)` gives the text of a document — for a search index, an excerpt, an e-mail, a summary
read aloud —: each block's text without HTML, decoded, blocks separated by a blank line, empty ones left out.

| Type | Text |
|---|---|
| `paragraph`, `header`, `alert`, `runningFooter`, `footnote`, `raw` | the text |
| `banner`, `watermark`, `code`, `mermaid`, `math` | the plain value |
| `quote`, `credit`, `personality` | the parts joined by ` — ` |
| `warning` | title and message |
| `delimiter` | `***` |
| `list` | one item per line, `-`, `1.` or `[x]`/`[ ]`, two spaces per level |
| `definitionList` | each term, then its definitions indented |
| `table` | one row per line, cells separated by ` | ` |
| `index` | the title, then `term, pages`, two spaces per level |
| `image`, `embed`, `gallery` | the captions; an image without one, its `alt` |
| `linkTool` | the title and the address |
| `attaches` | the title, or the file's name |
| `button` | the text and the address in parentheses |
| `toggle`, `note`, `descriptive` | the title, then the nested blocks |
| `wide`, `columnFlow`, `container`, `columns` | the nested blocks |
| `statblock` | name, subtitle, then each section: title, `label: value`, abilities, nested blocks |
| page markers, decorations, `chart`, `qrCode`, `toc`, and any unknown type | nothing |

`with()` replaces the conversion of a type, or adds one:

```php
$text = new PlainText()->with('callout', static fn (Block $block, PlainText $text): string => $text->text($block->data['text'] ?? null));
```

`passages($document, $language)` gives the text by language, for a search index that reads each passage with the
analyser of its language: each block at the top of the document goes to the language its `language` tune names, in
lower case, or to the language given for the document; inline passages (`<span lang>`) stay with their block. The
languages come in the order of their first block, and a language whose blocks give no text is left out.

```php
foreach (new PlainText()->passages($document, 'fr') as $language => $text) {
    $fields['body_'.str_replace('-', '_', $language)] = $text; // body_fr, body_en_ca…
}
```

The conversion receives the converter, whose `text()` strips HTML and whose `blocks()` converts nested blocks.
`Ujo::toText()` normalizes the document first.
