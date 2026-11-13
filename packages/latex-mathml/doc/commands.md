# Commands

## Characters

| Written | Read as |
|---|---|
| a letter, `é`, any letter of Unicode | an identifier |
| `3`, `3.14` | a number; as the argument of a script or a command, one digit, as in TeX |
| `+ - * / = < > ( ) [ ] \| , ; : ! ?` and any other character | an operator; `-` is a minus sign, `*` an asterisk operator |
| `'`, `''` | primes, as a superscript |
| `{…}` | a group |
| `~`, `\ ` | a space |

## Symbols

Greek letters (`\alpha` … `\omega`, `\varepsilon`, `\varphi` and the other variants; `\Gamma` … `\Omega` upright),
`\infty`, `\partial`, `\nabla`, `\emptyset`, `\hbar`, `\ell`, `\Re`, `\Im`, `\aleph`; the binary operators
(`\times`, `\cdot`, `\div`, `\pm`, `\mp`, `\circ`, `\oplus`, `\otimes`, `\cup`, `\cap`, `\setminus`, `\wedge`, `\vee`…),
relations (`\leq`, `\geq`, `\neq`, `\approx`, `\equiv`, `\sim`, `\cong`, `\propto`, `\in`, `\notin`, `\subset`,
`\subseteq`, `\perp`, `\parallel`, `\mid`…), arrows (`\to`, `\rightarrow`, `\leftarrow`, `\Rightarrow`, `\iff`,
`\mapsto`…), dots (`\ldots`, `\cdots`, `\vdots`, `\ddots`), the escaped `\{ \} \| \% \& \# \$ \_`, and the fences
`\langle`, `\rangle`, `\lfloor`, `\rfloor`, `\lceil`, `\rceil`, `\vert`, `\Vert`. `\not` crosses the operator after it.

## Operators and functions

| Command | Written as |
|---|---|
| `\sum`, `\prod`, `\coprod`, `\bigcup`, `\bigcap`, `\bigoplus`, `\bigotimes`, `\bigvee`, `\bigwedge` | limits under and over |
| `\int`, `\iint`, `\iiint`, `\oint` | limits beside |
| `\limits`, `\nolimits` | after an operator, limits under and over, or beside |
| `\sin`, `\cos`, `\log`, `\ln`, `\exp`, `\det`, `\gcd` and the other usual functions | upright, applied to what follows |
| `\lim`, `\max`, `\min`, `\sup`, `\inf`, `\liminf`, `\limsup` | upright, limits under |
| `\operatorname{name}` | a function of that name |
| `\bmod`, `\pmod{n}`, `\mod{n}` | modulo |

## Structures

| Command | Written as |
|---|---|
| `\frac{a}{b}`, `\cfrac`, `\dfrac`, `\tfrac` | a fraction; `d` displayed, `t` in text style |
| `\binom{n}{k}`, `\dbinom`, `\tbinom` | a binomial coefficient |
| `\sqrt{x}`, `\sqrt[n]{x}` | a root |
| `\hat`, `\widehat`, `\tilde`, `\widetilde`, `\bar`, `\overline`, `\vec`, `\overrightarrow`, `\dot`, `\ddot`, `\check`, `\breve`, `\acute`, `\grave` | an accent over |
| `\underline`, `\overbrace`, `\underbrace` | a mark under or over; a brace takes its label as a script |
| `\overset{a}{b}`, `\stackrel`, `\underset{a}{b}` | `a` over or under `b` |
| `\mathbf`, `\boldsymbol`, `\mathbb`, `\mathcal`, `\mathscr`, `\mathfrak`, `\mathsf`, `\mathtt`, `\mathit`, `\mathrm` | letters of that alphabet |
| `\text`, `\textrm`, `\mbox`, `\textnormal`, `\textbf`, `\textit` | text, its spaces kept |
| `\left(` … `\middle|` … `\right)` | delimiters stretched over the content; `.` for none |
| `\big`, `\Big`, `\bigg`, `\Bigg`, with `l`, `r` or `m` | a delimiter of a fixed size |
| `\,`, `\:`, `\;`, `\!`, `\quad`, `\qquad` and their named forms | spaces |
| `\displaystyle`, `\textstyle`, `\scriptstyle`, `\scriptscriptstyle` | the rest of the group in that style |
| `\tiny`, `\scriptsize`, `\footnotesize`, `\small`, `\normalsize`, `\large`, `\Large`, `\LARGE`, `\huge`, `\Huge` | the rest of the group at that size, from 0.5em to 2.488em |
| `\color{colour}` | the rest of the group in that colour |
| `\textcolor{colour}{x}` | `x` in that colour |
| `\boxed{x}` | `x` in a box |
| `\href{address}{text}`, `\url{address}` | the text, or the address, as a link |
| `\tag{n}`, `\tag*{n}` | the number of the formula, `(n)` or `n` as written |
| `\notag`, `\nonumber` | nothing: a formula is numbered only by `\tag` |
| `\ce{…}` | chemical notation, below |

## Colours

A colour is one of the names LaTeX's `xcolor` package always knows — `black`, `blue`, `brown`, `cyan`, `darkgray`,
`gray`, `green`, `lightgray`, `lime`, `magenta`, `olive`, `orange`, `pink`, `purple`, `red`, `teal`, `violet`, `white`,
`yellow` —, `#rgb` or `#rrggbb`, or a value in a model between brackets: `\color[HTML]{FF8800}`,
`\textcolor[rgb]{1,0.5,0}{x}`. It is written as `mathcolor="#rrggbb"`; any other text is refused.

## Links

The address of `\href` and `\url` is an `http` or `https` address, read and normalised by `phil/url` — which refuses
credentials, IP addresses and `localhost` —, or `mailto:` and one mailbox whose domain `phil/url` accepts. Any other
scheme, `javascript:` and `data:` among them, is refused. As in LaTeX, `%` starts a comment: write `\%` for it in an
address. The link is an HTML `<a>` inside `<mtext>`, the one place MathML holds HTML, so every browser follows it.

## Equation numbers

`\tag` numbers the formula, or the row of an environment it is written in; one per formula or per row. A formula's
number is written after it; in an environment, numbers fill a last column, empty for rows without one.

## Rules

In `array`, `|` in the column specification draws a rule between columns, or before the first and after the last;
`\hline` at the start of a row of any environment draws a rule above it, and before `\end` below the last row. Rules
and boxes are drawn by the `style` attribute of the cells and of the box.

## Macros

```latex
\newcommand{\R}{\mathbb{R}}
\newcommand{\pair}[2]{\left(#1, #2\right)}
\def\sq#1{#1^2}
```

`\newcommand`, `\renewcommand` and `\def` define a macro of up to nine arguments, used anywhere after its definition.
A macro's arguments are expanded before they are put in place of `#1` … `#9`, then its body is expanded, before the
formula is read. `\renewcommand` and `\def` may redefine any command, `\newcommand` only a name no macro has; definitions
hold to the end of the formula, whatever group they are written in. A macro used inside its own expansion is refused
with the position of the use.

## Chemistry

`\ce{…}` reads words separated by spaces:

| Written | Read as |
|---|---|
| `H2O`, `Ca(OH)2`, `[Cu(NH3)4]` | elements upright, the digits after an atom or a group as its subscript |
| `2H2O` | a coefficient before a formula |
| `Na+`, `Cl-`, `SO4^2-`, `Fe^{3+}` | a charge: a final `+` or `-`, or what follows `^` |
| `Fe_xO`, `C_{n}H_{2n}` | a subscript after `_` |
| `NaCl(aq)`, `(s)`, `(l)`, `(g)`, `(cr)` | a state |
| `^{227}_{90}Th` | mass and atomic numbers before an element |
| `CuSO4*5H2O`, `CaSO4.H2O` | the dot of a hydrate |
| `C=C`, `C-C`, `C#N` | single, double and triple bonds between atoms |
| `+` | a plus between formulas |
| `->`, `<-`, `<->`, `<=>` | arrows; `->[over][under]` writes conditions over and under |
| `^`, `v` | a gas given off, a precipitate |

Any other character, and any command, is refused with its position.

## Environments

`matrix`, `smallmatrix`, `pmatrix`, `bmatrix`, `Bmatrix`, `vmatrix`, `Vmatrix`, `cases`, `aligned`, `align`,
`align*`, `gathered` and `array` with its column specification (`l`, `c`, `r` and `|`, spaces ignored). Cells are
separated by `&`, rows by `\\`, whose optional space, `\\[2pt]`, is ignored; a last empty row is left out. `\hline`
starts a row.

## Not read

Macros with an optional argument or delimited parameters, `\def\x#1.{…}`; column specifications other than `l`, `c`,
`r` and `|`, such as `p{2cm}` and `@{}`; `\color` inside an argument, where `\textcolor` is meant; `\label`, `\ref` and
automatic numbering; colour models other than `HTML` and `rgb`; the parts of chemical notation not listed above,
commands inside `\ce` among them; text layout, packages; and any command not listed here. Each is refused with its
position.
