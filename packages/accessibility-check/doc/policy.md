# Policy

`Policy` is the one point of configuration of a check. Its scalar settings are arguments of its constructor; its
collections are added with `with…()`, each returning a new policy.

```php
use Phil\AccessibilityCheck\ContrastLevel;
use Phil\AccessibilityCheck\HeadingPolicy;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Severity;

$policy = new Policy(
    headings: HeadingPolicy::None,
    contrast: ContrastLevel::AAA,
    foreground: '#222',
    background: '#fdfaf3',
    language: 'fr',
    locale: 'fr',
)
    ->withColourTokens(['accent' => '#8a1c1c', 'muted' => '#6b6b6b'])
    ->withGenericLinkTexts('fr', 'cliquez ici', 'ici', 'lire la suite', 'découvrir')
    ->withoutRules('svg-name')
    ->withSeverity('link-text', Severity::Error);
```

## Limits

| Argument | Default | Refuses |
|---|---|---|
| `maxBytes` | 5 242 880 (5 MiB) | an HTML content, or an HTML field of UJO, larger than this, before it is parsed |
| `maxDepth` | 256 | elements nested deeper; in UJO, blocks and their data nested deeper |
| `maxElements` | 100 000 | an HTML content of more elements; a UJO document of more blocks |

A limit below 1 throws `InvalidPolicy`. A content over a limit throws `LimitExceeded`, which the constraint, the guard
and the command turn into a refusal.

## Headings

`headings` is a `HeadingPolicy`: `Single`, the default — one `h1` at most, the content is the whole page or holds its
title —; `None` — the page shows its title above the content, whose headings start at `h2`, and `heading-order` counts
an `h1` before the first heading —; `Any` — `heading-h1` says nothing.

## Contrast and colours

| Argument | Default | Means |
|---|---|---|
| `contrast` | `ContrastLevel::AA` | 4.5:1 and 3:1 for large text; `AAA`, 7:1 and 4.5:1 |
| `foreground` | `#000000` | the colour of a text the content gives a background but no colour |
| `background` | `#ffffff` | the colour behind a text the content gives a colour but no background, and behind translucent backgrounds |

`foreground` and `background` are opaque CSS colours, else `InvalidPolicy`: those of the look the content is shown
in. `withColourTokens(array<string, string>)` gives the colours of the tokens of the look, which UJO writes as
`<span class="text-color" data-color="accent">`; a token the policy does not know is the look's business, and the text
is not checked. A token's colour may be translucent; one that cannot be read throws `InvalidPolicy`.

## Languages

`language` is the language of a content that says nothing of its own — no `lang` around a link, no `language` tune —
for `link-text`; the `$language` argument of `checkHtml()` and `checkUjo()` stands for it on one check.

## Generic link texts

`genericLinkTexts` holds, by primary language, the texts that say nothing of where a link leads. The defaults
(`GenericLinkText::DEFAULTS`) cover English, French, Spanish, German, Italian and Portuguese.
`withGenericLinkTexts(string $language, string ...$texts)` replaces the list of one language — `fr`, `nl` — with texts
normalized as link names are: lower case, collapsed spaces, without the punctuation and arrows around them. Without
texts, it turns the language off.

## Rules

| Member | Does |
|---|---|
| `disabledRules`, `withoutRules(string ...)` | the rules that do not run; they have no result in the report |
| `severities`, `withSeverity(string, Severity)` | a severity for every finding of a rule, its results included |
| `isEnabled(string)` | whether a rule runs |

## Messages

`locale` is the locale the report writes its messages in, `en` by default: see [Messages](messages.md).
