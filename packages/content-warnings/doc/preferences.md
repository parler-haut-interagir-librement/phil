# Preferences and decisions

## Displays

`Display` says what happens to a piece of content with a warning: `Show` it with its warning in view, `Fold` it behind
its warning until the reader reveals it, or `Hide` it. The cases go from the most open to the strictest;
`isStricterThan()` compares two, and `Display::strictest(...$displays)` gives the strictest, `Show` for none. A
safety tool's veil folds, its line hides.

## A reader's preferences

`WarningPreferences` holds a reader's choices: a `Preference` per theme, a `Preference` per category, and the default
display of every other warning — `Fold` unless given.

```php
$preferences = WarningPreferences::defaults(Display::Show)
    ->withCategory('phobias', Display::Fold)
    ->withTheme('gore', Display::Hide, Severity::Moderate)
    ->withTheme('spiders', Display::Hide);
```

A `Preference` is a display, from a severity on when one is given: below that severity it does not apply. A warning
of unknown severity is taken at its most severe, so the preference applies to it.

| Method | Gives |
|---|---|
| `defaults($display)`, `withDefault($display)` | The default display |
| `withTheme()`, `withoutTheme()`, `withCategory()`, `withoutCategory()` | New preferences with one added, replaced or removed |
| `theme($key)`, `category($key)`, `themes()`, `categories()` | The preferences kept |
| `themesWith($display)` | The keys of the themes given that display, whatever the severity |
| `displayFor($warning, $catalogue)` | The display of one warning |

For one warning, the preference of its theme wins over that of its theme's category, read in the catalogue, which
wins over the default; a preference that does not apply at the warning's severity gives way to the next. A theme the
catalogue does not have answers to its own preference and to the default only. Preferences hold 2,200 entries at
most.

`toArray()` writes them for storage — `['default' => 'fold', 'themes' => ['gore' => ['display' => 'hide', 'at_least'
=> 'moderate']], 'categories' => []]` — and `WarningPreferences::fromArray()` reads them back, throwing
`UnreadableWarnings` for anything else.

## Decisions

`WarningMatcher` is built with the catalogue and decides for one piece of content and one reader:

```php
$decision = new WarningMatcher($catalogue)->decide($warnings, $preferences);
```

The `Decision` holds the `display` — the strictest display of the warnings, `Show` without warnings —, all the
`warnings`, and `because`, the warnings that gave that display. `isShown()`, `isFolded()`, `isHidden()` and
`hasWarnings()` read it. A decision takes time linear in the number of warnings, about a microsecond each.
