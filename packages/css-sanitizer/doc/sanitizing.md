# Sanitizing

## Style sheets and `style` attributes

`CssSanitizer::sanitize()` reads a style sheet — the content of a `<style>` element or a `.css` file. Its rules are
style rules and at-rules.

`CssSanitizer::sanitizeDeclarations()` reads the declarations of a `style` attribute, without braces. At-rules found
there are removed.

```php
$sheet = $sanitizer->sanitize('h1 { color: red; position: fixed }');
$sheet->css;        // "h1 {\n  color: red;\n}\n"

$inline = $sanitizer->sanitizeDeclarations('color: red; position: fixed');
$inline->css;       // 'color: red'
```

Both return a `Sanitized`. Neither throws on what it reads: anything the policy does not allow is removed and listed.

## The result

| Member | What it holds |
|---|---|
| `css` | The CSS to write in the page: two spaces of indentation per level for a style sheet, `name: value; …` for declarations |
| `removals` | The `Removal`s, in the order of the source, up to `Sanitized::MAX_REMOVALS` (100) |
| `omittedRemovals` | The removals past the first 100, counted but not listed |
| `isClean()` | Whether nothing was removed |
| `sheet` | The kept rules of a style sheet: `Sheet\StyleSheet` of `Sheet\ImportRule`, `Sheet\StyleRule` and `Sheet\GroupRule` |
| `declarations` | The kept `Syntax\Declaration`s of a `style` attribute |

A rule left without declarations is dropped without a removal of its own: its declarations were listed. So is a group
rule left empty.

## Removals

A source of a megabyte can hold hundreds of thousands of refused rules: the first 100 removals are listed, and
`omittedRemovals` counts the others, so that neither the result nor a form that shows it grows with them.

Each `Removal` has a `reason`, a `subject` — the property name, the selector, `@import`, the function name with `()`,
the URL, cut to 80 code points — and the `line` of the source where it starts.

| `RemovalReason` | Removed |
|---|---|
| `Import` | An `@import` rule the policy does not allow, or one that does not open the style sheet |
| `AtRule` | An at-rule the policy does not allow, or one where it cannot stand (other than `@media` and `@supports` inside a style rule, in a margin box) |
| `Prelude` | The whole at-rule whose media query, condition or page selector is not allowed |
| `Selector` | One selector of a list; the rule keeps the others |
| `Property` | A declaration whose property the policy does not allow, or a custom property when they are not allowed |
| `DeniedProperty` | A declaration of `behavior`, `-ms-behavior` or `-moz-binding`, always |
| `Function` | A declaration holding a function the policy does not allow |
| `Url` | A declaration holding a URL the rewriter refused; in `@font-face`, that source only; an `@import` rule |
| `DangerousUrl` | A declaration holding a `javascript:`, `vbscript:`, `livescript:` or `mocha:` URL |
| `Value` | A declaration whose value holds a token no allowed value holds, or that is empty |
| `Important` | The `!important` flag of a declaration, when the policy does not keep it; the declaration stays |
| `Syntax` | What CSS itself drops: a declaration without a colon, text that is neither a declaration nor an at-rule, a rule cut before its block, an at-rule without the block it needs |
| `FontSource` | An `@font-face` source of a form it cannot use; an `@font-face` rule left without a family or a source |
| `TooLong` | The whole source, longer than the policy's limit |
| `TooDeep` | The whole source, whose blocks and functions nest deeper than the policy's limit |
| `Nesting` | A rule nested in a style rule beyond the policy's levels of nesting, with what it holds |

`RemovalReason::describe()` gives a few words for a message: `an @import rule`, `a property that runs code`.

## What a value may hold

Names, numbers, dimensions, percentages, strings, hashes, commas, the delims `/ + - *`, parentheses and brackets, and
the functions the policy allows, their arguments checked the same way. A colon, a semicolon, an at-keyword, a `{}`
block, any other delim, a bad string or a bad URL refuses the declaration. `url()`, quoted or not, and each image of
`image-set()` go through the rewriter ([URLs](urls.md)). `var()` is allowed only with custom properties.

`attr()` takes an attribute name and type names. The type `url` is allowed only when `attr()` is the first argument of
`target-counter()`, `target-counters()` or `target-text()`: there it names the element a link points to, and loads
nothing.

```css
a::after { content: " (page " target-counter(attr(href url), page) ")" }   /* kept */
a::after { content: attr(href url) }                                       /* removed */
```

## Output

The output is written by the [serializer](syntax.md): names are escaped, strings use double quotes, URLs are written
`url("…")`, white space is one space. It never holds `</` followed by a letter, so that it cannot close the `<style>`
element it is written in. In a `style` attribute, escape it as any attribute value — Twig does it by default.
