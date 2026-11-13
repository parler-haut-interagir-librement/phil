<!-- phil-dev source-sha256=19618df8cacd30a5fa0887e3a86852fea3f06cfe8c91e4b2266bf279a99f3fab -->
# Commandes

## Caractères

| Écrit | Lu comme |
|---|---|
| une lettre, `é`, toute lettre d'Unicode | un identifiant |
| `3`, `3.14` | un nombre ; comme argument d'un indice, d'un exposant ou d'une commande, un seul chiffre, comme en TeX |
| `+ - * / = < > ( ) [ ] \| , ; : ! ?` et tout autre caractère | un opérateur ; `-` est un signe moins, `*` un opérateur astérisque |
| `'`, `''` | des primes, en exposant |
| `{…}` | un groupe |
| `~`, `\ ` | une espace |

## Symboles

Les lettres grecques (`\alpha` … `\omega`, `\varepsilon`, `\varphi` et les autres variantes ; `\Gamma` … `\Omega`
droites), `\infty`, `\partial`, `\nabla`, `\emptyset`, `\hbar`, `\ell`, `\Re`, `\Im`, `\aleph` ; les opérateurs
binaires (`\times`, `\cdot`, `\div`, `\pm`, `\mp`, `\circ`, `\oplus`, `\otimes`, `\cup`, `\cap`, `\setminus`,
`\wedge`, `\vee`…), les relations (`\leq`, `\geq`, `\neq`, `\approx`, `\equiv`, `\sim`, `\cong`, `\propto`, `\in`,
`\notin`, `\subset`, `\subseteq`, `\perp`, `\parallel`, `\mid`…), les flèches (`\to`, `\rightarrow`, `\leftarrow`,
`\Rightarrow`, `\iff`, `\mapsto`…), les points (`\ldots`, `\cdots`, `\vdots`, `\ddots`), les caractères échappés
`\{ \} \| \% \& \# \$ \_`, et les délimiteurs `\langle`, `\rangle`, `\lfloor`, `\rfloor`, `\lceil`, `\rceil`,
`\vert`, `\Vert`. `\not` barre l'opérateur qui le suit.

## Opérateurs et fonctions

| Commande | Écrite comme |
|---|---|
| `\sum`, `\prod`, `\coprod`, `\bigcup`, `\bigcap`, `\bigoplus`, `\bigotimes`, `\bigvee`, `\bigwedge` | limites au-dessous et au-dessus |
| `\int`, `\iint`, `\iiint`, `\oint` | limites à côté |
| `\limits`, `\nolimits` | après un opérateur, limites au-dessous et au-dessus, ou à côté |
| `\sin`, `\cos`, `\log`, `\ln`, `\exp`, `\det`, `\gcd` et les autres fonctions usuelles | droites, appliquées à ce qui suit |
| `\lim`, `\max`, `\min`, `\sup`, `\inf`, `\liminf`, `\limsup` | droites, limites au-dessous |
| `\operatorname{name}` | une fonction de ce nom |
| `\bmod`, `\pmod{n}`, `\mod{n}` | modulo |

## Structures

| Commande | Écrite comme |
|---|---|
| `\frac{a}{b}`, `\cfrac`, `\dfrac`, `\tfrac` | une fraction ; `d` en style hors texte, `t` en style texte |
| `\binom{n}{k}`, `\dbinom`, `\tbinom` | un coefficient binomial |
| `\sqrt{x}`, `\sqrt[n]{x}` | une racine |
| `\hat`, `\widehat`, `\tilde`, `\widetilde`, `\bar`, `\overline`, `\vec`, `\overrightarrow`, `\dot`, `\ddot`, `\check`, `\breve`, `\acute`, `\grave` | un accent au-dessus |
| `\underline`, `\overbrace`, `\underbrace` | une marque au-dessous ou au-dessus ; une accolade prend son étiquette en indice ou en exposant |
| `\overset{a}{b}`, `\stackrel`, `\underset{a}{b}` | `a` au-dessus ou au-dessous de `b` |
| `\mathbf`, `\boldsymbol`, `\mathbb`, `\mathcal`, `\mathscr`, `\mathfrak`, `\mathsf`, `\mathtt`, `\mathit`, `\mathrm` | des lettres de cet alphabet |
| `\text`, `\textrm`, `\mbox`, `\textnormal`, `\textbf`, `\textit` | du texte, ses espaces gardées |
| `\left(` … `\middle|` … `\right)` | des délimiteurs étirés sur le contenu ; `.` pour aucun |
| `\big`, `\Big`, `\bigg`, `\Bigg`, avec `l`, `r` ou `m` | un délimiteur de taille fixe |
| `\,`, `\:`, `\;`, `\!`, `\quad`, `\qquad` et leurs formes nommées | des espaces |
| `\displaystyle`, `\textstyle`, `\scriptstyle`, `\scriptscriptstyle` | le reste du groupe dans ce style |
| `\tiny`, `\scriptsize`, `\footnotesize`, `\small`, `\normalsize`, `\large`, `\Large`, `\LARGE`, `\huge`, `\Huge` | le reste du groupe à cette taille, de 0,5em à 2,488em |
| `\color{colour}` | le reste du groupe dans cette couleur |
| `\textcolor{colour}{x}` | `x` dans cette couleur |
| `\boxed{x}` | `x` dans une boîte |
| `\href{address}{text}`, `\url{address}` | le texte, ou l'adresse, comme lien |
| `\tag{n}`, `\tag*{n}` | le numéro de la formule, `(n)` ou `n` tel qu'écrit |
| `\notag`, `\nonumber` | rien : une formule n'est numérotée que par `\tag` |
| `\ce{…}` | la notation chimique, ci-dessous |

## Couleurs

Une couleur est l'un des noms que le paquet `xcolor` de LaTeX connaît toujours (`black`, `blue`, `brown`, `cyan`,
`darkgray`, `gray`, `green`, `lightgray`, `lime`, `magenta`, `olive`, `orange`, `pink`, `purple`, `red`, `teal`,
`violet`, `white`, `yellow`), `#rgb` ou `#rrggbb`, ou une valeur dans un modèle entre crochets : `\color[HTML]{FF8800}`,
`\textcolor[rgb]{1,0.5,0}{x}`. Elle est écrite `mathcolor="#rrggbb"` ; tout autre texte est refusé.

## Liens

L'adresse de `\href` et de `\url` est une adresse `http` ou `https`, lue et normalisée par `phil/url` (qui refuse les
identifiants, les adresses IP et `localhost`), ou `mailto:` et une seule boîte aux lettres dont le domaine est accepté
par `phil/url`. Tout autre schéma, dont `javascript:` et `data:`, est refusé. Comme en LaTeX, `%` commence un
commentaire : écrivez `\%` pour lui dans une adresse. Le lien est un `<a>` HTML dans `<mtext>`, le seul endroit où
MathML contient du HTML, de sorte que tous les navigateurs le suivent.

## Numéros d'équation

`\tag` numérote la formule, ou la ligne de l'environnement où il est écrit ; un par formule ou par ligne. Le numéro
d'une formule est écrit après elle ; dans un environnement, les numéros remplissent une dernière colonne, vide pour les
lignes qui n'en ont pas.

## Filets

Dans `array`, `|` dans la spécification des colonnes trace un filet entre les colonnes, ou avant la première et après
la dernière ; `\hline` au début d'une ligne de n'importe quel environnement trace un filet au-dessus, et avant `\end`
sous la dernière ligne. Les filets et les boîtes sont dessinés par l'attribut `style` des cellules et de la boîte.

## Macros

```latex
\newcommand{\R}{\mathbb{R}}
\newcommand{\pair}[2]{\left(#1, #2\right)}
\def\sq#1{#1^2}
```

`\newcommand`, `\renewcommand` et `\def` définissent une macro d'au plus neuf arguments, utilisable partout après sa
définition. Les arguments d'une macro sont développés avant d'être mis à la place de `#1` … `#9`, puis son corps est
développé, avant que la formule soit lue. `\renewcommand` et `\def` peuvent redéfinir n'importe quelle commande,
`\newcommand` seulement un nom qu'aucune macro n'a ; les définitions valent jusqu'à la fin de la formule, quel que soit
le groupe où elles sont écrites. Une macro utilisée dans sa propre expansion est refusée, avec la position de
l'utilisation.

## Chimie

`\ce{…}` lit des mots séparés par des espaces :

| Écrit | Lu comme |
|---|---|
| `H2O`, `Ca(OH)2`, `[Cu(NH3)4]` | des éléments droits, les chiffres après un atome ou un groupe comme son indice |
| `2H2O` | un coefficient devant une formule |
| `Na+`, `Cl-`, `SO4^2-`, `Fe^{3+}` | une charge : un `+` ou un `-` final, ou ce qui suit `^` |
| `Fe_xO`, `C_{n}H_{2n}` | un indice après `_` |
| `NaCl(aq)`, `(s)`, `(l)`, `(g)`, `(cr)` | un état |
| `^{227}_{90}Th` | les nombres de masse et atomique devant un élément |
| `CuSO4*5H2O`, `CaSO4.H2O` | le point d'un hydrate |
| `C=C`, `C-C`, `C#N` | des liaisons simple, double et triple entre atomes |
| `+` | un plus entre des formules |
| `->`, `<-`, `<->`, `<=>` | des flèches ; `->[over][under]` écrit des conditions au-dessus et au-dessous |
| `^`, `v` | un gaz qui se dégage, un précipité |

Tout autre caractère, et toute commande, est refusé avec sa position.

## Environnements

`matrix`, `smallmatrix`, `pmatrix`, `bmatrix`, `Bmatrix`, `vmatrix`, `Vmatrix`, `cases`, `aligned`, `align`,
`align*`, `gathered` et `array` avec sa spécification de colonnes (`l`, `c`, `r` et `|`, espaces ignorées). Les cellules
sont séparées par `&`, les lignes par `\\`, dont l'espacement facultatif, `\\[2pt]`, est ignoré ; une dernière ligne vide
est laissée de côté. `\hline` commence une ligne.

## Non lu

Les macros avec un argument facultatif ou des paramètres délimités, `\def\x#1.{…}` ; les spécifications de colonnes
autres que `l`, `c`, `r` et `|`, comme `p{2cm}` et `@{}` ; `\color` dans un argument, là où `\textcolor` est voulu ;
`\label`, `\ref` et la numérotation automatique ; les modèles de couleur autres que `HTML` et `rgb` ; les parties de la
notation chimique qui ne sont pas listées ci-dessus, dont les commandes dans `\ce` ; la mise en page du texte, les
paquets ; et toute commande qui n'est pas listée ici. Chacun est refusé avec sa position.
