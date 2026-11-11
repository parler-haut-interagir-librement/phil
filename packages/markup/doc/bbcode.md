# BBCode

`Reader\BBCode\BBCodeReader` reads BBCode into a UJO document, which `phil/ujo` renders as HTML. It reads the tags of
forums, and extensions written in the same style that give BBCode every feature of the Markdown reader: footnotes,
definition lists, a table of contents, attributes, boxes, columns, page breaks, stat blocks and the other blocks of
printed pages. [Formats](formats.md) puts each tag beside the syntax of the other formats.

```php
$reading = new BBCodeReader()->read("[h2]Rules[/h2]\n[quote=Ada]Roll [b]two[/b] dice.[/quote]");

$reading->document->blocks[0];   // a `header` block, level 2, its anchor `rules`
$reading->document->blocks[1];   // a `quote` block, its caption `Ada`
```

`new BBCodeReader(true)` keeps raw HTML; `DefaultCompiler` asks for it at the level `all` only. A second argument
replaces the `phil/ujo` normalizer the document is checked by.

## Tags

A tag is written `[name]`, `[name=option]`, `[name="option" key=value flag]` or `[name key=value flag]`, its name in
any case. An option without quotes runs up to the first ` key=`. An attribute value holding spaces is quoted.

### Text

| Tag | UJO |
|---|---|
| `[b]`, `[i]`, `[u]`, `[s]`, `[sup]`, `[sub]` | `<b>`, `<i>`, `<u>`, `<s>`, `<sup>`, `<sub>` |
| `[mark]`, `[highlight]` | `<mark>` |
| `[icode]code[/icode]` | `<code>`, its content as written |
| `[url]https://…[/url]`, `[url=https://…]text[/url]` | `<a href>`; `www.…` takes `https://`; a scheme `phil/ujo` allows — `http`, `https`, `mailto`, `tel`, `sms`, `geo`, the messengers — or a path beginning with `/`, otherwise the text alone |
| `[email]a@b.ca[/email]`, `[email=a@b.ca]text[/email]` | a `mailto:` link to a valid address, otherwise the text alone |
| an address in the text, `https://…`, `www.…` | `<a href>`, except inside a link |
| `[color=red]`, `[color=#ff0000]` | `<span class="text-color" data-color="red">`, a hexadecimal color without its `#`, for the look to declare; any other value keeps the text alone |
| `[size=…]`, `[font=…]` | the text alone: UJO has no size or font |
| `[spoiler]text[/spoiler]` | `<span class="spoiler">` |
| `[footnote]text[/footnote]` | `<sup data-footnote>` where it is written, and a `footnote` block at the end, numbered in the order of the references |
| `[nbsp]` | U+00A0 |
| `[ruby=かんじ]漢字[/ruby]` | a ruby, its option the reading; its content alone without one |
| `[icon=fas_fa-dragon]`, `[icon=set:name]` | `<i data-icon="set:name">` |
| `[hspace=10px]` | `<span data-hspace>`, a horizontal space |
| `[var=name]value[/var]`, `[var]name[/var]` | defines a variable anywhere in the source; writes its value, or the tag as written when none is defined |

Colors, spoilers and icons reach the page as far as the level purifies them: `basic` and `forum` keep their text.

### Blocks

| Tag | UJO |
|---|---|
| text, a blank line between paragraphs | `paragraph`; another new line is a line break |
| `[h1]`…`[h6]` | `header`, its anchor generated, or `id` |
| `[quote]`, `[quote=Ada]`, `[quote="Ada Lovelace"]` | `quote`, the author as its caption |
| `[code]`, `[code=php]` | `code`, its language; the content is kept as written |
| `[hr]` | `delimiter` |
| `[list]`, `[*]` | `list`, unordered; the blocks of an item fold into its content, a list in it becomes its nested items |
| `[list=1]`, `[list=3]`, `[list=a]`, `[list=A]`, `[list=i]`, `[list=I]` | `list`, ordered, from the number given, or counted by letters or roman numerals |
| `[list=check]`, `[*=x]` | `list`, style `checklist`, the items of `[*=x]` checked |
| `[table]`, `[tr]`, `[td]`, `[th]` | `table`, its rows made as long as the longest, 64 cells at most; a first row of `[th]` only is its headings, a `[th]` elsewhere a header cell; `[td colspan=2 rowspan=2 align=center]` kept in the rich form |
| `[dl]`, `[dt]`, `[dd]` | `definitionList` |
| `[img]https://…[/img]`, `[img=text]`, `[img alt="…"]` | `image`, the text as its alternative text, with no caption: BBCode has none; a paragraph is split around it; an address that is not that of an image keeps the tag as written; inside a link, its text |
| `[center]`, `[left]`, `[right]`, `[justify]`, `[align=center]` | the `alignment` tune on each block inside |
| `[spoiler=Title]`, or a `[spoiler]` holding blocks | `toggle`, closed |
| `[toc]`, `[toc=Contents depth=4 wide]` | `toc`, its title, its depth, across the columns |
| `[metadata]YAML[/metadata]`, `[style]CSS[/style]` | `Reading::$metadata`, the style under `style` |
| `[html]…[/html]` | `raw` when raw HTML is kept, its text escaped otherwise |

### Attributes

Every block tag takes `id` — the `anchor` tune —, `align` — the `alignment` tune —, `width`, `height` and
`wrap=left` or `wrap=right` — the `layout` tune —, and `class`, a list of the look's classes that wraps the block in
a `container` where nesting allows it, as the attributes of the Markdown reader do:

```text
[h2 id=intro class="lead wide" align=center]Introduction[/h2]
[img=Map width=300px wrap=right]https://example.com/map.png[/img]
```

### Pages and game books

| Tag | UJO |
|---|---|
| `[pagebreak]`, `[pagebreak=frontCover]`, `[pagebreak=myPage]` | `pageBreak`, and the `pageStyle` of the page it opens: its cover, its classes |
| `[columnbreak]` | `columnBreak` |
| `[columns][column]…[column]…[/columns]` | `columns`, six at most, the blocks of the others joined to the sixth |
| `[columns=3]…[/columns]` | `columnFlow`, blocks flowing in 2 to 6 columns |
| `[wide]`, or the flag `wide` on a box | `wide`, across the columns |
| `[note=Title]`, `[descriptive]` | `note`, `descriptive`; without a title, a first heading inside is the title |
| `[warning=Title]` | `warning` |
| `[alert=danger]` | `alert`, of the kinds `info` — the default —, `success`, `danger`, `primary`, `secondary`, `light`, `dark` |
| `[statblock]`, `[statblock=spell]`, `[statblock=item]`, `[monster]`, flags `frame` and `wide` | `statblock`: the first heading its name, `[hr]` or a heading a new section, `[dl]` its properties, a table of two rows of scores — `10 (+0)` — its abilities; `name` and `subtitle` may be given as attributes |
| `[credit=Title]`, `[artist]` | `credit` |
| `[classtable frame decoration]` | its tables marked with the `classTable` variant |
| `[block=class,class]` | any kind of the `{{ }}` blocks of [Markdown](markdown.md#blocks-in-braces) — `note`, `monster`, `frontCover`, `spellList`… — or a `container` of the look's classes |
| `[spacer]`, `[spacer=3]` | `spacer`, 1 to 50 lines |
| `[pagenumber]`, `[pagenumber=12]`, `[pagenumber=skip]`, `[pagenumber=reset]` | `pageNumber`: counted, written, left out of the count, the count restarted |
| `[footer]text[/footer]` | `runningFooter` |
| `[watermark]`, `[banner]` | `watermark`, `banner` |
| `[decoration=12 color=#aabbcc kind=ornament]` | `decoration` |

## A source that is not well formed

Any text is a document: the reader never throws.

- A tag the reader does not know, or written over two lines, stays as written.
- A tag left open is closed at the end of the source. A closing tag closes the nearest open tag of its name and the
  tags opened inside it; a closing tag nothing opened stays as written.
- An item, a row, a cell, a column, a term or a definition closes the one of its kind left open in the same parent:
  `[list][*]a[*]b[/list]`. Outside its parent, it stays as written.
- Text and tags between the items of a list, the rows of a table, the cells of a row or the columns of `[columns]`
  make an item, a row, a cell or a column of their own; blank text between them is left out.
- An inline tag holding a block is left out, its content kept; a `[spoiler]` holding blocks becomes a `toggle`.
- A heading, a quote, a list item, a cell, a term, a definition and a footnote fold the blocks they hold into their
  inline content; a block with no inline content — a box, columns — is left out there, its paragraphs kept.
- Tags nested deeper than 32 stay as written. Boxes and other containers of blocks nest as deep as UJO allows; one
  deeper is left out, its blocks kept.
- The content of `[code]`, `[icode]`, `[img]`, `[html]`, `[var]`, `[metadata]` and `[style]` runs up to its closing
  tag, in any case, or to the end of the source.

## Linear time

A member who writes a post can write a hostile source. The reader takes time in proportion to the length of the
source, whatever its shape:

- the cursor of [the analyser](analyser.md) reads each byte once; a bracket that opens no tag is text, and the scan
  for its `]` stops at the next `[`;
- the reader keeps, for each tag name, the positions its tags are open at: a closing tag finds its opening tag, or
  learns there is none, without searching the stack, and each tag is opened and closed once;
- the search for the closing tag of `[code]` and the others consumes what it passes;
- headings of the same text, footnotes and images take constant time each.

`BBCodeReaderTest` reads sources of 100 KB written to be slow, and `BBCodeBench` reads them at 25, 50 and 100 KB:
the time doubles with the size.
