# phil/css-sanitizer

**Let people style their pages without letting their CSS run code, load what it should not, or leave its frame.**

phil/css-sanitizer reads CSS written by people — a style sheet, or the declarations of a `style` attribute — as the
CSS Syntax Level 3 specification reads it, keeps only what a policy allows, and writes it back. Properties, functions,
selectors and at-rules come from allow lists; `@media`, `@supports`, `@page` with its sixteen margin boxes,
`@font-face` and rules nested CSS Nesting's way are understood and checked; every URL — of `url()`, of `image-set()`,
of an `@import` the policy allows — goes through a rewriter that maps it to a file the application allows, or refuses
it. `expression()`, `javascript:` URLs, `behavior` and `-moz-binding` are refused whatever the policy says. What is
removed is listed, with its reason and line, for the author to read.

It is for any PHP application that lets its users write CSS: themes and looks of a published book or page, custom
styles of a profile, print styles of a document sent to a PDF engine, a style editor in a content system. The output
is safe to write in a `<style>` element or a `style` attribute of a page that other people read.

It is a plain PHP library whose only requirement is `ext-mbstring`; `symfony/validator`, if
installed, gives the `SafeCss` constraint. The tokenizer and parser follow CSS Syntax Level 3 section by section, so
that the sanitizer sees the same tokens a browser sees — escapes resolved, comments dropped, blocks matched — and a
name hidden behind an escape (`e\78 pression`) is the name it is. Nothing is filtered by regular expression on text.

The design: `Syntax\Tokenizer` and `Syntax\Parser` turn text into component values, rules and declarations;
`CssSanitizer` checks them against a `Policy`, rewrites their URLs through a `UrlRewriter`, and returns a `Sanitized`
result — the CSS, the kept rules as a `Sheet\StyleSheet`, and the `Removal`s; `Serializer` writes CSS back so that it
reads again as the same values and never holds the start of an end tag. `Validator\SafeCss` turns removals into
violations.

## Status

| Part | State |
|---|---|
| Tokenizer of CSS Syntax Level 3: every token, escapes, comments, bad strings and bad URLs, lines | Implemented and tested |
| Parser of CSS Syntax Level 3: component values, rules, declarations, `!important`, parse errors kept, depth bounded | Implemented and tested |
| Serializer: identifiers and strings escaped as CSSOM does, comments where tokens would join, no end tag | Implemented and tested |
| Allow lists of properties, functions, pseudo-classes, pseudo-elements, attributes and media types | Implemented and tested |
| Selectors: type, class, ID, attribute, pseudo-classes with their arguments, pseudo-elements, combinators | Implemented and tested |
| Nested style rules (CSS Nesting): relative selectors and `&` under the same grammar, levels bounded by the policy, `@media` and `@supports` inside | Implemented and tested |
| `@media`, `@supports`, `@page` and its margin boxes, `@font-face` with local and rewritten sources | Implemented and tested |
| URLs rewritten through a callback or a map of allowed files; fragments kept; code-running schemes refused | Implemented and tested |
| `image-set()` with each image rewritten; `@import` at the start of a sheet, rewritten to an allowed style sheet when the policy allows it | Implemented and tested |
| `expression()`, `behavior`, `-moz-binding` and resource-loading functions always refused | Implemented and tested |
| Scope selector, `!important` and custom properties under the policy, limits of length and depth | Implemented and tested |
| Symfony validator constraint `SafeCss` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the sanitizer as a Symfony service. |
| [Sanitizing](doc/sanitizing.md) | Style sheets and `style` attributes, the result, the removals and their reasons. |
| [Policy](doc/policy.md) | What the default policy allows, every `with…()`, the scope, nesting and the limits. |
| [URLs](doc/urls.md) | Rewriters, allowed files, `image-set()`, `@import`, fragments, the schemes always refused. |
| [Selectors](doc/selectors.md) | The selector grammar checked, nested selectors and `&`, and what the policy decides in it. |
| [At-rules](doc/at-rules.md) | `@media`, `@supports`, `@page` and its margin boxes, `@font-face`, `@import`, rules nested in style rules. |
| [Threats](doc/threats.md) | What the sanitizer protects from, how, and what stays the application's. |
| [Syntax](doc/syntax.md) | The tokenizer, the parser and the serializer used on their own. |
| [Validator](doc/validator.md) | The `SafeCss` constraint. |
| [Exceptions](doc/exceptions.md) | `CssSanitizerException` and its two exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that sanitizes CSS. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/css-sanitizer
```

```php
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Url\AllowedFiles;

$policy = new Policy()
    ->withUrlRewriter(new AllowedFiles(['paper.jpg' => '/media/42/paper.jpg']))
    ->withScope('.book-page');

$sanitized = new CssSanitizer($policy)->sanitize(<<<'CSS'
    @import url(https://example.com/x.css);
    h1 { color: #702; background: url(paper.jpg); behavior: url(x.htc) }
    @page :first { margin-top: 4cm; @top-center { content: string(title) } }
    CSS);

echo $sanitized->css;
// .book-page h1 {
//   color: #702;
//   background: url("/media/42/paper.jpg");
// }
//
// @page :first {
//   margin-top: 4cm;
//
//   @top-center {
//     content: string(title);
//   }
// }

foreach ($sanitized->removals as $removal) {
    printf("line %d: %s (%s)\n", $removal->line, $removal->reason->describe(), $removal->subject);
}
// line 1: an @import rule (@import)
// line 2: a property that runs code (behavior)
```

## Requirements

PHP 8.5 or later and `ext-mbstring`; `symfony/validator` for the `SafeCss` constraint.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
