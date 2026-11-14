# ColorPicker

The colour well of the browser, its hexadecimal value and swatches to choose from. The well is the field: it works alone without a script.

```twig {"preview":true}
<twig:ColorPicker label="Accent" name="accent" value="#336699" :swatches="[{value: '#336699', label: 'Blue'}, {value: '#993366', label: 'Plum'}]" />
```

## Installation

::: installation

## Usage

```twig
<twig:ColorPicker label="Accent" name="accent" value="#336699" :swatches="[{value: '#336699', label: 'Blue'}, {value: '#993366', label: 'Plum'}]" />
```

## Accessibility

Each swatch is a button with a name and `aria-pressed`; the name of a swatch says the colour in words, which its look alone would not. What is typed and is not a colour is refused, with `aria-invalid`, and the server validates in any case.
