# Selectors

Each selector of a style rule is checked on its own; a selector refused is listed (`Selector`) and the rule keeps the
others. A rule left without selectors is dropped.

## The grammar checked

A complex selector is a list of compound selectors joined by white space, `>`, `+` or `~`. A compound selector is,
in order:

1. an optional type selector (`p`) or `*`;
2. any number of ID selectors (`#main`), class selectors (`.note`), attribute selectors and pseudo-classes;
3. any number of pseudo-elements, which end the complex selector.

| Part | Checked |
|---|---|
| ID | A hash that is an identifier: `#1a` is refused |
| Attribute | `[name]`, `[name=value]`, `[name~=value]`, `[name\|=value]`, `[name^=value]`, `[name$=value]`, `[name*=value]`, a value as an ident or a string, an optional `i` or `s`; the name in the policy's attributes |
| Pseudo-class | A name in the policy; `:not()`, `:is()` and `:where()` take selector lists without pseudo-elements; `:nth-child()`, `:nth-last-child()`, `:nth-of-type()` and `:nth-last-of-type()` take An+B; `:lang()` takes names and strings; `:dir()` takes `ltr` or `rtl` |
| Pseudo-element | A name in the policy, with two colons, or one for `before`, `after`, `first-line` and `first-letter`; no function |

Refused whatever the policy: namespaces (`ns|a`, `*|a`), the nesting selector `&` outside a nested rule, the column
combinator `||`, functional pseudo-elements (`::part()`), `:nth-child(… of S)`, and a functional pseudo-class whose
arguments the check cannot read (`:has()`, `:nth-col()`), even if named in the policy.

## Nested selectors

The selector of a rule nested in a style rule is relative to its parent. It passes the same grammar, with two
additions: it may start with a combinator (`> p`, `+ h2`), and any of its compounds may hold `&`, the parent, after an
optional type selector — `&:hover`, `p&.note`, `.dark &`, `& + &`. A selector without `&` applies to the parent's
descendants, or to its siblings after a leading `+` or `~`.

```css
article {
  & h2 { margin: 0 }
  > p { text-indent: 1em }
  &:hover { color: #702 }
}
```

`&` is refused inside the arguments of `:not()`, `:is()` and `:where()`: `:not(&)` matches every element the parent
does not, and so the whole page outside the policy's scope. Elsewhere, `&` stands for an element the scope already
holds, and a selector that holds it reaches only that element, its descendants and its siblings, inside the scope.
`&` at the top level of a style sheet, where it means the root, is refused. Nested rules are never prefixed with the
scope.

## Why attributes are limited

An attribute selector can test the value of a field, letter by letter, and a background loaded for each match tells a
server what was typed. The default policy allows `lang` and `dir` only. Allowing more — `withAttributes('data-*')` —
is safe as long as the URL rewriter keeps every URL on the application's own files.
