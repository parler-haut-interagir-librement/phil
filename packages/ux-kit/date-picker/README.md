# DatePicker

The date field of the browser, which works alone, and a button opening a month on a grid, in a popover; or, with
`range`, two date fields, the start and the end, and two months side by side.

```twig {"preview":true}
<twig:DatePicker id="start" name="start" label="Start" min="2026-01-01" />
```

## Installation

::: installation

## Usage

```twig
<twig:DatePicker id="start" name="start" label="Start" min="2026-01-01" />
```

## Examples

### A range

`:range="true"` gives two date fields of the browser, each under its visible label, `startLabel` and `endLabel`: the
start, named by `name`, and the end, named by `endName`, with the identifier `id` followed by `-end`. `label` names the
group of both. The button opens two months side by side, one above the other where the screen is too narrow, with one
pair of buttons moving both. The first day chosen is the start and the second the end, the two swapped when the end
comes first; until the second, the days between the start and the day under the pointer or the focus are previewed. For
a booking, a stay or the rental of equipment:

```twig {"preview":true}
<twig:DatePicker
    id="stay"
    name="arrival"
    endName="departure"
    label="Stay"
    startLabel="Arrival"
    endLabel="Departure"
    openLabel="Choose dates"
    min="2026-01-01"
    :range="true"
/>
```

`min` and `max` hold both fields. The `min` of the end is the start, written by the template from `value`, then by the
controller whenever the start changes, so that the browser refuses an end before the start before sending the form.
That check stays in the browser: the application checks the range again on the server, as it does any value it
receives.

Without JavaScript, or where the browser has no popover, the two fields work alone: each opens the date picker of the
browser, and the form sends both.

`rangeStartLabel` and `rangeEndLabel`, `start of the range` and `end of the range` by default, end the names of the
first and the last day; the application translates them as it does `openLabel`, the dates being written in the language
of the page.

## Accessibility

The dialog follows the date picker of WAI-ARIA: the arrows move by day and week, mirrored right to left, Home and End to
the ends of the week, Page Up and Page Down by month, by year with Shift. Enter and the Space bar choose, Escape closes
and gives the focus back. Days out of `min` and `max` are disabled, and each day is read as a whole date in the language
of the page.

With `range`, the two months are one grid each, labelled by its month, and a single day takes the focus at a time: the
arrows go from one month to the other, and the months move only when the focus passes beyond them, so the keyboard never
stays caught in either. Each day of the range is `aria-selected` in a grid marked `aria-multiselectable`; its first and
its last day are read with `rangeStartLabel` and `rangeEndLabel`, and the start, once chosen, is announced by a live
region. On screen, the days of the range are filled and underlined, its start and its end filled loud and bold, so that
the range never rests on colour alone; under forced colours, the start and the end take the colours of the selection of
the system. The fill fades in only where `prefers-reduced-motion` allows it.
