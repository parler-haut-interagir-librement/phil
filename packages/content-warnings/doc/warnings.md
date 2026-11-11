# Warnings

## A warning

A `ContentWarning` is one warning of a piece of content: the key of its theme, how severe the theme is there when it
is known, and an optional note — `new ContentWarning('gore', Severity::Severe, 'The final battle in chapter 12')`.
The note is trimmed, 500 characters at most; an empty one is none.

`Severity` goes from `Mild` to `Moderate` to `Severe`: a passing mention, a scene shown, a scene dwelt on.
`isAtLeast()` compares two severities. A warning without severity is taken at its most severe when preferences are
matched.

A warning names its theme by key and is not checked against a catalogue: content keeps its warnings when a label
changes, and a theme an application removes stays on the content that named it. Display code reads the theme's label
from the catalogue, and falls back to the key for a theme it no longer has.

## The warnings of a piece of content

`ContentWarnings` holds one warning per theme, in the order given, 100 at most.

| Method | Gives |
|---|---|
| `none()`, `of(...$warnings)`, `forThemes(...$keys)` | A list; a theme given twice is refused |
| `get($theme)`, `has($theme)`, `themes()` | One warning, whether there is one, the theme keys |
| `with($warning)`, `without($theme)` | A new list with a warning added or replacing the one of its theme in its place, or removed |
| `isEmpty()`, `count()`, iteration | The warnings in order |

## Storage

`toArray()` writes a list for one JSON column, leaving out what is not set:

```php
[['theme' => 'gore', 'severity' => 'severe', 'note' => 'The final battle'], ['theme' => 'spiders']]
```

`ContentWarnings::fromArray()` reads it back and throws `UnreadableWarnings` for anything `toArray()` does not write:
an entry that is not an array, an unknown key, a theme that is no valid key, an unknown severity, a theme twice, more
than 100 warnings.
