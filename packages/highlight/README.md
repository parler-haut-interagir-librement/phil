# phil/highlight

**Code in 24 languages coloured on the server, as plain HTML spans any stylesheet can theme.**

phil/highlight turns source code into HTML for `<pre><code>`: each piece the reader should tell apart — a keyword, a
string, a comment, a number, a tag, a key — is wrapped in a `<span class="hl-…">`, and everything is escaped. It
reads PHP, JavaScript, TypeScript, JSON, HTML, XML, CSS, SCSS, SQL, YAML, Twig, Markdown, shell, Python, Ruby, Go,
Rust, Java, C, C++, C#, INI, Dockerfiles and diffs, each by a small tokenizer that never throws and never loses a
byte, whatever it is given.

It is for any PHP application that shows code: documentation, a blog, a forum, a wiki, a book laid out as HTML or
PDF. Colouring on the server sends the page ready to read, with no script and no flash of plain code, and works in a
feed reader, an e-mail or a PDF. The same `hl-` classes are written in the browser by the highlight controller of
`phil/ux-bundle`, so one theme serves both. The `phil/ujo` renderer takes the highlighter for the code blocks of a
document.

It is a plain PHP library, written from scratch, without dependency. Each tokenizer reads the source once, from left to
right, byte by byte with `strspn` and `strcspn`, and keeps no regular expression on its hot path; hostile sources —
a hundred thousand open quotes or brackets — are read in linear time. It colours what a reader needs and no more: it
is not a parser, does not check syntax and does not know every corner of every grammar.

The design is small: a `Language` turns a source into `Token`s, each with a `TokenType`; `Code` is the tokenizer of a
programming language described by a `Syntax`, and `Definitions` has the ones shipped; markup, style sheets, YAML,
INI, Dockerfiles, diffs, Markdown, Twig and PHP's mix of markup and code have their own; `HtmlFormatter` writes the
spans; `Highlighter` finds a language by name or alias and puts them together. The themes are CSS files.

## Status

| Part | State |
|---|---|
| `Highlighter`: languages by name and alias in any case, added or replaced; HTML or tokens | Implemented and tested |
| Programming languages by `Syntax`: PHP, JavaScript, TypeScript, JSON, SQL, shell, Python, Ruby, Go, Rust, Java, C, C++, C# | Implemented and tested |
| HTML with its scripts and styles, XML, CSS, SCSS, YAML, INI, Dockerfile, diff, Markdown, Twig, PHP with markup | Implemented and tested |
| Lines: numbers, highlighted ranges, annotations in comments | Implemented and tested |
| Themes: light, dark, and automatic by the system or `data-theme` | Implemented |
| Linear time on hostile sources; nothing lost on any source | Implemented and tested |
| Heredocs, nested comments, raw strings with delimiters, string interpolation coloured inside strings | Not read: they take the colour of what holds them |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the themes. |
| [Languages](doc/languages.md) | Every language, its names, what each colours and what it leaves alone. |
| [HTML and themes](doc/html-and-themes.md) | The token types, their classes, the HTML written, and the themes. |
| [Lines](doc/lines.md) | A block line by line: numbers, highlighted ranges, and the `[!code ++]`, `[!code --]` and `[!code highlight]` annotations. |
| [Adding a language](doc/extending.md) | A `Syntax` of your own, or a `Language` written by hand, and replacing a shipped one. |
| [UJO documents](doc/ujo.md) | Colouring the code blocks of `phil/ujo` documents, and keeping the colours through a purifier. |
| [Exceptions](doc/exceptions.md) | `HighlightException` and its three exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that highlights. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/highlight
```

```php
use Phil\Highlight\Highlighter;

$highlighter = Highlighter::default();

echo '<pre><code class="language-php">'.$highlighter->toHtml('echo "Hi";', 'php').'</code></pre>';
// <pre><code class="language-php"><span class="hl-keyword">echo</span> <span class="hl-string">&quot;Hi&quot;</span><span class="hl-punctuation">;</span></code></pre>

$highlighter->highlight('x', 'cobol'); // null: a language it does not know
```

```html
<link rel="stylesheet" href="/vendor/phil/highlight/themes/auto.css">
```

## Requirements

PHP 8.5 or later. Nothing else.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
