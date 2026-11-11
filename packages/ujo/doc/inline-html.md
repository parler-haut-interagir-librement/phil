# Inline HTML

UJO (Universal JSON Output) keeps inline formatting inside its texts as HTML: `Hello <b>world</b>`. A block editor's own
sanitizer runs in the writer's browser, which a request can skip; phil/ujo sanitizes every HTML value again on the
server, field by field, when a document is normalized and again when it is rendered.

## The sanitizer

`InlineSanitizer::sanitize($html, $profile)` reads the fragment token by token — text, start tags with their
attributes, end tags, comments — and writes back only what the profile allows, in one canonical form:

- text is decoded, then escaped again: `&eacute;` becomes `é`, `<` becomes `&lt;`;
- an element no tool of the profile writes is unwrapped: its text stays;
- `script`, `style`, `iframe`, `object`, `embed`, `template`, `noscript`, `textarea`, `select`, `svg`, `math`, `title`,
  `head`, `frameset`, `frame`, `applet`, `audio`, `video`, `canvas`, `form` and `annotation-xml` are dropped with
  their content, unless the profile reads them: `rich` and `mathml` read `math`;
- a tool kept only `within` other elements is unwrapped elsewhere: a ruby's `<rt>` outside a `<ruby>`, an element of
  MathML outside a `<math>`;
- comments are dropped;
- attributes a tool does not name are dropped, those it names are cleaned, and an element missing a required one is
  unwrapped;
- elements are closed in order: a stray end tag is ignored, an element left open is closed, and a link is never
  nested in a link.

`InlineSanitizer::toText($html)` gives the decoded text without elements, a `<br>` as a new line.

## Profiles

| Profile | Keeps |
|---|---|
| `rich` | the inline tools below — the profile of every HTML value of the types |
| `plain` | nothing but text |
| `mathml` | one formula of MathML Core: `<math>` and the elements it holds, the field of a `math` block's `mathml` |
| `raw` | the inline tools, and the elements of a page's structure: paragraphs, headings, lists, quotes, code, tables with `colspan` and `rowspan`, figures, images with `src`, `alt`, `width` and `height`, definition lists, sections, details — no class, style or event anywhere |

## Inline tools of `rich`

| Tool | Read from | Written as |
|---|---|---|
| bold | `b`, `strong` | `<b>` |
| icon | `i[data-icon]`, a reference `set:name` | `<i data-icon="fa:dragon">` |
| italic | `i`, `em` | `<i>` |
| underline | `u` | `<u class="cdx-underline">` |
| strikethrough | `s`, `strike`, `del` | `<s class="cdx-strikethrough">` |
| marker | `mark` | `<mark class="cdx-marker">` |
| inline code | `code`, `span.inline-code` | `<code class="inline-code">` |
| mention | `a[data-mention]`, a reference `kind:id`, optional `href` | `<a href data-mention="user:42">` |
| wiki link | `a[data-ref]`, a reference, optional `href` | `<a href data-ref="page:7">` |
| link | `a` with an `href` that is `http`, `https`, `mailto`, `tel`, `sms`, `geo`, `xmpp`, `matrix`, `viber` or relative — a `tel` or `sms` a number, a `geo` coordinates —; `target="_blank"`; `rel` among `nofollow`, `noopener`, `noreferrer`, `ugc`, `sponsored`, `external` | `<a href target rel>` |
| footnote reference | `sup[data-footnote]` | `<sup data-footnote="fn1">` |
| superscript, subscript | `sup`, `sub` | `<sup>`, `<sub>` |
| line break | `br` | `<br>` |
| text colour | `span.text-color` with `data-color`, a token of the look | `<span class="text-color" data-color="accent">` |
| tooltip | `span.cdx-tooltip` with `data-tooltip` | `<span class="cdx-tooltip" data-tooltip>` |
| annotation | `span.cdx-annotation` with `data-title`, `data-text` | `<span class="cdx-annotation" data-title data-text>` |
| spoiler | `span.spoiler` | `<span class="spoiler">` |
| horizontal space | `span[data-hspace]`, a CSS length | `<span data-hspace="100px">` |
| abbreviation | `abbr` with its `title` | `<abbr title="Game master">` |
| language | `span[lang]`, `span[dir]`, `bdi`, `bdo`; `lang` a BCP 47 tag, `dir` `ltr`, `rtl` or `auto`; one of the two at least | `<span lang="en" dir="ltr">` |
| ruby | `ruby`, its readings `rt` and their fallback `rp` inside it | `<ruby>漢字<rp>(</rp><rt>かんじ</rt><rp>)</rp></ruby>` |
| inline math | `math` with `display`, and inside it the elements of MathML Core — `mrow`, `mi`, `mn`, `mo`, `mtext`, `ms`, `mfrac`, `msqrt`, `mroot`, `msub`, `msup`, `msubsup`, `munder`, `mover`, `munderover`, `mmultiscripts`, `mprescripts`, `none`, `mtable`, `mtr`, `mtd`, `mspace`, `mpadded`, `mphantom`, `mstyle`, `merror`, `semantics`, `annotation` — with their own attributes, keywords and lengths only | `<math><mi>x</mi></math>` |

Free colours — `<font color>`, `style="color:…"` — are unwrapped: a colour is a token the look defines.

## Adding a profile or a tool

```php
$profile = InlineProfile::rich()
    ->without('marker')
    ->with(new InlineTool('keyboard', 'kbd', ['kbd']));

$sanitizer = new InlineSanitizer(['rich' => $profile, 'caption' => InlineProfile::plain()]);
```

An `InlineTool` has a name, the tag it writes, the patterns it reads — `span`, `span.class`, `a[attribute]` —, an
optional class it writes, the attributes it keeps with an `AttributeValue` each — `Link`, `Media`, `Token`, `Reference`,
`Text`, `Target`, `Rel`, `Length`, `Number` —, and the attributes it requires. A profile tries its tools in order: a
narrower pattern comes before a wider one. Give the sanitizer to both `Normalizer` and `HtmlRenderer`.

## Why its own sanitizer

`phil/markup` purifies whole documents at a level — `basic`, `forum`, `all` — through `symfony/html-sanitizer`. UJO
needs less and more: a rule per field rather than per document, the classes and data attributes of the Editor.js
inline tools kept exactly, and a canonical form so that a stored document compares equal to itself. And the readers of
`phil/markup` convert other formats into UJO, so phil/ujo cannot depend on it. An application that wants a second
pass may give the rendered HTML to `phil/markup`'s purifier at the `all` level; with the default profiles nothing is
removed by it.
