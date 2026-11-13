# phil/latex-mathml

**LaTeX formulas turned into the MathML that browsers draw, on the server, with nothing to install.**

phil/latex-mathml converts a formula written in LaTeX into MathML Core: identifiers, numbers and operators, scripts
and limits, fractions and binomials, roots, accents, fonts, stretched delimiters, matrices, cases and aligned
equations, simple macros, colours, links, boxes, equation numbers, rules in tables, and chemical notation. The formula
is kept beside the MathML as a TeX annotation, so a reader can copy it. A command outside the supported set is refused
with its position, never passed through, and the length, the depth and the expansion of a formula are bounded.

It is for any PHP code that shows formulas written by people: a document block converted once when it is saved, an
EPUB, a page that must read without a script. Browsers have drawn MathML Core natively since 2023.

It is a plain PHP library built on `ext-mbstring` and `phil/url`. It is not TeX: it reads the common commands of
formulas written by hand and macros that stand for them, not packages or text layout.

## Status

| Part | State |
|---|---|
| Identifiers, numbers, operators, Greek letters and the common symbols | Implemented and tested |
| Scripts, primes, large operators and their limits, functions | Implemented and tested |
| `\frac`, `\binom`, `\sqrt`, accents, `\overset`, braces over and under | Implemented and tested |
| `\mathbf` and its kin as Unicode mathematical letters, `\text` | Implemented and tested |
| `\left` … `\middle` … `\right`, `\big` and its kin | Implemented and tested |
| Matrices, `cases`, `aligned`, `array`, its rules and `\hline` | Implemented and tested |
| `\newcommand`, `\renewcommand` and `\def`, with arguments, within a budget of expansion | Implemented and tested |
| Colours, links through `phil/url`, `\boxed`, sizes and styles of a group | Implemented and tested |
| `\tag` and `\tag*`, for a formula and for the rows of an environment | Implemented and tested |
| Chemical notation, `\ce{…}`: formulas, charges, states, isotopes, bonds, arrows and their conditions | Implemented and tested |
| Limits of length, depth, cells and expansion; every refusal with its byte offset; fuzzing | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Converting](doc/converting.md) | `LatexToMathMl`, its output, its limits and its refusals. |
| [Commands](doc/commands.md) | Every command and environment read, and what is not. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests, its fuzz target and its mutation bar. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/latex-mathml
```

```php
use Phil\LatexMathMl\LatexToMathMl;

$mathml = new LatexToMathMl()->convert('\sum_{i=1}^{n} i = \frac{n(n+1)}{2}');
// <math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mrow><munderover>…
```

## Requirements

PHP 8.5 or later, with `ext-ctype`, `ext-mbstring` and `ext-intl`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
