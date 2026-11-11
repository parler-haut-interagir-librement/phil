# Button

A button, or a link that looks like one with `as="a"`.

```twig {"preview":true}
<twig:Button variant="outline">Save</twig:Button>
```

## Installation

::: installation

## Usage

```twig
<twig:Button type="submit">Save</twig:Button>
<twig:Button as="a" href="/events" variant="link">All events</twig:Button>
<twig:Button variant="ghost" size="sm" aria-pressed="true">Month</twig:Button>
<twig:Button :loading="true">Sending</twig:Button>
```

## Accessibility

A toggle takes `aria-pressed`, a link to the page shown `aria-current`: any attribute reaches the element. With `loading` the button says `aria-busy`.
