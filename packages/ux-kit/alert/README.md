# Alert

A message that stands out: an error, a confirmation.

```twig {"preview":true}
<twig:Alert variant="destructive">
    <twig:Alert:Title>Not saved</twig:Alert:Title>
    <twig:Alert:Description>The title is missing.</twig:Alert:Description>
</twig:Alert>
```

## Installation

::: installation

## Usage

```twig
<twig:Alert variant="destructive">
    <twig:Alert:Title>Not saved</twig:Alert:Title>
    <twig:Alert:Description>The title is missing.</twig:Alert:Description>
</twig:Alert>
```

## Accessibility

The role `alert` is announced at once; a message of no urgency takes `role="status"`, which the attribute replaces.
