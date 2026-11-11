# The wikis

`Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader` and `Reader\Creole\CreoleReader` read the
syntax of three wikis into a UJO document, which `phil/ujo` renders as HTML. Each reads the native syntax of its wiki
— headings, emphasis, links to pages and to other sites, lists, tables, preformatted text, text kept as written,
images, rules — and the extensions every wiki of PHIL shares, written as the wiki writes its own: tags in MediaWiki
and DokuWiki, `<note>`, placeholders in Creole, `<<note>>`; templates and magic words in MediaWiki, `{{TOC}}`,
`__TOC__`; macros in DokuWiki, `~~TOC~~`. Together they give each wiki every feature of the Markdown reader.
[Formats](formats.md) puts each syntax beside those of the other formats.

```php
$reading = new MediaWikiReader()->read("== Rules ==\nRoll '''two''' dice.<ref>Six-sided.</ref>");

$reading->document->blocks[0];   // a `header` block, level 2, its anchor `rules`
$reading->document->blocks[2];   // the `footnote` block, `Six-sided.`
```

`new MediaWikiReader(true)` keeps raw HTML; `DefaultCompiler` asks for it at the level `all` only. A second argument
replaces the `phil/ujo` normalizer the document is checked by; a third, a `Reader\Wiki\WikiLinks`, says where pages
and files are found.

## Pages and files

A link to a page, `[[Main Page#History]]`, goes to the path of the page, its spaces underscores, its other unsafe
characters encoded, and its section an anchor made as the readers make the anchors of headings: `/Main_Page#history`.
A file, `[[File:map.png]]` or `{{map.png}}`, goes to the path of the media. A web address is kept as written.

```php
new MediaWikiReader(false, null, new WikiLinks(pages: '/wiki/', media: 'https://cdn.example.com/'));
```

Both paths start with `/` by default, so that a name holding `:` — a namespace, `Help:Contents` — is never read as a
scheme. DokuWiki writes its page names in lower case, as DokuWiki does.

## The extension tags

A tag is written `<name>`, `<name flag key="value">` or `<name/>` in MediaWiki and DokuWiki, `<<name …>>` in Creole,
its name in any case, and closed by `</name>` or `<</name>>`. A value holding spaces is quoted.

### Inline

| Tag | UJO |
|---|---|
| `<b>`, `<strong>`, `<i>`, `<em>`, `<u>`, `<ins>`, `<s>`, `<del>`, `<strike>`, `<sup>`, `<sub>`, `<mark>`, `<tt>`, `<kbd>` | `<b>`, `<i>`, `<u>`, `<s>`, `<sup>`, `<sub>`, `<mark>`, `<code>` |
| `<span>`, `<small>`, `<big>` | the text alone |
| `<code>…</code>` closed on its line, `<nowiki>…</nowiki>` | `<code>`, or the text, as written |
| `<br>`, `<br/>` | `<br>` |
| `<footnote>…</footnote>`, `<ref>…</ref>`, `<ref name="a">…</ref>`, `<ref name="a"/>` | `<sup data-footnote>` where it is written, and a `footnote` block at the end; a named footnote is referenced again by its name |
| `<spoiler>…</spoiler>` | `<span class="spoiler">` |
| `<color red>…</color>`, `<color color="#ff0000">` | `<span class="text-color" data-color>`; any other value keeps the text alone |
| `<icon fas_fa-dragon>`, `<hspace 10px>`, `<nbsp>` | `<i data-icon>`, `<span data-hspace>`, U+00A0 |
| `<var name="value">`, `<var name>` | defines a variable, then writes its value; a variable not defined yet keeps the tag as written |
| `<html>…</html>` | its HTML kept at `all`, escaped below |
| `<references/>` | nothing: the footnotes are added at the end |

A tag not known, or left open, is text. A tag holding a tag of its own name closes at the first closing tag.

### Blocks

A container opens with its tag at the start of a line, and holds the blocks up to its closing tag — on the same line
or a line after it — or up to the closing tag of a container around it, or to the end. `<note>a</note>` is a note
holding a paragraph.

| Tag | UJO |
|---|---|
| `<note title="…">`, `<descriptive title="…">` | `note`, `descriptive`, the title given or that of the heading they start with |
| `<warning title="…">`, `<alert info>` and the other alert types | `warning`, `alert` |
| `<note warning>`, `<note important>`, `<note tip>` | `warning`, `alert` of the type `danger`, of the type `success`: the note plugin of DokuWiki |
| `<quote author="…">`, `<blockquote author="…">` | `quote`, the author as its caption |
| `<spoiler title="…">`, `<toggle>`, `<hidden title="…">` | `toggle`, closed |
| `<wide>`, the flag `wide` on a box | `wide` |
| `<block class>`, `<div class="…">`, `<WRAP class>` | `container`, for a look that declares the classes |
| `<columns>`, `<column>`, `<newcolumn>` | `columns`, six at most, the blocks of the others joined to the sixth; without a column, `columnFlow`, `<columns 3>` |
| `<statblock name="…" subtitle="…" frame wide>`, `<statblock spell>`, `<monster>` | `statblock`, read as the Markdown reader reads a creature |
| `<credit title="…">`, `<artist>` | `credit` |
| `<classtable frame decoration>` | the `variant` tune on the tables inside |
| `<center>`, `<left>`, `<right>`, `<justify>`, `<align right>` | the `alignment` tune on each block inside |
| `<footer>`, `<watermark>`, `<banner>` | `runningFooter`, `watermark`, `banner` |

Every container takes the attributes `id` — the `anchor` tune of the block it makes —, `class` — a `container`
around it —, `align`, `width`, `height` and `wrap="left"` or `wrap="right"`. Containers nest as deep as UJO allows:
the tag of a container deeper is left out, its blocks read as those of the container around it. A closing tag that
closes nothing open is left out.

| Tag that holds nothing | UJO |
|---|---|
| `<toc title="…" depth=4 wide>` | `toc` |
| `<pagebreak>`, `<pagebreak frontCover>`, `<columnbreak>` | `pageBreak`, then `pageStyle` for the classes or the cover given; `columnBreak` |
| `<spacer 3>`, `<pagenumber 12>`, `<pagenumber skip>`, `<pagenumber reset>`, `<decoration 12 color=#rrggbb kind=ornament>` | `spacer`, `pageNumber`, `decoration` |

| Tag whose content is kept as written | UJO |
|---|---|
| `<code php>`, `<file php name.js>`, `<syntaxhighlight lang="php">`, `<source lang="php">` | `code`, its language |
| `<pre>`, `<nowiki>` on lines of their own, `<code>` without a language and not closed on its line | `code` |
| `<html>` on lines of their own | `raw` at `all`, its text escaped below |
| `<metadata>` YAML, `<style>` CSS | `Reading::$metadata`, the style under `style` |

## MediaWiki

| Syntax | UJO |
|---|---|
| `= … =` to `====== … ======` | `header`, its level the fewer of the signs that open and close it |
| `''italic''`, `'''bold'''`, `'''''both'''''` | `<i>`, `<b>`; a fourth apostrophe and those beyond five are apostrophes; a mark left open is text |
| `[[Page]]`, `[[Page\|text]]`, `[[Page#Section\|text]]`, `[[#Section]]`, `[[dog]]s` | a link to the page, the letters after it part of its text |
| `[[Category:…]]` | nothing; `[[:Category:…]]` is a link |
| `[https://… text]`, `[https://…]`, `[mailto:…]`, an address in the text | a link |
| `[[File:a.png\|thumb\|left\|200px\|alt=…\|Caption]]`, `[[Image:…]]` | `image`: `left` and `right` wrap the text around it, `center` centers it, `200px`, `200x100px` and `x100px` size it, `class=` adds classes, `alt=` is its alternative text, the last other option is its caption |
| `*`, `#`, `**`, `#*`; `*:` | `list`, a sign more for each level; a line ending its signs with `:` continues the item before it |
| `; Term : Definition`, `: Definition` | `definitionList` |
| `:`, `::` not after a term | `paragraph`, its `indent` tune |
| `{\|` … `\|}`, `\|-`, `! a !! b`, `\| a \|\| b`, `\| style="…" \| a` | `table`; a line of text continues the cell or the caption before it; the caption `\|+` its `caption`; `colspan`, `rowspan`, `align` and the `text-align` of `style` kept in the rich form |
| a line that starts with a space | `code` |
| `----` | `delimiter` |
| `&nbsp;`, `&amp;`, `&#233;` | their characters |
| `__TOC__`, `__FORCETOC__`; `__NOTOC__` and the other magic words | `toc`; nothing |
| `<!-- … -->` | nothing |
| `{{TOC\|title=…}}`, `{{Page break\|frontCover}}`, `{{Column break}}`, `{{Spacer\|3}}`, `{{Page number\|12}}`, `{{Decoration\|12\|color=#rrggbb}}` alone on a line | the blocks of the tags of the same name |
| `{{Footnote\|…}}`, `{{Spoiler\|…}}`, `{{Mark\|…}}`, `{{Color\|red\|…}}`, `{{Icon\|…}}`, `{{Hspace\|…}}`, `{{nbsp}}`, `{{u\|…}}`, `{{s\|…}}`, `{{sup\|…}}`, `{{sub\|…}}`, `{{code\|…}}` | the inline elements of the tags of the same name |
| `{{#vardefine:name\|value}}`, `{{#var:name}}`, `{{var\|name}}` | a variable defined, its value |

A template is named in any case, its spaces and underscores left out: `{{Page break}}` is `{{pagebreak}}`. A
template not known is text.

## DokuWiki

| Syntax | UJO |
|---|---|
| `====== … ======` to `== … ==` | `header`: six signs are the first level, two the fifth |
| `**bold**`, `//italic//`, `__underline__`, `''monospace''` | `<b>`, `<i>`, `<u>`, `<code>` |
| `[[page]]`, `[[namespace:page#section\|text]]`, `[[https://…\|text]]`, `[[name@example.com]]` | a link; a link to another wiki, `[[wp>Name]]`, keeps its text alone |
| `<name@example.com>`, an address in the text | a link |
| `((text))` | a footnote |
| `%%text%%` | the text, as written |
| `\\` before a space or the end of the line | `<br>` |
| `{{a.png}}`, `{{ a.png}}`, `{{a.png }}`, `{{ a.png }}`, `{{a.png?200x100\|Caption}}` | `image`, the text its caption, on the right, on the left or centered by its spaces, sized; a file that is no image, a link to it |
| `  * item`, `  - item`, two spaces or a tab more for each level | `list` |
| `  = Term : Definition`, `  : Definition` | `definitionList` |
| lines indented by two spaces that are no items | `code` |
| `^ heading ^`, `\| cell \|`, `:::`, `\|\|` | `table`, a first row of headings only its headings, a heading elsewhere a header cell; `:::` widens the cell above by a row, an empty cell the cell before by a column; two spaces before the text align it right, after it left, on both sides center |
| `>`, `>>` | `quote`, each line on its own |
| `----` | `delimiter` |
| `~~TOC~~`, `~~PAGEBREAK:frontCover~~`, `~~COLUMNBREAK~~`, `~~SPACER:3~~`, `~~PAGENUMBER:12~~`, `~~DECORATION:12 color=#rrggbb~~` alone on a line | the blocks of the tags of the same name |
| `~~NOTOC~~`, `~~NOCACHE~~` | nothing |

## Creole

| Syntax | UJO |
|---|---|
| `= … =` to `====== … ======` | `header`, its level the signs that open it; those that close it are optional |
| `**bold**`, `//italic//`; `##monospace##`, `^^superscript^^`, `,,subscript,,`, `__underline__` | `<b>`, `<i>`, `<code>`, `<sup>`, `<sub>`, `<u>`; `//` inside an address is the address |
| `[[page]]`, `[[page\|text]]`, `[[https://…\|text]]`, `[[page\|{{image.png}}]]` | a link |
| `{{image.png\|text}}` | `image`, the text its alternative text: Creole has no caption |
| `{{{text}}}` | `<code>`, the text as written; the braces that close it are the last three of their run |
| `{{{` and `}}}` on lines of their own | `code`; a line ` }}}`, indented, is text |
| `\\` | `<br>` |
| `~` before a character | the character, as text |
| `*`, `#`, `**`, `#*` | `list`; a list starts with a single sign, so that a paragraph may start with bold |
| `\|= heading \|`, `\| cell \|` | `table` |
| `; Term : Definition`, `: Definition`; `:`, `::` | `definitionList`; `paragraph`, its `indent` tune |
| `>` | `quote` |
| `----` | `delimiter` |
| `<<<…>>>` | nothing: a placeholder for the wiki that wrote it |

## Text that is not well formed

Any text is a document, and the readers never throw. A mark of emphasis closes the element of its kind still open,
and those opened inside it and left open are text, so that the HTML is always well nested; a mark never closed is
text. A link, an image or a template is read within 4 KB and on one line; one not closed there is text. A container
never closed holds the rest of the text.

## Linear time

Each line is read once, the tags of containers set on lines of their own by one pass first. A delimiter missing from
a position is missing from every position after it, which the inline reader remembers; content is read eight levels
deep at most, deeper as text; a link, an image or a template is searched within 4 KB. The open containers are counted
by name, and a row keeps sixty-four cells, the text of the others joined to the last. Sources of 100 KB written to be
slow are each read in a fraction of a second.
