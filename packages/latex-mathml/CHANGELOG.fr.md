<!-- phil-dev source-sha256=53ccf03d51ead515443ef0332d485b7ad7dc3c797e34c14e96f6415a8ec60ee2 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/latex-mathml` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `LatexToMathMl` : une formule LaTeX comme élément `<math>` de MathML Core, en bloc ou en ligne, la formule gardée
  comme annotation TeX, les délimiteurs `$…$`, `$$…$$`, `\(…\)` et `\[…\]` laissés de côté, les commentaires ignorés.
- Les identifiants, les nombres, les opérateurs, les lettres grecques et les symboles courants ; les indices et les
  exposants, les primes, les grands opérateurs avec leurs bornes, `\limits` et `\nolimits`, les fonctions et
  `\operatorname` ; les fractions, les binomiaux, les racines, les accents, `\overset`, `\underset`, les accolades
  au-dessus et au-dessous, `\not`, le modulo ; les polices comme lettres mathématiques Unicode, `\mathrm` en romain, le
  texte ; `\left`, `\middle` et `\right`, `\big` et sa famille ; les espaces ; `\displaystyle`, `\textstyle`,
  `\scriptstyle`, `\scriptscriptstyle` et les tailles de `\tiny` à `\Huge` ; les matrices, `cases`, `aligned`,
  `gathered` et `array`, avec les filets de sa spécification de colonnes et `\hline`.
- Les macros : `\newcommand`, `\renewcommand` et `\def` avec jusqu'à neuf arguments, développées avant la lecture de la
  formule, dans un budget d'usages, d'imbrication et de jetons ; une macro employée dans son propre développement
  refusée avec sa position.
- `\color` et `\textcolor` avec les noms de `xcolor`, `#rgb`, `#rrggbb`, `[HTML]` et `[rgb]` ; `\href` et `\url` comme
  liens HTML dans `<mtext>`, les adresses `http` et `https` lues par `phil/url`, `mailto` avec une seule boîte aux
  lettres ; `\boxed` ; `\tag`, `\tag*`, `\notag` et `\nonumber`, pour une formule et pour les rangées d'un
  environnement.
- La notation chimique dans `\ce{…}` : formules, coefficients, indices, charges, états, isotopes, hydrates, liaisons,
  `+`, flèches avec leurs conditions, gaz et précipités.
- `InvalidLatex`, sous l'interface `LatexMathMlException`, avec la position en octets du problème ; des limites de
  longueur, de profondeur, de cellules et de développement.
- Une cible de fuzzing et ses germes, les nouvelles constructions parmi eux, et des tests de chaque construction et de
  chaque refus.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; `CONTRIBUTING.md`, `SECURITY.md` et
  `CODE_OF_CONDUCT.md`.
