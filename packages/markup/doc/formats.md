# Formats

Every format reaches the same features. What a language has natively is written its own way; what it lacks is
written with an extension of that language, in its own style. Each feature ends up as the same UJO block or inline
element, whatever format it was written in, so a text can be converted from one format to another through UJO.

## The formats

| Format | `MarkupFormat` | Read into UJO by | Compiled |
|---|---|---|---|
| Markdown | `markdown` | `Reader\Markdown\MarkdownReader` | UJO, rendered by `phil/ujo` |
| UJO | `ujo` | `Reader\UjoReader` | UJO, rendered by `phil/ujo` |
| BBCode | `bbcode` | `Reader\BBCode\BBCodeReader` | UJO, rendered by `phil/ujo` |
| MediaWiki | `mediawiki` | `Reader\MediaWiki\MediaWikiReader` | UJO, rendered by `phil/ujo` |
| DokuWiki | `dokuwiki` | `Reader\DokuWiki\DokuWikiReader` | UJO, rendered by `phil/ujo` |
| Creole | `creole` | `Reader\Creole\CreoleReader` | UJO, rendered by `phil/ujo` |
| HTML | `html` | `Reader\Html\HtmlReader` | as written, for the purifier |
| Plain text | `text` | `Reader\Text\TextReader` | `Adapter\PlainText`, straight to HTML |

HTML is compiled as written, so that what an editor wrote reaches the purifier untouched, and plain text straight to
its paragraphs; their readers convert them into UJO when a text is to become blocks. An add-on brings a format of its
own under another name: see [Extension points](extension-points.md).

## Correspondence

A cell names the syntax; "—" marks a feature the format does not have. The UJO column is what every syntax of the row
becomes. The Markdown column describes PHIL's Markdown, `{{ }}` blocks and pages included. The three
wikis share their extension tags: MediaWiki and DokuWiki write them `<note>`, Creole `<<note>>`, closed by
`</note>` and `<</note>>`; a cell of the DokuWiki or Creole column that says "the tag" means the tag of the MediaWiki
column, in its own delimiters. The HTML column names the elements, and the classes of the HTML `phil/ujo` renders,
that the HTML reader reads back.

### Text and inline elements

| Feature | UJO | Markdown | BBCode | MediaWiki | DokuWiki | Creole | HTML |
|---|---|---|---|---|---|---|---|
| Bold, italic | `<b>`, `<i>` | `**b**`, `*i*`, `__b__`, `_i_` | `[b]`, `[i]` | `'''b'''`, `''i''`, `'''''both'''''` | `**b**`, `//i//` | `**b**`, `//i//` | `<b>`, `<strong>`, `<i>`, `<em>`, `<cite>`, a bold or italic style |
| Strikethrough | `<s>` | `~s~`, `~~s~~` | `[s]` | `<s>`, `<del>`, `<strike>`, `{{s\|…}}` | `<del>` | `<<s>>…<</s>>` | `<s>`, `<del>`, `<strike>`, `text-decoration: line-through` |
| Underline | `<u>` | — | `[u]` | `<u>`, `<ins>`, `{{u\|…}}` | `__u__` | `__u__` | `<u>`, `<ins>`, `text-decoration: underline` |
| Highlight | `<mark>` | `==text==` | `[mark]`, `[highlight]` | `<mark>`, `{{Mark\|…}}`, `{{Highlight\|…}}` | `<mark>` | `<<mark>>…<</mark>>` | `<mark>` |
| Exponent, subscript | `<sup>`, `<sub>` | — | `[sup]`, `[sub]` | `<sup>`, `<sub>` | `<sup>`, `<sub>` | `^^sup^^`, `,,sub,,` | `<sup>`, `<sub>` |
| Inline code | `<code>` | `` `code` `` | `[icode]` | `<code>`, `<tt>`, `{{code\|…}}` | `''code''` | `##code##`, `{{{text}}}` | `<code>`, `<kbd>`, `<samp>`, `<tt>` |
| Text kept as written | its text | `\` before a character | `[code]`, `[icode]` | `<nowiki>` | `<nowiki>`, `%%text%%` | `~` before a character, `{{{text}}}` | — |
| Line break | `<br>` | two spaces or `\` at the end of a line | a new line | `<br>` | `\\` before a space or the end of the line | `\\` | `<br>` |
| Link | `<a href>` | `[text](address)`, `[text][label]`, `[label]` | `[url]`, `[url=address]`, `[email]` | `[[Page\|text]]`, `[[Page#Section]]`, `[https://… text]` | `[[page\|text]]`, `[[namespace:page#section]]`, `[[https://…\|text]]` | `[[page\|text]]`, `[[https://…\|text]]` | `<a href>` |
| Autolink | `<a href>` | `<https://…>`, `<name@example.com>`, `https://…`, `www.…` | an address in the text | an address in the text | an address in the text, `<name@example.com>` | an address in the text | — |
| Non-breaking space | U+00A0 | — | `[nbsp]` | `&nbsp;`, `{{nbsp}}`, `<nbsp>` | the tag | the tag | `&nbsp;` |
| Icon | `<i data-icon="set:name">` | — | `[icon=fas_fa-dragon]` | `{{Icon\|fas_fa-dragon}}`, `<icon fas_fa-dragon>` | the tag | the tag | `<i data-icon>` |
| Horizontal space | `<span data-hspace>` | — | `[hspace=100px]` | `{{Hspace\|100px}}`, `<hspace 100px>` | the tag | the tag | `<span data-hspace>` |
| Text color | `<span class="text-color" data-color>` | — | `[color=red]` | `{{Color\|red\|text}}`, `<color red>` | the tag | the tag | `<span data-color>` |
| Spoiler | `<span class="spoiler">` | — | `[spoiler]` | `{{Spoiler\|text}}`, `<spoiler>` | the tag | the tag | `<span class="spoiler">` |
| Variable | its value | — | `[var=name]value[/var]`, `[var]name[/var]` | `{{#vardefine:name\|value}}`, `{{#var:name}}`, `<var name="value">`, `<var name>` | `<var name="value">`, `<var name>` | `<<var name="value">>`, `<<var name>>` | — |
| Footnote | `<sup data-footnote>` and `footnote` | `[^label]`, `[^label]: text` | `[footnote]text[/footnote]` | `<ref>text</ref>`, `<ref name="a">`, `<ref name="a"/>`, `{{Footnote\|text}}` | `((text))` | `<<footnote>>text<</footnote>>` | `<sup data-footnote>` and its `<aside class="ujo-footnote">` |
| Raw HTML | inline elements, sanitized | kept at `all`, escaped below | `[html]` as a block | `<html>`, kept at `all`, escaped below | the tag | the tag | — |

### Blocks

| Feature | UJO | Markdown | BBCode | MediaWiki | DokuWiki | Creole | HTML |
|---|---|---|---|---|---|---|---|
| Paragraph | `paragraph` | lines between blank lines | text, a blank line between two | lines between blank lines | same | same | `<p>`, text between blocks |
| Heading | `header`, its anchor tune | `#` to `######`, or underlined by `===` or `---` | `[h1]`…`[h6]` | `= … =` to `====== … ======` | `====== … ======` is the first level, `== … ==` the fifth | `= … =` to `====== … ======`, the closing signs optional | `<h1>`…`<h6>` |
| Heading anchor | `anchor` tune | generated, or `{#id}` | generated, or `id=` | generated, or `id` on a `<div>` around it | generated, or `id` on a tag around it | generated, or `id` on a tag around it | generated, or `id` |
| List, ordered list | `list` | `-`, `+`, `*`; `1.`, `1)` | `[list]`, `[*]`; `[list=1]`, `[list=a]`, `[list=i]` | `*`, `#`, a sign more for each level | `  *`, `  -`, two spaces more for each level | `*`, `#`, a sign more for each level | `<ul>`, `<ol>`, `start`, `type` |
| Task list | `list`, style `checklist` | `- [ ]`, `- [x]` on every item | `[list=check]`, `[*=x]` | `[ ]`, `[x]` after the sign of every item | same | same | a checkbox, or `data-checked`, in every item |
| Indented paragraph | `paragraph`, its `indent` tune | — | — | `:`, `::`, a sign for each level | — | `:`, `::` | — |
| Quote | `quote` | `>` | `[quote]` | `<blockquote>` | `>` | `>` | `<blockquote>` |
| Quote with attribution | `quote`, its caption | — | `[quote=author]` | `<quote author="…">`, `<blockquote author="…">` | the tag | the tag | `<figure>`, its `<blockquote>` and `<figcaption>` |
| Code block | `code`, its language | fenced by ```` ``` ```` or `~~~`, or indented | `[code]`, `[code=language]` | lines that start with a space, `<pre>`, `<syntaxhighlight lang="…">`, `<source lang="…">`, `<code php>` | lines indented by two spaces, `<code php>`, `<file php>` | `{{{` and `}}}` on lines of their own, `<<code php>>` | `<pre>`, its `<code class="language-…">` |
| Rule | `delimiter` | `***`, `---`, `___` | `[hr]` | `----` | `----` | `----` | `<hr>` |
| Table | `table` | the tables of GitHub | `[table]`, `[tr]`, `[th]`, `[td]` | `{\|`, `!`, `\|`, `\|-`, `\|}` | `^` headings, `\|` cells | `\|=` headings, `\|` cells | `<table>`, `<th>`, `<td>` |
| Image | `image`, its `alt`, its caption, its layout tune | `![alt](address "caption")`, alone or in a paragraph | `[img]`, `[img=alt width=… wrap=left]` | `[[File:a.png\|left\|200px\|alt=…\|Caption]]` | `{{ a.png?200\|Caption}}`, placed by its spaces | `{{a.png\|alt}}` | `<img alt title>`, `<figure>` and its `<figcaption>` |
| Definition list | `definitionList` | `Term` then `: definition` | `[dl]`, `[dt]`, `[dd]` | `; Term : Definition`, `: Definition` | `  = Term : Definition`, `  : Definition` | `; Term : Definition`, `: Definition` | `<dl>`, `<dt>`, `<dd>` |
| Table of contents | `toc` | `[TOC]` | `[toc]`, `[toc=Title depth=4]` | `__TOC__`, `{{TOC\|title=…}}`, `<toc title="…" depth=4>` | `~~TOC~~`, the tag | the tag | `<nav class="ujo-toc">` |
| Front matter | `Reading::$metadata` | YAML between `---` lines | `[metadata]` YAML, `[style]` CSS | `<metadata>` YAML, `<style>` CSS | the tags | the tags | `<title>`, `<meta name="…" content="…">` |
| Raw HTML block | `raw` | kept at `all`, escaped below | `[html]`, kept at `all`, escaped below | `<html>` on lines of its own, kept at `all`, escaped below | the tag | the tag | — |
| Attributes | tunes, or a `container` | `{.class #id}` after a heading or on the line under a block | `id=`, `class=`, `align=`, `width=`, `height=`, `wrap=` on a block tag | `id`, `class`, `align`, `width`, `height`, `wrap` on an extension tag; `<div id="…">` around a block | the same, on the tag | the same, on the tag | `id`, `align`, `text-align`, the classes `ujo-align-…` and `ujo-wrap-…` |
| Boxed note, read-aloud text | `note`, `descriptive` | `::: note Title`, `{{note …}}` | `[note=Title]`, `[descriptive]` | `<note title="…">`, `<descriptive>` | the tags | the tags | `<aside class="ujo-note">`, `ujo-descriptive`, their `ujo-box__title` |
| Warning, alert | `warning`, `alert` | `::: warning`, `::: info` and the other alert types | `[warning=Title]`, `[alert=info]` | `<warning title="…">`, `<alert info>` | the tags, and `<note warning>`, `<note important>`, `<note tip>` of the note plugin | the tags | `<aside class="ujo-warning">`, `<div class="ujo-alert ujo-alert--info">` |
| Collapsible block | `toggle` | — | `[spoiler=Title]` | `<spoiler title="…">`, `<toggle>` holding blocks | the tags, and `<hidden title="…">` | the tags | `<details>`, its `<summary>` |
| A class of the look | `container` | `::: {.class}`, `{{class …}}` | `[block=class]`, `class=` | `<div class="…">`, `<block class>` | `<WRAP class>`, the tags | the tags | `<div class="ujo-container …">` |

### Pages and game books

| Feature | UJO | Markdown | BBCode | MediaWiki | DokuWiki | Creole | HTML |
|---|---|---|---|---|---|---|---|
| Page break, column break | `pageBreak`, `columnBreak` | `\page`, `\column` | `[pagebreak]`, `[columnbreak]` | `{{Page break}}`, `{{Column break}}`, `<pagebreak>`, `<columnbreak>` | `~~PAGEBREAK~~`, `~~COLUMNBREAK~~`, the tags | the tags | `ujo-page-break`, `ujo-column-break` |
| Style of the next page, cover | `pageStyle` | `\page {class}` | `[pagebreak=frontCover]`, `[block=frontCover]` | `{{Page break\|frontCover}}`, `<pagebreak frontCover>`, `<block frontCover>` | `~~PAGEBREAK:frontCover~~`, the tags | the tags | `ujo-page-style`, its `data-variant` |
| Across the columns | `wide` | `::: wide`, `{{wide …}}` | `[wide]`, the flag `wide` | `<wide>`, the flag `wide` | the same | the same | `ujo-wide` |
| Columns side by side | `columns` | — | `[columns][column]…[column]…[/columns]` | `<columns><column>…<column>…</columns>` | the same, `<newcolumn>` too | the same | `ujo-columns`, its `ujo-column` |
| Flowing columns | `columnFlow` | — | `[columns=3]` | `<columns 3>` | the same | the same | `ujo-column-flow--3` |
| Blank lines | `spacer` | — | `[spacer=3]` | `{{Spacer\|3}}`, `<spacer 3>` | `~~SPACER:3~~`, the tag | the tag | `ujo-spacer`, its `data-lines` |
| Alignment | `alignment` tune | `{text-align:center}` under a block | `[center]`, `[left]`, `[right]`, `[justify]`, `[align=…]`, `align=` | `<center>`, `<left>`, `<right>`, `<justify>`, `<align right>`, `align=` | the same | the same | `<center>`, `align`, `text-align` |
| Page number | `pageNumber` | `{{pageNumber 1}}` | `[pagenumber]`, `[pagenumber=12]`, `[pagenumber=skip]`, `[pagenumber=reset]` | `{{Page number\|12}}`, `<pagenumber 12>`, `<pagenumber skip>`, `<pagenumber reset>` | `~~PAGENUMBER:12~~`, the tags | the tags | `ujo-page-number`, its `data-mode` |
| Running footer | `runningFooter` | `{{footnote …}}` | `[footer]` | `<footer>` | the tag | the tag | `ujo-running-footer` |
| Watermark, banner | `watermark`, `banner` | `{{watermark …}}`, `{{banner …}}` | `[watermark]`, `[banner]` | `<watermark>`, `<banner>` | the tags | the tags | `ujo-watermark`, `ujo-banner` |
| Decoration | `decoration` | `{{watercolor1}}` to `{{watercolor99}}` | `[decoration=12 color=#rrggbb]` | `{{Decoration\|12\|color=#rrggbb}}`, `<decoration 12 color=#rrggbb kind=ornament>` | `~~DECORATION:12 color=#rrggbb~~`, the tag | the tag | `ujo-decoration--stain-12` |
| Stat block | `statblock` | `{{monster …}}`, `{{statblock,spell …}}` | `[statblock]`, `[monster]`, `frame`, `wide`, `name=`, `subtitle=` | `<statblock name="…" subtitle="…" frame wide>`, `<monster>` | the tags | the tags | its blocks |
| Class table | `table`, `variant` tune | `{{classTable …}}` | `[classtable frame decoration]` | `<classtable frame decoration>` | the tag | the tag | its table, without the variant |
| Art credit | `credit` | `{{artist …}}` | `[credit=Title]`, `[artist]` | `<credit title="…">`, `<artist>` | the tags | the tags | `ujo-credit`, its `ujo-credit__title` |

## Planned

The features below are part of the Markdown of PHIL and of every other format, and are not read yet: default
attributes per block type, links to other sites (`rel`, `target`, the site's own domains), mentions `@name` and
`#ticket`, an address alone on its line as an embed, smart punctuation through `phil/typography`, heading levels made
consecutive, and an inline-only mode. [BBCode](bbcode.md), [The wikis](wikis.md) and [HTML](html.md) detail their
columns.
