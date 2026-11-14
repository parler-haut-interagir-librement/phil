# Tag

A short label, which a button can take away.

```twig {"preview":true}
<twig:Tag label="Symfony" :removable="true" name="tags[]" />
```

## Installation

::: installation

## Usage

```twig
<twig:Tag label="Symfony" :removable="true" name="tags[]" />
```

## Accessibility

The button is named by `removeLabel` and stays hidden without a script. Once the tag is gone, the focus goes to the tag beside it, or to the list. `tag:remove` can be cancelled, for a server to answer first.
