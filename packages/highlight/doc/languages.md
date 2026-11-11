# Languages

Every language is asked for by its name or one of its aliases, in any case: `Highlighter::default()->toHtml($code,
'TS')` reads TypeScript.

| Language | Name | Aliases | Tokenizer |
|---|---|---|---|
| Bash and other shells | `bash` | `sh`, `shell`, `zsh`, `ksh` | `Code` with `Definitions::bash()` |
| C | `c` | `h` | `Code` with `Definitions::c()` |
| C++ | `cpp` | `c++`, `cc`, `cxx`, `hpp`, `hh`, `hxx` | `Code` with `Definitions::cpp()` |
| C# | `csharp` | `cs`, `c#`, `dotnet` | `Code` with `Definitions::csharp()` |
| CSS | `css` | — | `StyleSheet` |
| Diffs and patches | `diff` | `patch`, `udiff` | `Diff` |
| Dockerfile | `dockerfile` | `docker`, `containerfile` | `Dockerfile`, arguments read as shell |
| Go | `go` | `golang` | `Code` with `Definitions::go()` |
| HTML | `html` | `htm`, `xhtml`, `vue`, `svelte` | `Markup`, scripts as JavaScript and styles as CSS |
| INI, `.env`, properties | `ini` | `dosini`, `cfg`, `conf`, `properties`, `env`, `dotenv`, `editorconfig`, `gitconfig` | `Ini` |
| Java | `java` | `jsp` | `Code` with `Definitions::java()` |
| JavaScript | `javascript` | `js`, `mjs`, `cjs`, `jsx`, `node` | `Code` with `Definitions::javascript()` |
| JSON | `json` | `jsonc`, `json5`, `geojson`, `webmanifest` | `Code` with `Definitions::json()` |
| Markdown | `markdown` | `md`, `mkd`, `mdx`, `commonmark`, `gfm` | `Markdown` |
| PHP | `php` | `php8`, `phtml` | `Php`: code with `Definitions::php()`, markup as HTML |
| Python | `python` | `py`, `py3`, `python3`, `gyp` | `Code` with `Definitions::python()` |
| Ruby | `ruby` | `rb`, `gemspec`, `podspec`, `rake` | `Code` with `Definitions::ruby()` |
| Rust | `rust` | `rs` | `Code` with `Definitions::rust()` |
| SCSS | `scss` | `less`, `sass` | `StyleSheet(scss: true)` |
| SQL | `sql` | `mysql`, `postgresql`, `postgres`, `pgsql`, `sqlite`, `plsql`, `tsql`, `mariadb` | `Code` with `Definitions::sql()` |
| Twig | `twig` | `html.twig`, `jinja`, `jinja2`, `nunjucks`, `njk`, `liquid` | `Twig`: expressions by `Definitions::twigExpression()`, text as HTML |
| TypeScript | `typescript` | `ts`, `tsx`, `mts`, `cts` | `Code` with `Definitions::typescript()` |
| XML | `xml` | `svg`, `xsl`, `xslt`, `xsd`, `rss`, `atom`, `plist`, `wsdl`, `xaml`, `mathml` | `Markup` |
| YAML | `yaml` | `yml` | `Yaml` |

## Programming languages

`Code` reads a source once and, at each position, tries in order: spaces, meta marks, a preprocessor line, comments,
quoted texts, variables, annotations, symbols, numbers, words, regular expressions, operators and punctuation.

| What | Type | Languages |
|---|---|---|
| Reserved words | `keyword` | All; SQL in any case |
| Type names, and names that start with a capital | `type` | All with a list; capitals in PHP, JavaScript, TypeScript, Python, Ruby, Rust, Java, C# |
| `true`, `null`, `None`, `nil`… | `constant` | All |
| Functions the language provides that are often called without parentheses | `builtin` | Shell (`echo`, `cd`…), Python, Ruby, Go, SQL, JavaScript (`console`) |
| A name followed by `(`, a Rust macro (`vec!`), a Twig filter (`|upper`) | `function` | All |
| `$var`, `${var}`, `$?`, `@var`, `@@var` | `variable` | PHP, shell, Ruby, SQL |
| Quoted texts, with their prefixes (`f"…"`, `b"…"`, `u8"…"`, `$"…"`, `@"…"`) and character literals | `string` | All; a Rust lifetime (`'a`) is not a character |
| JSON keys | `property` | JSON |
| Numbers: `0x1F`, `1_000u32`, `.5`, `1e-3` | `number` | All |
| Comments: line, block | `comment` | All |
| Regular expression literals where a value is expected | `regex` | JavaScript, TypeScript, Ruby |
| Decorators and annotations (`@Override`), PHP and Rust attributes (`#[…]`), PHP tags, preprocessor lines, shebangs | `meta` | Python, JavaScript, TypeScript, Java; PHP, Rust; C, C++, C#; shell, Python, Ruby, JavaScript |
| Ruby symbols (`:name`) | `constant` | Ruby |

A Ruby method name may end with `?` or `!`, as `empty?`. A quote that may span lines does: JavaScript template
literals, Python triple quotes, Java and C# text blocks, Go raw strings, shell and SQL strings. A doubled quote in SQL
(`'it''s'`) reads as one string.

## Markup

`Markup` reads tags, attributes and their values, comments, character references (`&amp;`, `&#38;`), CDATA sections,
doctypes and processing instructions. In HTML the content of `<script>` is read as JavaScript and that of `<style>` as
CSS, up to the end tag in any case.

## Style sheets

`StyleSheet` reads each statement as an at-rule (`@media`, a `keyword`), a selector or a declaration: at the top level
a statement is a selector; inside a block it is a nested selector when a `{` comes before the next `;` or `}`, and a
declaration otherwise. A property is a `property`, a custom property (`--x`) a `variable`; in a value, numbers with
their units and colours (`#fff`) are `number`, functions `function`, `!important` a `keyword`, and the address in
`url()`, quoted or not, a `string`. SCSS adds `$variables`, `#{…}` interpolations and line comments.

## Line languages

| Language | What it colours |
|---|---|
| YAML | Comments, `---` and `...`, sequence dashes, keys plain or quoted, quoted and plain scalars — a constant (`true`, `yes`, `on`, `null`, `~`…), a number or a string —, anchors and aliases, tags, flow collections, and the lines of block scalars (`|`, `>`) |
| INI | Comments opened by `;` or `#`, `[sections]`, keys, `=` and `:`, values — quoted, numbers, constants or strings —, and a comment after a value and a space |
| Dockerfile | Comments, the instruction that opens a line in any case, and its arguments, continued by a backslash, read as shell |
| Diff | File headers (`diff`, `index`, `---`, `+++`), hunk headers (`@@`), lines added (`+`, `>`) and removed (`-`, `<`), and `\ No newline` notes |
| Markdown | Fenced code blocks, headings, thematic breaks, quote and list marks, task boxes; code spans, strong emphasis, emphasis, links, images and autolinks closed on their line |

## Mixed languages

`Php` reads a source with no opening tag as code; otherwise markup up to each `<?php` or `<?=`, and code up to the
next `?>`. `Twig` reads comments, the delimiters of prints and tags with their whitespace controls, the expressions
between them, and the text around them as HTML. Each stretch of markup is read on its own: a tag cut by a Twig
expression — `<a href="{{ url }}">` — loses some colours after the cut.

## What is left alone

The tokenizers colour what a reader needs; they do not parse. Heredocs and nowdocs, nested block comments, Rust raw
strings with `#` delimiters, C++ raw strings, and expressions interpolated inside strings take the colour of what
holds them. Markdown inline marks must close on their line. A source of one language read as another never fails: it
comes back with fewer colours.
