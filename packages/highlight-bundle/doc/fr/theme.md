<!-- phil-dev source-sha256=75b594edb8e311d941fdb0070f0e817a5c42da35369bad66cb62b36308e2730d -->
# Thème

`assets/styles/highlight.css` colore les classes `hl-` de `phil/highlight` et met en page les blocs qui les
contiennent : un `pre` qui contient un `code` avec une classe `language-…`, les blocs de code des documents UJO
(`pre.ujo-code`) et tout élément de classe `hl`. Le bloc défile horizontalement plutôt que de revenir à la ligne, garde
aux tabulations une largeur de quatre colonnes et affiche un anneau de focus quand il est atteint au clavier.

Il est clair ou sombre selon la préférence du système, sauf si un ancêtre porte `data-theme="light"` ou
`data-theme="dark"`, l'attribut que bascule PHIL UX ; il est clair sur papier, où les longues lignes reviennent à la
ligne plutôt que d'être coupées. En couleurs forcées, les couleurs du système l'emportent et les lignes d'un diff sont
soulignées ou barrées au lieu d'être teintées. Chaque couleur respecte le contraste WCAG AA pour du texte normal sur le
fond où elle s'affiche, lignes teintées d'un diff et lignes marquées comprises.

Quand le filtre écrit le bloc ligne par ligne, chaque ligne est une boîte au moins aussi large que le bloc ; son
numéro, de la couleur des commentaires, ne peut pas être sélectionné, si bien qu'une copie ne contient que le code. Une
ligne mise en évidence prend sa teinte et une barre de la couleur des appels ; une ligne ajoutée ou retirée, sa teinte,
une barre et un `+` ou un `-`, cachés aux lecteurs d'écran, pour qu'aucune marque ne se dise par la couleur seule. En
couleurs forcées, la barre d'une ligne marquée prend la couleur de surlignage du système et la teinte disparaît.

## Couleurs

Chaque couleur est une propriété personnalisée, réglée sur `:root` et sur tout élément qui porte `data-theme` :

| Propriété | Colore |
|---|---|
| `--hl-background`, `--hl-text`, `--hl-border` | Le bloc |
| `--hl-keyword` | Les mots-clés, les sélecteurs, les sections, les titres |
| `--hl-type` | Les types et les éléments intégrés |
| `--hl-function` | Les appels, les liens, l'anneau de focus |
| `--hl-number` | Les nombres, les constantes, les références de caractères |
| `--hl-string` | Les chaînes, les expressions régulières, le code Markdown |
| `--hl-comment` | Les commentaires, en italique |
| `--hl-punctuation` | Les opérateurs et la ponctuation |
| `--hl-variable` | Les variables |
| `--hl-meta` | Les métadonnées |
| `--hl-tag` | Les noms de balises |
| `--hl-property` | Les noms d'attributs et les propriétés |
| `--hl-inserted`, `--hl-inserted-background`, `--hl-deleted`, `--hl-deleted-background` | Les lignes d'un diff |
| `--hl-line-highlighted-background`, `--hl-line-inserted-background`, `--hl-line-deleted-background` | Les lignes marquées, quand le bloc s'écrit ligne par ligne |

Une page change une couleur en redéfinissant sa propriété, sur `:root` ou sur un seul bloc, dans une feuille de style
chargée après le thème :

```css
:root { --hl-keyword: #7a1fa2; }
.docs pre.ujo-code { --hl-background: #ffffff; }
```

Les propriétés sont celles des thèmes de `phil/highlight` : une page qui les redéfinit garde donc ses couleurs avec l'un
ou l'autre. Un thème propre à l'application est une feuille de style des classes `hl-`, que liste `phil/highlight`.
