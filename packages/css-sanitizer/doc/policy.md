# Policy

A `Policy` says what a style sheet may hold. It is immutable: each `with…()` returns a new policy. Names are compared
in ASCII lower case, as CSS compares them.

## The default policy

`new Policy()` allows what a page laid out for the screen or for print needs:

| List | Default |
|---|---|
| Properties (`DEFAULT_PROPERTIES`) | Box (margins, paddings, borders, radius, outline, shadow), size, display and float, flex and grid, columns, fragmentation (`break-*`, `page-break-*`, `orphans`, `widows`, `page`), generated content (`content`, `quotes`, counters, lists, `string-set`, bookmarks, footnotes), text and fonts, background, opacity, filters, transforms, `clip-path`, tables |
| Functions (`DEFAULT_FUNCTIONS`) | Colours (`rgb()` to `light-dark()`), mathematics (`calc()`, `min()`, `max()`, `clamp()`, …), grids (`repeat()`, `minmax()`, `fit-content()`), gradients, `counter()`, `counters()`, `attr()`, `string()`, `content()`, `leader()`, `target-counter()`, `target-counters()`, `target-text()`, transforms, filters |
| Pseudo-classes (`DEFAULT_PSEUDO_CLASSES`) | Structural (`:root`, `:empty`, `:first-child`, `:nth-child()` …), `:not()`, `:is()`, `:where()`, `:lang()`, `:dir()`, `:link`, `:hover`, `:focus`, `:focus-visible`, `:focus-within`, `:active`, `:target` |
| Pseudo-elements (`DEFAULT_PSEUDO_ELEMENTS`) | `::before`, `::after`, `::first-letter`, `::first-line`, `::marker`, `::selection`, `::footnote-call`, `::footnote-marker` |
| Attributes (`DEFAULT_ATTRIBUTES`) | `lang`, `dir` |
| Media types (`DEFAULT_MEDIA_TYPES`) | `all`, `print`, `screen` |
| At-rules | `@media`, `@supports`, `@page`, `@font-face`; not `@import` |
| Nesting | Three levels of rules nested in a style rule |
| URL rewriter | `RefuseUrls`: every URL refused |

It refuses, among others: `position`, `top`, `inset` and `z-index`, which can lay a box over the application's own page;
`cursor`, transitions and animations; `:visited`, `:has()` and `:checked`; every attribute selector but `lang` and
`dir`, so that a selector cannot read the value of a field.

## Changing it

```php
$policy = new Policy()
    ->withProperties('position', 'z-index')       // added to the list
    ->withoutProperties('filter')                 // taken out of it
    ->withFunctions('env')
    ->withoutFunctions('attr')
    ->withPseudoClasses('checked')
    ->withoutPseudoClasses('hover')
    ->withPseudoElements('placeholder')
    ->withoutPseudoElements('selection')
    ->withAttributes('data-*', 'title')           // `data-*` allows every data- attribute
    ->withoutAttributes('dir')
    ->withMediaTypes('print')                     // replaces the list
    ->withoutAtRules(AtRuleKind::FontFace)
    ->withAtRules(AtRuleKind::FontFace)
    ->withUrlRewriter($rewriter)
    ->withScope('.user-styles')
    ->withImportant(false)
    ->withCustomProperties(true)
    ->withMaxLength(65_536)
    ->withMaxDepth(16)
    ->withMaxNesting(2);
```

The lists are public, as sets: `$policy->properties['color']` is `true`.

## Never allowed

`withProperties()` throws `InvalidPolicy` for `behavior`, `-ms-behavior` and `-moz-binding` (`DENIED_PROPERTIES`), which
run code or bind behaviour. `withFunctions()` throws for `expression()`, `url()`, `src()`, `image()`, `image-set()`,
`-webkit-image-set()`, `cross-fade()`, `-webkit-cross-fade()`, `element()`, `-moz-element()` and `paint()`
(`DENIED_FUNCTIONS`), which run code or load a resource from a string. `url()` and `image-set()` are handled by the
rewriter instead: each image of `image-set()` is rewritten as any `url()` ([URLs](urls.md)).

## `@import`

`AtRuleKind::Import` is the one at-rule the default policy leaves out. `withAtRules(AtRuleKind::Import)` keeps the
`@import` rules that open a style sheet, their URL rewritten with `UrlKind::StyleSheet`; the style sheets it loads are
not sanitized by this pass, so the rewriter returns only those the application trusts. See [At-rules](at-rules.md).

## Scope

`withScope('.user-styles')` places every style rule under that selector: `p, h1` becomes
`.user-styles p, .user-styles h1`, inside `@media` and `@supports` too. Page rules are not scoped, and neither are
nested rules: their selectors are relative to a parent that already is, and `&` cannot reach outside it
([Selectors](selectors.md)). The scope is the
application's, and is written as given. A selector that starts with `:root`, `html` or `body` matches nothing under
the scope; the application decides whether its users need them.

## `!important` and custom properties

`withImportant(false)` removes the flag from every declaration, which stays, and lists each one as an `Important`
removal.

`withCustomProperties(true)` allows custom properties (`--accent: #702`) and `var()`. Their values are checked like any
other: a custom property cannot carry a URL the rewriter refuses, nor a function the policy does not allow.

## Nesting

`maxNesting`, 3 by default, is how many levels of rules may nest inside a style rule, CSS Nesting's way; an `@media` or
`@supports` rule nested in a style rule counts as a level. A rule one level deeper is removed (`Nesting`) with what it
holds, and its parent stays. `withMaxNesting(0)` removes every nested rule. It cannot be negative.

## Limits

`maxLength`, one mebibyte by default, is the longest source accepted, in bytes; `maxDepth`, 32 by default, the deepest
nesting of blocks and functions. A source past either is refused whole, with a single `TooLong` or `TooDeep` removal.
Both must be at least 1. The sanitizer reads about a mebibyte in two seconds.
