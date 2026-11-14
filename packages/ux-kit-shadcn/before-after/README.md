# BeforeAfter

Two pictures one above the other, the first cut where a slider says.

```twig {"preview":true}
<twig:BeforeAfter label="Part of the first picture shown">
    <twig:block name="before"><img src="/before.webp" alt="Before"></twig:block>
    <twig:block name="after"><img src="/after.webp" alt="After"></twig:block>
</twig:BeforeAfter>
```

## Installation

::: installation

## Usage

```twig
<twig:BeforeAfter label="Part of the first picture shown">
    <twig:block name="before"><img src="/before.webp" alt="Before"></twig:block>
    <twig:block name="after"><img src="/after.webp" alt="After"></twig:block>
</twig:BeforeAfter>
```

## Accessibility

The slider is a native range: the arrows, Home and End are the browser’s, mirrored right to left. The position goes through a custom property set from the script, which a strict Content-Security-Policy allows. Without a script, the two pictures stand one under the other.
