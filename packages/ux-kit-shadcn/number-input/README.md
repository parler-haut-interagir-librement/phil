# NumberInput

A number field with a button on each side.

```twig {"preview":true}
<twig:NumberInput label="Guests" name="guests" :value="2" :min="1" :max="8" />
```

## Installation

::: installation

## Usage

```twig
<twig:NumberInput label="Guests" name="guests" :value="2" :min="1" :max="8" />
```

## Accessibility

The field keeps its keyboard: the arrows, Page Up and Page Down as the browser gives them. As in the spin button of WAI-ARIA, the buttons are for the pointer and left out of the Tab order; they stop at the limits and stay hidden without a script.
