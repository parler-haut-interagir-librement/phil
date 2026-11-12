# Rules

The rules of `DefaultRules::all()`, in the order they run. Each reads HTML; each but `html-lang` also reads UJO. The
severity is the rule's unless the row says otherwise for one case, and the policy may give a rule another one. A
message key is the rule's identifier and the case, such as `image-alt.missing`; the placeholders are in braces.

| Rule | Criterion | Severity | Checks |
|---|---|---|---|
| `image-alt` | 1.1.1 Non-text Content (A) | error | images have a text alternative |
| `heading-order` | 1.3.1 Info and Relationships (A) | warning | no heading level is skipped |
| `heading-h1` | 1.3.1 Info and Relationships (A) | warning | the first-level headings the policy allows |
| `empty-heading` | 2.4.6 Headings and Labels (AA) | warning | headings have text |
| `link-name` | 2.4.4 Link Purpose (In Context) (A) | error | links have a name |
| `button-name` | 4.1.2 Name, Role, Value (A) | error | buttons have a name |
| `link-text` | 2.4.4 Link Purpose (In Context) (A) | warning | link names say where they lead |
| `color-contrast` | 1.4.3 Contrast (Minimum) (AA), or 1.4.6 (AAA) | error | coloured text stands out from its background |
| `table-headers` | 1.3.1 Info and Relationships (A) | error | tables of data mark their header cells |
| `html-lang` | 3.1.1 Language of Page (A) | error | a whole page says its language |
| `lang-change` | 3.1.2 Language of Parts (AA) | error | passages in another language say which |
| `frame-title` | 4.1.2 Name, Role, Value (A) | error | frames say what they hold |
| `video-captions` | 1.2.2 Captions (Prerecorded) (A) | error | videos carry captions |
| `duplicate-id` | 4.1.2 Name, Role, Value (A) | warning | an identifier names one element |
| `svg-name` | 1.1.1 Non-text Content (A) | warning | drawings say what they show |

What is shown counts: an element that is `hidden`, `aria-hidden="true"`, `display: none` or `visibility: hidden`
inline, or inside one of them, or inside `head`, `script`, `style`, `template` or `noscript`, is left alone.

## `image-alt`

In HTML, an `img`, an `area` with an `href` and an `input type="image"` have an `alt`, or a name of ARIA
(`aria-label`, `aria-labelledby`) or a `title`. `alt=""` marks a decorative image and passes, as does an `img` of role
`presentation` or `none`. In UJO, an `image` block has an `alt` or a caption, and each file of a `gallery` a caption.

| Key | Severity | Placeholders | When |
|---|---|---|---|
| `image-alt.missing` | error | `source` | no alternative at all |
| `image-alt.blank` | warning | `source` | an `alt` of spaces only |
| `image-alt.filename` | warning | `source`, `alt` | the alternative is a file name — `photo.jpg`, `IMG_2041`, `DSC0001`, `untitled`, the name of the image's own file |

## `heading-order`

A heading goes at most one level deeper than the heading before it: an `h4` after an `h2` skips a level. Going back
up any number of levels passes. Headings are the `h1`–`h6`, the elements of role `heading` with their `aria-level`
(2 without one), and, in UJO, the `header` blocks with their `level` (2 without one) and the headings of the HTML
fields, in document order. With `HeadingPolicy::None`, the content sits under the title of its page, so its first
heading is an `h2`.

| Key | Placeholders |
|---|---|
| `heading-order.skipped` | `level`, `previous` |

## `heading-h1`

| Policy | Checks | Key | Placeholders |
|---|---|---|---|
| `HeadingPolicy::Single` | one `h1` at most: each further one is reported | `heading-h1.multiple` | `position` |
| `HeadingPolicy::Single` | a whole HTML page has an `h1` (notice) | `heading-h1.missing` | |
| `HeadingPolicy::None` | no `h1`: each one is reported | `heading-h1.forbidden` | |
| `HeadingPolicy::Any` | nothing | | |

## `empty-heading`

A heading has an accessible name — its text, the alternative of an image in it, a name of ARIA.

| Key | Placeholders |
|---|---|
| `empty-heading.empty` | `level` |

## `link-name`

An `a` with an `href` has an accessible name: its text, the alternative of its images, the title of its drawings, a
name of ARIA, else its `title`. In UJO, a `button` block has a text.

| Key | Placeholders |
|---|---|
| `link-name.empty` | `href` |

## `button-name`

A `button`, an `input` of type `button`, `submit` or `reset`, and any element of role `button` have an accessible
name; a `submit` or `reset` input without a value takes the name the browser gives it.

| Key | Placeholders |
|---|---|
| `button-name.empty` | |

## `link-text`

The name of a link is not one of the generic texts of its language: "click here", "read more", "ici", "lire la
suite", "aquí", "mehr", "qui", "aqui"… ([Policy](policy.md#generic-link-texts)). The language is the `lang` of the
link or of its nearest ancestor, else the language of the content; when it is not known, the texts of every language
count. The comparison ignores case, spaces, and the punctuation and arrows around the text. In UJO, the text of a
`button` block is compared too. An empty name is `link-name`'s.

| Key | Placeholders |
|---|---|
| `link-text.generic` | `href`, `text` |

## `color-contrast`

A text the content colours has the contrast ratio of WCAG with its background: 4.5:1, or 3:1 for large text, with
`ContrastLevel::AA`; 7:1 and 4.5:1 with `ContrastLevel::AAA`, whose findings name 1.4.6. The ratio is computed from the
relative luminance of WCAG and written rounded down, never up.

- **Only the colours of the content are known.** A `style` with `color`, `background-color` or a `background` of one
  colour, the `color` and `bgcolor` attributes, and the tokens of the look that UJO writes in `data-color`, whose
  colours the policy holds. A text whose colours all come from the style sheets of the look is not checked; where the
  content gives one of the two colours, the policy's `foreground` or `background` stands for the other.
- **Colours are read as CSS writes them**: hexadecimal, `rgb()`, `hsl()`, `oklch()`, the named colours,
  `transparent`, opacity included; a translucent colour is laid over what is behind it. An `oklch()` colour goes
  through OKLab to sRGB, each channel clipped to sRGB when it falls outside. A text over an image or a gradient, or in a
  colour that cannot be computed, is left alone.
- **`var()` and `color-mix()` are not resolved**: only the style sheets of the page give their colour. The element
  that gives its text or its background such a colour, when it shows text, gets one notice,
  `color-contrast.unresolved`, so that a person checks that text in a browser; its text is not measured.
- **Large text** is 24 CSS pixels, or 18.66 in bold. The size is the nearest inline `font-size` in `px`, `pt`, `em`,
  `rem` or `%` (of 16 pixels), else the default size of the heading the text is in; the weight is the nearest inline
  `font-weight`, else bold inside `b`, `strong`, `th` or a heading.

Each element whose own text is shown is checked once.

| Key | Placeholders |
|---|---|
| `color-contrast.insufficient` | `text` (cut to 40 characters), `ratio`, `foreground`, `background`, `required` |
| `color-contrast.unresolved` (notice) | `value`, the `color`, `background-color` or `background` written, cut to 60 characters |

## `table-headers`

In HTML, a `table` of two rows or more holds a `th`, or a cell of role `columnheader` or `rowheader`; the rows and
cells of a table nested in it are its own table's. A table of role `presentation` or `none` lays out and is left alone.
In UJO, a `table` block of two rows or more has `withHeadings`, or a cell marked `header`.

| Key | Placeholders |
|---|---|
| `table-headers.missing` | |

## `html-lang`

A whole page has a well-formed language tag of BCP 47 in the `lang` of its `html` element. A fragment takes the
language of the page it is shown in, and is not checked.

| Key | Placeholders | When |
|---|---|---|
| `html-lang.missing` | | no `lang`, or a blank one |
| `html-lang.invalid` | `lang` | a `lang` that is not a well-formed tag |

## `lang-change`

Every `lang` below the `html` element is a well-formed tag; `lang=""`, a passage of unknown language, is noted. In
UJO, the `language` tune of a block names a well-formed tag, and a change of direction without a language — a tune
with only a direction, a `bdi`, a `bdo` or a `span` with `dir` and no `lang` — is noted, since a passage written the
other way is most often in another language.

| Key | Severity | Placeholders |
|---|---|---|
| `lang-change.invalid` | error | `lang` |
| `lang-change.empty` | notice | |
| `lang-change.direction-only` | notice | |

## `frame-title`

Every `iframe` and `frame` has a `title`, or a name of ARIA; the text inside an `iframe` does not count. In UJO, an
`embed` block has a `title`.

| Key | Placeholders |
|---|---|
| `frame-title.missing` | `source` |

## `video-captions`

A `video` has a `track` child of kind `captions` or `subtitles` — the kind of a track without one. In UJO, a `video`
block has such a track among its `tracks`. Whether the track says everything the sound says is the author's to check;
an `audio` player, whose alternative is a transcript, is not checked.

| Key | Placeholders |
|---|---|
| `video-captions.missing` | `source` |

## `duplicate-id`

An identifier names one element. It is reported once, where it is given the second time: an error when a label, an
attribute of ARIA (`aria-labelledby`, `aria-describedby`, `aria-controls`…), a link to a fragment or a footnote
reference refers to it, a warning otherwise. In UJO, the identifiers are those of the `anchor` tunes, of the
`footnote` blocks and of the elements of the HTML fields, across the whole document.

| Key | Placeholders |
|---|---|
| `duplicate-id.duplicate` | `id`, `count` |

## `svg-name`

A drawing written in the page has a `title` child, or a name of ARIA, or is decorative: `aria-hidden="true"`, or a
role of `presentation` or `none`. A drawing nested in another, or inside a link or a button, which names it, is left
alone. In UJO, the drawings a block keeps in `rendered` are checked as HTML fields.

| Key | Placeholders |
|---|---|
| `svg-name.missing` | |

## What no rule checks

What depends on the look and the behaviour of a page rather than on its content: keyboard use, focus, the contrast of
colours set by style sheets, motion, timing, forms and their errors. What needs a person: whether an alternative text
describes its image, whether captions say what the sound says, whether a heading names its section.
