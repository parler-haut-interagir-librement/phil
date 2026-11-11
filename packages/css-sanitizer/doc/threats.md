# Threats

What CSS written by someone else can do to a page, and what the sanitizer does about it.

| Threat | How CSS does it | What the sanitizer does |
|---|---|---|
| Running code | `expression()`, `behavior`, `-moz-binding`, `javascript:` URLs | Always refused, whatever the policy; names are read after their escapes are resolved, so `e\78 pression()` is `expression()` |
| Loading another style sheet | `@import` | Refused until the policy allows `AtRuleKind::Import`; then only at the start of the sheet, its URL a style sheet the rewriter allows, with `UrlKind::StyleSheet`, never a fragment |
| Loading from another site, or tracking the reader | `url()` in backgrounds, list markers, fonts, cursors; the images of `image-set()`, quoted or not, with `type()` beside them | Every URL goes through the rewriter, an `image-set()` string as any `url()`; `type()` takes a string only and loads nothing; the default refuses all; `AllowedFiles` keeps them on the project's files |
| Reading the page | Attribute selectors on field values with a background per letter; `@font-face` with `unicode-range` per character; `:visited`, `:has()`, `:checked` | Attribute selectors limited to `lang` and `dir`; those pseudo-classes not allowed; and no URL leaves the application's files |
| Covering the application's page | `position: fixed` with a high `z-index`, a box laid over buttons or forms | `position`, offsets and `z-index` not allowed by default; a scope keeps rules inside the user's part of the page |
| Leaving the scope through nesting | `:not(&)` or `:is(:not(&))` in a nested rule, which matches every element its parent does not; `&` at the top level, where it means the root | `&` is refused inside the arguments of `:not()`, `:is()` and `:where()` and at the top level; elsewhere it stands for the parent, already scoped, and the selectors that hold it reach only that element, its descendants and its siblings |
| Leaving the `<style>` element | `</style><script>` in a string or a name | The output never holds `</` followed by a letter: `<` is escaped in names and strings, and kept apart from `/` |
| Wearing out the server | Megabytes of CSS, thousands of nested parentheses, rules nested in rules, hundreds of thousands of refused rules | A limit of length and a limit of depth, each refusing the source whole; a limit of nesting levels, past which nested rules are removed; the parser is linear, a block of many nested rules included, and its recursion bounded; the first 100 removals listed and the others counted, so that `SafeCss` gives at most 101 violations |
| Smuggling through a parser difference | Comments inside names, escapes, unclosed strings and URLs, stray braces | The tokenizer and parser follow CSS Syntax Level 3, so the sanitizer reads what the browser reads, and the output is written again from the tokens kept |

## What stays the application's

- **The rewriter.** It decides which files a style sheet may load. A rewriter that returns any URL it is given lets
  the style sheet load from anywhere, and so lets it report on the reader.
- **The style sheets `@import` loads.** They are not sanitized by this pass: allow `AtRuleKind::Import` only with a
  rewriter that returns style sheets the application wrote or sanitized itself, for `UrlKind::StyleSheet`.
- **The scope.** Without one, a user's rules apply to the whole page they are written in. Give a scope wherever user
  styles share a page with the application's own interface, and write them inside an element that carries it.
- **Escaping for attributes.** The output of `sanitizeDeclarations()` is CSS, not HTML: escape it as any attribute
  value.
- **The policy.** Each property or pseudo-class added widens what the CSS can do; `position`, `z-index` and attribute
  selectors in particular should only be allowed where the page is the user's alone, such as a document sent to a PDF
  engine.
