# Elements

The base layer, `phil.base`, dresses the HTML elements without a class.

| Elements | What they get |
|---|---|
| `*`, `::before`, `::after` | `box-sizing: border-box` |
| `html` | The sans-serif stack, the body line height, the text and surface colours, the brand as `accent-color` |
| `body` | No margin, the body size |
| `h1` to `h6` | The text scale from `3xl` down to `sm`, the tight line height, balanced lines |
| `p`, `ul`, `ol`, `dl`, `blockquote`, `pre`, `figure`, `table` | A bottom margin of `md`; `p` with pretty wrapping |
| `hgroup` | Its children without margins: an eyebrow and its heading read as one |
| `a` | The link colour, an underline drawn from the font, thicker on hover |
| `:focus-visible` | A ring of `--phil-color-focus`, 3:1 on the surface |
| `img`, `svg`, `video`, `canvas`, `picture` | Blocks, never wider than their container |
| `figcaption`, `small` | The small size; captions muted |
| `hr` | A rule of the border colour |
| `code`, `kbd`, `samp`, `pre` | The monospace stack; `pre` scrolls on the raised surface |
| `blockquote` | A brand rule on its start side |
| `mark` | The warning tint |
| `dt`, `dd` | A medium term, its description flush with it |
| `kbd`, `abbr[title]` | A key on the raised surface; a dotted underline |
| `[hidden]` | Hidden, whatever display a class sets; `until-found` is left to the browser |

## Forms

| Elements | What they get |
|---|---|
| `fieldset`, `legend` | A rounded frame, a medium title |
| `label` | A block over its control; beside it, at touch-target height, when it wraps a check box or a radio |
| Text inputs, `date`, `search` and the like, `select`, `textarea` | Full width, the height of a touch target (2.75rem), the loud neutral border, 3:1 on the surface |
| `::placeholder` | The muted text colour |
| `checkbox`, `radio`, `range`, `date`, the pickers | The browser's own controls, in the brand through `accent-color` and the scheme through `color-scheme`; a check box or a radio is 24 CSS pixels |
| `color`, `file` | A framed swatch; `::file-selector-button` as a quiet button |
| `:user-valid` | The success border |
| `:user-invalid`, `[aria-invalid="true"]` | The danger border, doubled so that it does not lean on colour alone |
| `:disabled` | Faded, a not-allowed cursor |

`:user-valid` and `:user-invalid` wait for the reader to touch the field, so a blank form opens without errors.

### Symfony's forms

The markup of `form_row` is dressed as Symfony writes it, with no form theme to install.

| Markup | What it gets |
|---|---|
| `form div` holding a `label` or a button | The space between two rows |
| `label.required` | A star, hidden from screen readers, since the field carries `required` |
| The errors, a plain `ul` after the label or first in the form | No bullets, small, in the danger colour |
| `.help-text` | Small and muted, under the widget |
| A check box row, label first | The box before its label, without touching the order of the source |
| Expanded choices, siblings in a `div` | A box and its label on each line |

## Buttons

`button`, `input` of type `submit`, `button` or `reset`, and `[role="button"]`: solid and in the brand, 2.75rem high,
faded when disabled or `aria-disabled`. Hovering or pressing mixes the fill towards the text colour. See
[Components](components.md) for the other looks.

## Tables

| Elements | What they get |
|---|---|
| `table` | Full width, collapsed, tabular figures |
| `caption` | Small, muted, at the start |
| `th`, `td` | Start-aligned, a line under each row |
| `thead th`, `tfoot` | A heavy line of the loud neutral, a medium weight |
| `figure:has(> table)` | Scrolls a table too wide for the page |

Striped rows are the class `isStriped`.

## Dialogs and popovers

| Elements | What they get |
|---|---|
| `dialog` | The `--phil-panel-md` width or `--phil-dialog-size`, at most the height of the screen, scrolling inside; modal or not |
| `dialog > footer` | Its actions at the end of the line |
| `dialog::backdrop` | `--phil-color-backdrop` |
| `html:has(dialog:modal)` | Holds the page still behind a modal |
| `[popover]` | A panel, centred by the browser until a controller places it and sets `data-placed` |

They fade in and out where the browser can animate the top layer.

## Disclosures, navigation and gauges

| Elements | What they get |
|---|---|
| `details`, `summary` | A summary tall enough to touch; the content opens to `height: auto` where the browser allows it |
| `nav` lists | No bullets, in a row that wraps |
| `nav a` | The height of a touch target |
| `nav a[aria-current]` | The text colour and a bold weight |
| `progress` | A rounded bar in the brand; an indeterminate one travels, and stands still under reduced motion |
| `meter` | The same, success, warning or danger as the browser judges the value |

Every direction is a logical property, so a right-to-left page needs nothing more. The only physical axis is the
scrolling of tables, tab bars, code and carousels, written `overflow-x` because `overflow-inline` is not yet in every
browser this stylesheet supports.

Under `prefers-reduced-motion: reduce`, or below a root that carries `data-motion="reduce"`, every animation and
transition ends at once. These declarations are `!important` in the base layer, which makes them win over every later
layer and over the site's own styles.

## The reader's display preferences

The page follows the system's media queries. An application that lets each reader choose writes the choice on the
root element, and the base layer applies it as it applies the query; without the attribute, the query decides.

| Attribute on the root | Same as | Effect |
|---|---|---|
| `data-theme="light"` or `"dark"` | `prefers-color-scheme` | The scheme, see [Themes](themes.md) |
| `data-motion="reduce"` | `prefers-reduced-motion: reduce` | Every animation and transition ends at once |
| `data-contrast="more"` | `prefers-contrast: more` | Muted text takes the text colour, borders the loud neutral border |
| `data-text-size="large"` or `"larger"` | the browser's text size | The root at 112.5 % or 125 %, so every size of the scale follows |

```twig
<html lang="{{ app.request.locale }}" data-motion="reduce" data-text-size="large">
```
