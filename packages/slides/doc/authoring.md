# Authoring

A deck is a UJO (Universal JSON Output) document: the block editor of the application writes it, or `phil/markup`
reads it from Markdown or another format. Nothing in the document is specific to slides: the same document reads as
an article when it is rendered whole.

## Where slides are cut

The top-level blocks are read in order.

| Block | Effect |
|---|---|
| `delimiter` — `---` in Markdown, after a blank line — or `pageBreak` — `\page` | Ends the slide, and is not shown |
| `header` up to `heading_level` | Starts a slide, unless the slide holds nothing shown yet |
| `pageStyle` — `\page {.lead}` | Gives its classes to the slide that holds it, and is not shown |
| `pageNumber` of mode `skip` — `{{skipCounting}}` | Hides the number of the slide that holds it, and is not shown |
| Any other block | Shown on the current slide |

`split` chooses what cuts: separators, headings, both, or, with `auto`, separators when the document holds one at the
top level and headings otherwise — a document from the block editor, with headings and no separator, is cut at its
headings. A slide with nothing shown and no note is dropped, so two separators in a row, or a separator before a
heading, make no empty slide. The title of a slide is the plain text of its first heading; it names the slide for
assistive technologies and in the presenter view.

In Markdown, leave a blank line before `---`: a line of text followed by `---` is a heading, as CommonMark reads it.
A `---` on the first line opens the front matter.

## Notes

The speaker's notes are taken out of the slide wherever they are nested — in a column, a toggle, a box — and are never
rendered for the audience:

- a `container` block whose classes name `notes_class` — `::: notes` … `:::` in Markdown — keeps its blocks, with
  their emphasis, lists and links;
- the `note` tune of any block is a plain-text note, its lines kept; `phil/markup` gives it to the block after an HTML
  comment, or to the last block when nothing follows.

The `note` tune of a block that closes a slide — a separator, or a heading that starts the next slide — belongs to the
slide it closes: a note written at the end of a slide stays with that slide.

```markdown
## The plan

Three steps.

::: notes
Ask who has tried it already. Keep **five minutes** for questions.
:::
```

A slide that holds only notes is kept: the speaker sees it, the audience sees an empty slide.

## Fragments

A `container` block whose classes name `fragment_class` — `::: fragment` … `:::` in Markdown — appears on its own key
press, in the order of the document, nested ones included. Without script, and printed, every fragment shows.

```markdown
::: fragment
First, the problem.
:::

::: fragment
Then, the answer.
:::
```

## Directives

The metadata of a source — the YAML front matter `phil/markup` reads before a Markdown text — are the global
directives of the deck, under the names Marp gives them.

| Directive | Value | Effect |
|---|---|---|
| `title` | Text, up to 200 characters | The name of the deck for assistive technologies, the title of the presenter view and of the PDF |
| `lang` | A BCP 47 tag | The `lang` of the deck and of the printed document |
| `class` | Up to 16 class names, separated by spaces | Classes of every slide |
| `paginate` | `true` or `false` | The number of each slide in its footer |
| `header`, `footer` | Text, up to 200 characters | Repeated on every slide |
| `size` | `16:9` or `4:3` | The shape of the slides; `aspect_ratio` otherwise |
| `headingDivider` | 1 to 6 | Cuts at separators and at headings up to that level |

A directive with a value of the wrong kind, and any other name — `theme`, `style`, `backgroundColor`… — is not kept:
`Deck::$ignoredDirectives` lists them, for an editor to show. Colours and backgrounds belong to the look of the site:
give the slides a class and style it. A UJO document has no front matter: `slides_deck()` and
`DeckReader::fromDocument()` take the directives as an array.

## Notes in HTML comments

Marp writes the notes of a slide, and the directives of one slide, in HTML comments:

```markdown
---

<!-- _class: lead -->
<!-- _paginate: false -->

# Welcome

<!-- Thank the organisers. -->
```

The HTML reader of `phil/markup` keeps each comment as the `note` tune of the block after it, and its Markdown reader
does the same from the `phil/markup` 0.2 release after 0.2.1-alpha. The deck then reads them with no other change:

- a comment made of Marp directives alone — each line `name: value` with a name Marp gives a directive — is read as
  directives, not as a note. `_class` gives classes to its slide, `class` to its slide and the ones after; `_paginate`
  and `paginate`, `true` or `false`, show or hide the number of its slide, or of its slide and the ones after, whatever
  the front matter says. Every other directive name, and a value of the wrong kind, is listed in
  `Deck::$ignoredDirectives`;
- any other comment is a note of its slide.

A value may be quoted. A line that is not a directive makes the whole comment a note, so a note that starts with
`Title: …` should not stand alone in its comment. With an earlier `phil/markup`, the Markdown reader leaves the
comments out: write `\page {.lead}` and `{{skipCounting}}` for the slide, and `::: notes` for the notes, which every
version reads.

## Making a deck in code

```php
use Phil\Slides\Application\DeckReader;

$deck = $reader->fromMarkup($source);                       // Markdown, read by phil/markup
$deck = $reader->fromMarkup($source, 'mediawiki');          // another format of phil/markup
$deck = $reader->fromDocument($page->body, ['title' => 'Q3']); // a document, or its UJO JSON
$deck = $reader->fromReading($reading);                     // what phil/markup already read
```

`DeckReader` is a public service. A deck's identifier is a hash of its blocks; `Deck::withId()` gives it a stable one
— the identifier of the talk — so that the windows of a deck stay together while it is edited.
