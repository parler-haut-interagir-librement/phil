# Markdown

`Reader\Markdown\MarkdownReader` reads Markdown into a UJO document. It never throws: a construct that is not well
formed stays text. The document is normalized by `phil/ujo` before it is returned, so its inline HTML is in the
canonical form of the inline tools — `<b>`, `<i>`, `<s class="cdx-strikethrough">`, `<code class="inline-code">`.

```php
$reader = new MarkdownReader();                  // raw HTML escaped
$reader = new MarkdownReader(rawHtml: true);     // raw HTML kept, for a purifier at the level `all`
$reading = $reader->read($source);               // Reading: document and metadata
```

A second argument replaces the `Normalizer`; the default one registers every type of `phil/ujo` and `raw`. A third
gives a `MarkdownDialect`, the syntax a dialect adds or reads differently: see
[Extension points](extension-points.md#dialects-of-markdown).

## Blocks

| Markdown | UJO |
|---|---|
| A paragraph | `paragraph`; split around the images it holds, each an `image` |
| `# Heading`, `Heading` over `===` or `---` | `header`, level 1 to 6, with an `anchor` tune |
| `- item`, `+ item`, `* item`, `1. item`, `1) item` | `list`, `unordered` or `ordered` with its `start`; nested lists are nested items |
| `- [ ] task`, `- [x] done`, on every item | `list`, style `checklist`, each item `checked` |
| `> quote` | `quote`, its blocks folded into its text |
| ```` ``` ```` or `~~~` fence, four spaces of indentation | `code`, the first word of the fence its `language` |
| `***`, `---`, `___` | `delimiter` |
| A table of GitHub | `table`, `withHeadings`; the alignment of columns is not kept |
| `Term` then `: Definition` | `definitionList`; consecutive lists are one |
| `[^label]: text` | a `footnote` at the end of the document, numbered by first reference |
| `[label]: address "title"` | a reference for links and images, its title the caption of an image; no block |
| `[TOC]` alone on its line | `toc` |
| `::: note Title`, `::: warning`, `::: info`, `::: wide`, `::: {.class #id}` … `:::` | `note`, `warning`, `alert`, `wide`, `container` (see below) |
| `{{note …}}` and the other `{{ }}` blocks, a span alone on its line | see [Blocks in braces](#blocks-in-braces) |
| `\page`, `\pagebreak`, `\column`, `\columnbreak` | `pageBreak`, `columnBreak`; see [Pages and columns](#pages-and-columns) |
| HTML block | `raw`, or a `paragraph` of escaped text |
| Front matter, YAML between two `---` lines at the start | `Reading::$metadata` |

A container — a quote, a list item, a footnote — holds only inline content in UJO: its paragraphs are joined by line
breaks, a code block becomes inline code, a heading its text. Containers nest five deep; deeper, their markers are
text.

A heading's anchor is the `{#id}` written after it, or a slug of its text as GitHub makes it — lower case, accents of
Latin letters dropped, other runs of characters a hyphen —, unique in the document: a second heading of the same
text takes `-1`.

## Inline elements

| Markdown | UJO |
|---|---|
| `*i*`, `_i_`, `**b**`, `__b__` | `<i>`, `<b>`, paired as CommonMark pairs them |
| `~s~`, `~~s~~` | `<s>` |
| `==text==` | `<mark>` |
| `{漢字\|かんじ}` | a ruby: the base and its reading, each up to 100 characters without a brace or a bar |
| `` `code` `` | `<code>` |
| `[text](address "title")`, `[text][label]`, `[text][]`, `[label]` | `<a href>`; the title is left out |
| `<https://…>`, `<name@example.com>`, `https://…`, `www.…` | `<a href>`; `www.` addresses take `https://` |
| `![alt](address "caption")`, `![alt][label]` | an `image` block in a paragraph, its text its alternative text and its title — `"…"`, `'…'` or `(…)` — its caption, none without a title; `<a href>` elsewhere; in a link, its alternative text, or its caption without one |
| `[^label]` | `<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup>` |
| `\*`, `&copy;`, `&#65;` | the character |
| Two spaces or `\` at the end of a line | `<br>` |
| Inline HTML | kept for the sanitizer of `phil/ujo`, or escaped |

## Attributes

Braces after a heading, `# Title {#id .class}`, or alone on the line right under a block, `{.class #id}` — or, in the
grammar of the `{{ }}` blocks, `{class,#id,property:value}` — apply to that block:

| Attribute | UJO |
|---|---|
| `#id` | the `anchor` tune |
| `text-align:left`, `center`, `right`, `justify` | the `alignment` tune |
| `position`, `top`, `right`, `bottom`, `left`, `width`, `height`, `z-index`; the classes `wrapLeft` and `wrapRight` | the `layout` tune |
| any other class | a `container` around the block, for a look that declares the class |
| `key=value`, other properties | left out |

Braces right after an image, `![a](a.png){width:200px,wrapLeft}`, apply to the image.

## Raw HTML

Raw HTML is kept or escaped, as the reader is built. `DefaultCompiler` keeps it for the level `all`, where an editor
writes and the purifier keeps safe elements, and escapes it below, where it would only be removed: a member's
`<script>` reads as text instead of vanishing. A kept HTML block is a `raw` block, sanitized by `phil/ujo` then purified
at the level; an HTML comment alone is dropped. Inline HTML is sanitized with the inline tools of `phil/ujo`.

## `:::` blocks

`::: name Title` opens a block closed by a line of `:::`; blocks nest. The name is a class, mapped as the classes of the
`{{ }}` blocks are: `note` and `descriptive` take the title, or the heading they start with; `warning` makes a `warning`;
`info`, `success`, `danger`, `primary`, `secondary`, `light` and `dark` an `alert` of that type; `wide` a `wide` block;
any other class a `container`. `::: {.class #id}` takes attributes instead.

## Blocks in braces

A line `{{tags` opens a block, a line `}}` closes it, and blocks nest. The tags are separated by commas: a word is a
class, `#id` the identifier, `property:value` a style. The first class UJO knows decides the type:

| Class | UJO |
|---|---|
| `note`, `descriptive` | `note`, `descriptive`; the heading it starts with is its title |
| `warning`; `info`, `success`, `danger`, `primary`, `secondary`, `light`, `dark` | `warning`; an `alert` of that type |
| `monster` (`frame`, `wide`) | `statblock` (see below) |
| `statblock` with `spell` or `item` | `statblock` of that kind |
| `toc` (`wide`, `tocDepthH4` to `tocDepthH6`) | `toc`, its title the heading; the written list is dropped, the table being generated |
| `quote` with a line `{{attribution …}}` | `quote`, the attribution as its caption |
| `artist` | `credit`, the heading as its title |
| `classTable` (`frame`, `decoration`) | its tables with the `variant` tune |
| `spellList` | `columnFlow` of two columns |
| `frontCover`, `insideCover`, `partCover`, `backCover` | `pageStyle` with that variant, before the blocks |
| `wide` | `wide`; with another class, around that block |
| Any other | `container` with its classes, for a look that declares them |

The identifier becomes the `anchor` tune, and `text-align`, `position`, offsets and size the tunes of
[attributes](#attributes). Braces on the line under any block, `{color:purple,text-align:center}`, apply the same way;
a CSS property UJO has no place for is left out.

A span alone on its line, `{{tags content}}`, may be a block of the page; any other stays text:

| Span | UJO |
|---|---|
| `{{pageNumber 1}}` | `pageNumber`, `fixed`, value `1` |
| `{{pageNumber,auto}}`, `{{pageNumber}}` | `pageNumber`, `auto` |
| `{{skipCounting}}`, `{{resetCounting}}` | `pageNumber`, `skip` or `reset` |
| `{{footnote PART 1 \| TITLE}}` | `runningFooter`: the label at the foot of the pages, not a footnote |
| `{{watermark text}}`, `{{banner text}}` | `watermark`, `banner` |
| `{{frontCover}}` and the other covers | `pageStyle` |
| `{{watercolor8,background-color:#BBAD82}}` | `decoration`, `stain`, variant 8, its colour |

Spans nest 8 deep within their line.

## Pages and columns

| Markdown | UJO |
|---|---|
| `\page`, `\pagebreak` | `pageBreak` |
| `\page {frontCover,class}` | `pageBreak`, then `pageStyle` with the cover as `variant` and the classes |
| `\column`, `\columnbreak` | `columnBreak` |

## Stat blocks

```markdown
{{monster,frame
## Gibbering Duck
*Small beast, chaotic gossipy*
___
**Armor Class**
: 15 (chain mail, shield)

___
|  STR  |  DEX  |
|:-----:|:-----:|
|10 (+0)|14 (+2)|
___
***Pack Tactics.*** These guys work together.
### Actions
***Bite.*** *Melee Weapon Attack:* +4 to hit.
}}
```

The first heading is the `name`, an italic line right after it the `subtitle`. A rule `___` or a heading starts a
section, the heading its `title`. Definitions are the section's `properties`, the label without its colon; a table of
two rows whose second holds scores — `10 (+0)`, `14` — its `abilities`, each a label, a score and a modifier; any other
block stays in the section's `blocks`. `frame` and `wide` set the flags of the same name.
