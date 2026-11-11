# Adding a language

`Highlighter::with()` adds a language under its name and aliases, or replaces the language that had them. Names are
lower-case letters, digits and `_ + # . -`, 32 at most; anything else throws `InvalidLanguage`.

## A programming language: a `Syntax`

Most programming languages are a `Syntax` read by `Code`:

```php
use Phil\Highlight\Highlighter;
use Phil\Highlight\Language\Code;
use Phil\Highlight\Language\Quote;
use Phil\Highlight\Language\Syntax;

$lua = new Code(new Syntax(
    'lua',
    aliases: ['luau'],
    keywords: ['and', 'break', 'do', 'else', 'elseif', 'end', 'for', 'function', 'if', 'in', 'local', 'not', 'or', 'repeat', 'return', 'then', 'until', 'while'],
    constants: ['true', 'false', 'nil'],
    builtins: ['print', 'pairs', 'ipairs', 'require'],
    lineComments: ['--'],
    blockComments: ['--[[' => ']]'],
    quotes: [new Quote('[[', ']]', escape: '', multiline: true), new Quote('"'), new Quote("'")],
));

$highlighter = Highlighter::default()->with($lua);
```

| Field | What it says |
|---|---|
| `name`, `aliases` | The names the language is asked for by, in lower case |
| `keywords`, `types`, `constants`, `builtins` | Word lists; a word in two lists takes the first of keyword, constant, type, built-in |
| `lineComments` | Marks that open a comment to the end of the line |
| `blockComments` | Each opening mark, to its closing one |
| `quotes` | `Quote`s, tried in order — the longer marks first: opening and closing marks, escape character (empty for none), whether it spans lines, whether it holds one character |
| `stringPrefixes` | Letters or signs that may stand before a quote: `rbfu` in Python |
| `variablePrefixes`, `specialVariables` | Sigils (`$`), and the characters that make a one-character variable after one (`?` in `$?`) |
| `annotation` | The sign of decorators and annotations: `@` |
| `metaMarks` | Marks read as meta wherever they stand, before comments: `#[`, `<?php` |
| `preprocessor` | Whether `#` opening a line opens a preprocessor line, continued by a backslash |
| `caseInsensitive` | Whether words are matched in any case |
| `capitalizedTypes` | Whether a name that starts with a capital is a type |
| `regex` | Whether `/` opens a regular expression where a value is expected |
| `propertyKeys` | Whether a quoted text followed by `:` is a key |
| `shebang` | Whether a first line opening with `#!` is meta |
| `commentAfterSpace` | Whether a line comment opens only at the start of a word, as `#` in shell |
| `symbols` | Whether `:` before a word makes a symbol |
| `macros` | Whether a word followed by `!` is a macro call |
| `callAfter` | Signs after which a word is a call: `|` before a Twig filter |
| `wordChars`, `wordSuffixes` | Characters a word may hold (`$` in JavaScript), and that may end it (`?` and `!` in Ruby) |
| `operators`, `punctuation` | The characters of each |

## Any other language: a `Language`

A language that is not a programming language implements `Language`: `name()`, `aliases()` and `tokenize()`, which
returns `Token`s whose texts, joined, are the source — never throwing, whatever the source. The shipped `Ini` and
`Diff` are short models. A tokenizer that mixes languages takes the others in its constructor, as `Dockerfile` takes
the shell and `Twig` the markup and expression languages.

```php
use Phil\Highlight\Language;
use Phil\Highlight\Token;
use Phil\Highlight\TokenType;

final readonly class Csv implements Language
{
    public function name(): string { return 'csv'; }

    public function aliases(): array { return ['tsv']; }

    public function tokenize(string $code): array
    {
        $tokens = [];

        foreach (preg_split('/([,\t\n])/', $code, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if ('' !== $part) {
                $tokens[] = new Token($part, str_contains(",\t\n", $part) ? TokenType::Punctuation : TokenType::String);
            }
        }

        return $tokens;
    }
}
```

## Replacing a shipped language

A language added under a shipped name replaces it, aliases included when it claims them: `with(new Code(new
Syntax('sql', aliases: ['postgresql'], …)))` reads SQL and PostgreSQL its way, while the other SQL aliases keep the
shipped tokenizer.
