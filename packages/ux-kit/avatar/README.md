# Avatar

The picture of a person, over the initials that show when it is missing or broken, without script.

```twig {"preview":true}
<twig:Avatar>
    <twig:Avatar:Fallback>PG</twig:Avatar:Fallback>
    <twig:Avatar:Image src="/p.webp" alt="Philippe" />
</twig:Avatar>
```

## Installation

::: installation

## Usage

```twig
<twig:Avatar>
    <twig:Avatar:Fallback>PG</twig:Avatar:Fallback>
    <twig:Avatar:Image src="/p.webp" alt="Philippe" />
</twig:Avatar>
```

## Accessibility

The picture takes the name of the person as its `alt`, or an empty one when the name is written next to it.
