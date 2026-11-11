# Toggle

A button that stays pressed, on a checkbox: the browser presses it and submits it with the form, without JavaScript.

```twig {"preview":true}
<twig:Toggle name="bold" variant="outline"><b aria-hidden="true">B</b><span class="isVisuallyHidden">Bold</span></twig:Toggle>
```

## Installation

::: installation

## Usage

```twig
<twig:Toggle name="bold" variant="outline"><b aria-hidden="true">B</b><span class="isVisuallyHidden">Bold</span></twig:Toggle>
```

## Accessibility

The control is a checkbox inside its label, hidden but kept in the page: the keyboard reaches it, the space bar presses it, and screen readers say whether it is checked. The focus ring goes around the whole toggle. An icon alone needs a visually hidden name.
