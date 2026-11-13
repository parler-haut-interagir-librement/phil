# Converting

```php
new LatexToMathMl(int $maxLength = 20_000, int $maxDepth = 64, int $maxExpansions = 1_000)
    ->convert(string $latex, bool $display = true): string
```

The result is one `<math>` element, `display="block"` for a formula on its own line, holding a `<semantics>` whose
first child is the formula and whose `<annotation encoding="application/x-tex">` is the LaTeX. The delimiters `$…$`,
`$$…$$`, `\(…\)` and `\[…\]` around the whole formula are left out; spaces are not significant outside `\text`;
`%` starts a comment to the end of the line.

## Output

Only MathML Core elements — `mi`, `mn`, `mo`, `mtext`, `mspace`, `mrow`, `msub`, `msup`, `msubsup`, `munder`,
`mover`, `munderover`, `mmultiscripts`, `mprescripts`, `mfrac`, `msqrt`, `mroot`, `mstyle`, `mtable`, `mtr`, `mtd`,
`semantics`, `annotation` — and the attributes the converter writes are produced, every text escaped; the one HTML
element is the `<a>` of a link, inside `<mtext>`, its address an `http`, `https` or `mailto` one checked by
`phil/url`. Colours are validated before they are written as `mathcolor`. The output is safe to write in a page.

Boxes and the rules of environments are drawn by `style` attributes. A Content Security Policy that forbids inline
styles without `'unsafe-inline'` in `style-src-attr` leaves them undrawn; the formula itself is drawn.

Bold, double-struck, script, fraktur, sans-serif and monospace letters are written as the characters of Unicode's
Mathematical Alphanumeric Symbols, which MathML Core draws in place of `mathvariant`; `\mathrm` writes
`mathvariant="normal"`.

## Limits

| Limit | Default |
|---|---|
| Length of a formula | 20 000 bytes |
| Depth of groups, arguments, environments and macros | 64 |
| Cells of an environment | 2 500 |
| Uses of macros expanded | 1 000 |
| Tokens of the formula once its macros are expanded | as many as its bytes, 20 000 |

Macros are expanded first, within these limits, so that a macro which doubles its argument at each level cannot make
a short formula long. The expanded formula is then read once, each token once: the work and the output are linear in
it.

## Refusals

`InvalidLatex` refuses a formula with a message for its author and the byte where the problem was found, in
`$offset`: a command outside the supported set, a group or an environment left open, an environment closed by
another name, two superscripts on one atom, a command without its argument, `&` or `\\` outside an environment, a
`#` or a `$` inside the formula, text that is not UTF-8, a macro used inside its own expansion, a colour or an address
that is not one, two `\tag` on one formula or row, a character chemical notation does not read, and a formula beyond
a limit. A problem inside the expansion of a macro is reported at the use of the macro.

## Where formulas are drawn

Browsers draw MathML Core: Chromium since version 109, Firefox and Safari for longer. EPUB 3 readers draw MathML.
WeasyPrint does not draw MathML: a PDF made by WeasyPrint needs the formula in another form, such as an image; a PDF
made by Chromium, such as Gotenberg's, draws it.
