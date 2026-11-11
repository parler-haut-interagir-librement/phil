# At-rules

The policy allows `@media`, `@supports`, `@page` and `@font-face` by default (`AtRuleKind`); `withoutAtRules()` takes
them out, and `withAtRules(AtRuleKind::Import)` adds `@import`. Any other at-rule — `@charset`, `@namespace`,
`@keyframes`, `@layer`, `@container`, `@property`, `@font-feature-values` — is removed (`AtRule`), and `@import` is
unless the policy allows it (`Import`). An at-rule whose prelude is refused is removed whole (`Prelude`).

## `@media`

The prelude is a list of media queries. Each holds the keywords `only`, `not`, `and` and `or`, the policy's media
types (`all`, `print`, `screen`), and features in parentheses made of names, numbers, dimensions, percentages, colons,
ratios and comparisons — `(min-width: 30em)`, `(400px <= width <= 700px)`, `(aspect-ratio: 16/9)`. An empty prelude
matches all media. The block holds rules, checked as the top level is: style rules, and the allowed at-rules.

## `@supports`

The prelude is a condition: `not`, `and`, `or`, conditions in parentheses and `selector()`, holding no URL, no bad
string and no `{}` block. The block holds rules.

## `@page`

The prelude is a list of page selectors: an optional page name followed by `:first`, `:left`, `:right` or `:blank`,
without space between them — `@page :first`, `@page chapter:left, index`. The block holds declarations — the policy's
properties and the descriptors `size`, `marks`, `bleed` and `page-orientation` — and the at-rules of the sixteen
margin boxes, each with declarations of its own and an empty prelude:

`@top-left-corner`, `@top-left`, `@top-center`, `@top-right`, `@top-right-corner`, `@bottom-left-corner`,
`@bottom-left`, `@bottom-center`, `@bottom-right`, `@bottom-right-corner`, `@left-top`, `@left-middle`,
`@left-bottom`, `@right-top`, `@right-middle`, `@right-bottom`.

```css
@page :first {
  size: A4;
  margin: 2cm 2cm 3cm;

  @bottom-center {
    content: counter(page) " / " counter(pages);
  }
}
```

`@page` is allowed inside `@media` and `@supports`.

## `@font-face`

The prelude must be empty. The block holds the descriptors `font-family`, `src`, `font-style`, `font-weight`,
`font-stretch`, `font-display`, `unicode-range`, `font-feature-settings`, `font-variation-settings`,
`ascent-override`, `descent-override`, `line-gap-override` and `size-adjust`. When a descriptor is written twice, the
last one stays.

Each source of `src` is either `local()` with a name, or a URL followed by optional `format()` and `tech()`. A URL goes
through the rewriter with `UrlKind::Font`; a source refused, or of another form, is removed (`Url`, `FontSource`) and
the others stay. A rule left without `font-family` or without a source is removed (`FontSource`).

```css
@font-face {
  font-family: "Body";
  src: local("Body Regular"), url(body.woff2) format("woff2");
  font-display: swap;
}
```

## `@import`

Allowed only by `withAtRules(AtRuleKind::Import)`, and only at the start of the style sheet: an `@import` after any
other rule, inside a group rule or with a block is removed (`Import`). Its prelude is a URL, as a string or a `url()`,
then an optional `supports()` condition holding no URL, then media queries as `@media` reads them; a layer is not
allowed. A prelude of another form is removed (`Prelude`). The URL goes through the rewriter with
`UrlKind::StyleSheet`; refused, it removes the rule (`Url`). The kept rules are written first, as `Sheet\ImportRule`
values in `StyleSheet::$imports`.

```css
@import "theme.css" supports(display: grid) print;
/* @import url("/media/42/theme.css") supports(display: grid) print; */
```

## Rules nested in a style rule

A style rule may hold, beside its declarations, style rules whose selectors are relative to it
([Selectors](selectors.md)) and `@media` and `@supports` rules, whose preludes are checked as at the top level and whose
blocks hold declarations for the parent's elements and nested rules of their own. Any other at-rule there is removed
(`AtRule`). Each nested rule or group counts as a level; past the policy's `maxNesting`, three by default, it is
removed (`Nesting`). Nested rules are checked as CSS Nesting reads a block: an item that starts with a name is a
declaration unless it has no colon, or a `{}` block beside other values, as in `a:hover { … }`.

```css
.card {
  color: #333;

  & h2 {
    margin: 0;
  }

  @media print {
    color: black;
  }
}
```

The declarations of a rule are written before its nested rules, as browsers first read nested CSS.
