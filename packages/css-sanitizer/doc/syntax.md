# Syntax

The tokenizer, the parser and the serializer are public and can be used without the sanitizer: to read CSS as a
browser does, to check its structure, or to write it back.

## Tokenizer

`Syntax\Tokenizer::tokenize()` follows CSS Syntax Level 3, section 4. The input is preprocessed — CR LF, CR and FF become
LF, NULL becomes U+FFFD — and bytes that are not UTF-8 become U+FFFD. It returns a list of `Token`, the last one of
type `Eof`. Comments are dropped.

```php
use Phil\CssSanitizer\Syntax\Tokenizer;

foreach (new Tokenizer()->tokenize('a { width: 10px }') as $token) {
    echo $token->type->value, ' ';
}
// ident whitespace { whitespace ident colon whitespace dimension whitespace } EOF
```

A `Token` has a `type` (`TokenType`), a `value` with its escapes resolved — the name of an ident, function, at-keyword
or hash, the text of a string or URL, the character of a delim, the representation of a number as written —, the
`unit` of a dimension, `isId` for a hash that could be an identifier, `isInteger` for a number, and its
`sourceLine`.

## Parser

`Syntax\Parser` follows section 5:

| Method | Returns |
|---|---|
| `parseStylesheet($css)` | A `Stylesheet` of `QualifiedRule`, `AtRule` and `Malformed` |
| `parseDeclarationList($css)` | `Declaration`, `AtRule` and `Malformed`, as in a `style` attribute |
| `parseComponentValues($css)` | Component values: `Token`, `SimpleBlock`, `FunctionValue` |
| `rules($values, $topLevel)` | The rules of component values, such as a block's |
| `declarations($values)` | The declarations of component values, such as a block's |
| `blockContents($values)` | `Declaration`, `AtRule`, `QualifiedRule` and `Malformed`, as CSS Nesting reads a style rule's block |

A rule's block keeps its component values; whether they hold rules or declarations depends on the rule, so the
caller reads them with `rules()`, `declarations()` or `blockContents()`. `blockContents()` reads an item that starts
with a name as a declaration unless it has no colon, or a `{}` block beside other values in a property that is not
custom; then it reads a nested rule, whose prelude ends at its block or, as a parse error, at a semicolon. It reads a
block in time linear in its length. What the specification drops as a parse error — a declaration
without its colon, a rule cut by the end of the source — is kept as `Malformed`, with its content and line.

`new Parser(maxDepth: 64)` bounds the nesting of blocks and functions; deeper, it throws `NestingTooDeep`. The static
`Parser::trim()` takes white space off the ends of component values, and `Parser::splitAtCommas()` cuts them at the
commas of their top level.

## Serializer

`Serializer` writes CSS back:

| Method | Writes |
|---|---|
| `componentValues($values)` | Component values |
| `declarations($declarations)` | `name: value; …`, as a `style` attribute holds them |
| `styleSheet($sheet)` | A `Sheet\StyleSheet`: its `@import` rules, then one rule after the other, two spaces of indentation per level, a rule's declarations before its nested rules |
| `Serializer::identifier($name)` | A name, escaped as CSSOM serializes identifiers |
| `Serializer::string($text)` | A string between double quotes |

Numbers keep the representation they were written with. An empty comment goes between two tokens that would read
back as other tokens — an ident and a number, a number and a unit-like ident —, as section 9 requires, except where a
signed number cannot join what comes before it, so that `U+0000-00FF` stays as written. Reading the output again gives
the same values. A `<` in a name or a string is written `\3c `, and a `<` delim is kept apart from a `/` that follows
it: the output never starts an end tag.
