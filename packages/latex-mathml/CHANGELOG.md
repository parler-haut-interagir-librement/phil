# Changelog

All notable changes to `phil/latex-mathml` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `LatexToMathMl`: a LaTeX formula as a `<math>` element of MathML Core, block or inline, the formula kept as a TeX
  annotation, the delimiters `$…$`, `$$…$$`, `\(…\)` and `\[…\]` left out, comments ignored.
- Identifiers, numbers, operators, Greek letters and the common symbols; scripts, primes, large operators with their
  limits, `\limits` and `\nolimits`, functions and `\operatorname`; fractions, binomials, roots, accents, `\overset`,
  `\underset`, braces over and under, `\not`, modulo; fonts as Unicode mathematical letters, `\mathrm` upright, text;
  `\left`, `\middle` and `\right`, `\big` and its kin; spaces; `\displaystyle`, `\textstyle`, `\scriptstyle`,
  `\scriptscriptstyle` and the sizes from `\tiny` to `\Huge`; matrices, `cases`, `aligned`, `gathered` and `array`,
  with the rules of its column specification and `\hline`.
- Macros: `\newcommand`, `\renewcommand` and `\def` with up to nine arguments, expanded before the formula is read,
  within a budget of uses, nesting and tokens; a macro used inside its own expansion refused with its position.
- `\color` and `\textcolor` with the names of `xcolor`, `#rgb`, `#rrggbb`, `[HTML]` and `[rgb]`; `\href` and `\url`
  as HTML links inside `<mtext>`, `http` and `https` addresses read by `phil/url`, `mailto` with one mailbox; `\boxed`;
  `\tag`, `\tag*`, `\notag` and `\nonumber`, for a formula and for the rows of an environment.
- Chemical notation in `\ce{…}`: formulas, coefficients, subscripts, charges, states, isotopes, hydrates, bonds, `+`,
  arrows with their conditions, gases and precipitates.
- `InvalidLatex`, under the `LatexMathMlException` interface, with the byte offset of the problem; limits of length,
  depth, cells and expansion.
- A fuzz target and its seeds, the new constructions among them, and tests of each construct and each refusal.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
