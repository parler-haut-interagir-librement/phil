# Languages

The highlighter reads the languages `phil/highlight` ships — their names and aliases are listed in that library's
documentation — and those the application adds.

## The application's own languages

A service implementing `Phil\Highlight\Language` is added to the highlighter: autoconfiguration tags it
`phil_highlight.language`, and a service registered without autoconfiguration takes the tag by hand.

```php
use Phil\Highlight\Language;

final readonly class GherkinLanguage implements Language
{
    public function name(): string { return 'gherkin'; }
    public function aliases(): array { return ['feature']; }
    public function tokenize(string $code): array { /* … */ }
}
```

A language takes its name and its aliases from any language that had them: a service named `php` replaces the PHP of
the library. The tagged languages are added in the order their services are registered, so of two languages with the
same name, the one registered later is kept. Writing a tokenizer, or describing a programming language by a `Syntax`,
is explained by `phil/highlight`'s guide to adding a language.

## More names

`aliases` gives a language more names, each to the name or an alias of the language it stands for. A code block
fenced as `vue` in Markdown, or written with `vue` as its language in a block editor, is then coloured as HTML:

```yaml
phil_highlight:
    aliases:
        vue: html
        jsonc: json
        feature: gherkin    # a language of the application
```

The aliases are added after the tagged languages, so they may name one, and each takes its name from any language that
had it. The `language-…` class of the block keeps the name the writer used.
