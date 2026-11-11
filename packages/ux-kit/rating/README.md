# Rating

A note out of a number of stars: radio buttons with a name, a single image without one, in half stars.

```twig {"preview":true}
<twig:Rating label="Your note" name="note" :value="3" clearLabel="No note" />
```

## Installation

::: installation

## Usage

```twig
<twig:Rating label="Your note" name="note" :value="3" clearLabel="No note" />
```

## Accessibility

The radio buttons give the keyboard and the form, in both directions; each star is read by `starLabel`. Read-only, the whole note is one image with one name. Stars keep a target of 2.75rem, and under forced colours a system colour tells them apart.
