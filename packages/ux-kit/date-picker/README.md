# DatePicker

The date field of the browser, which works alone, and a button opening a month on a grid, in a popover.

```twig {"preview":true}
<twig:DatePicker id="start" name="start" label="Start" min="2026-01-01" />
```

## Installation

::: installation

## Usage

```twig
<twig:DatePicker id="start" name="start" label="Start" min="2026-01-01" />
```

## Accessibility

The dialog follows the date picker of WAI-ARIA: the arrows move by day and week, mirrored right to left, Home and End to the ends of the week, Page Up and Page Down by month, by year with Shift. Enter and the Space bar choose, Escape closes and gives the focus back. Days out of `min` and `max` are disabled, and each day is read as a whole date in the language of the page.
