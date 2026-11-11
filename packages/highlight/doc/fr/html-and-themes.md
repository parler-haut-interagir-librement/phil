<!-- phil-dev source-sha256=e33084a40cff66065b656cd430427c9a96c018026df7797324ab8ba58749ca7d -->
# HTML et thèmes

## Types de jetons

Chaque langage écrit les mêmes 26 types : un même thème les sert donc tous. La classe d'un type est `hl-` suivi de sa
valeur.

| Type | Classe | Ce qu'il marque |
|---|---|---|
| `Keyword` | `hl-keyword` | Mots réservés, règles @ du CSS, instructions de Dockerfile |
| `Type` | `hl-type` | Noms de types, et noms en majuscule dans les langages où c'est un signe |
| `Builtin` | `hl-builtin` | Fonctions que fournit le langage, souvent appelées sans parenthèses |
| `Constant` | `hl-constant` | `true`, `null`, symboles Ruby, booléens YAML et INI |
| `Number` | `hl-number` | Nombres, dimensions et couleurs CSS |
| `String` | `hl-string` | Textes entre guillemets, valeurs d'attributs, valeurs simples YAML et INI |
| `Regex` | `hl-regex` | Littéraux d'expressions régulières |
| `Comment` | `hl-comment` | Commentaires, notes de diff |
| `Operator` | `hl-operator` | Opérateurs, indicateurs de bloc YAML, le `=` de l'INI |
| `Punctuation` | `hl-punctuation` | Crochets, séparateurs, chevrons du balisage, marques Markdown |
| `Variable` | `hl-variable` | Variables avec un sigil, propriétés personnalisées CSS, ancres et alias YAML, interpolations SCSS |
| `Function` | `hl-function` | Appels, macros, filtres, fonctions CSS |
| `Meta` | `hl-meta` | Lignes du préprocesseur, décorateurs, attributs, shebangs, balises PHP, doctypes, étiquettes YAML, info de clôture |
| `Tag` | `hl-tag` | Noms de balises du balisage |
| `AttributeName` | `hl-attr-name` | Noms d'attributs du balisage |
| `Entity` | `hl-entity` | Références de caractères |
| `Property` | `hl-property` | Propriétés CSS, clés JSON, YAML et INI |
| `Selector` | `hl-selector` | Sélecteurs CSS |
| `Section` | `hl-section` | Sections INI, en-têtes de blocs de diff |
| `Heading` | `hl-heading` | Titres Markdown |
| `Emphasis` | `hl-emphasis` | Emphase Markdown |
| `Strong` | `hl-strong` | Emphase forte Markdown |
| `Link` | `hl-link` | Liens, images et liens automatiques Markdown |
| `Code` | `hl-code` | Code en ligne et blocs clôturés Markdown |
| `Inserted` | `hl-inserted` | Lignes qu'ajoute un diff |
| `Deleted` | `hl-deleted` | Lignes que retire un diff |

## Le HTML

`HtmlFormatter::format()` échappe chaque texte avec `htmlspecialchars()` (`&`, `<`, `>`, `"` et `'`) et enveloppe
chaque jeton typé dans `<span class="hl-…">` ; le texte simple, comme les espaces et les noms ordinaires, n'est pas
enveloppé. Les jetons de même type qui se suivent sont déjà joints par l'analyseur lexical : une suite de ponctuation
forme donc une seule balise span. L'UTF-8 invalide est remplacé par U+FFFD. La sortie ne contient aucun autre balisage
ni aucune structure de lignes propre : placez-la dans `<pre><code class="language-…">`, dont le `white-space` garde
les lignes.

La classe `language-…` est ce que lit le contrôleur de coloration de `phil/ux-bundle` dans le navigateur ; un bloc qui
contient déjà des balises span `hl-` est laissé tel quel : du code coloré sur le serveur n'est donc pas coloré deux
fois.

Avec des `LineOptions`, les mêmes jetons s'écrivent ligne par ligne, chaque ligne dans un `<span class="hl-line">` avec
ses marques et son numéro : voir [Lignes](lines.md).

## Les thèmes

`themes/light.css`, `themes/dark.css` et `themes/auto.css` définissent des propriétés personnalisées sur `:root` et
colorent les classes avec elles ; un `pre` qui contient un `code` doté d'un langage, ou tout élément de classe `hl`,
prend le fond et la couleur de texte du thème. Chaque couleur atteint le contraste AA des WCAG pour du texte normal sur
son fond.

| Propriété | Couleurs |
|---|---|
| `--hl-background`, `--hl-text` | Le bloc |
| `--hl-keyword` | Mots-clés, sélecteurs, sections, titres |
| `--hl-type` | Types et fonctions intégrées |
| `--hl-function` | Appels, liens |
| `--hl-number` | Nombres, constantes, références |
| `--hl-string` | Chaînes, expressions régulières, code Markdown |
| `--hl-comment` | Commentaires, en italique |
| `--hl-punctuation` | Opérateurs et ponctuation |
| `--hl-variable` | Variables |
| `--hl-meta` | Méta |
| `--hl-tag` | Noms de balises |
| `--hl-property` | Noms d'attributs et propriétés |
| `--hl-inserted`, `--hl-inserted-background`, `--hl-deleted`, `--hl-deleted-background` | Lignes de diff |
| `--hl-line-highlighted-background`, `--hl-line-inserted-background`, `--hl-line-deleted-background` | Lignes marquées |

Une page change une couleur en redéfinissant sa propriété, sur `:root` ou sur un seul bloc :

```css
:root { --hl-keyword: #7c3aed; }
```

Un thème à vous est une feuille de style des classes `hl-` : la liste ci-dessus est tout le contrat, avec les classes
des lignes ci-dessous.

## Lignes

Quand un bloc est écrit ligne par ligne, chaque thème dispose les lignes :

| Classe | Disposée comme |
|---|---|
| `hl-line` | Une boîte au moins aussi large que le bloc, avec de la place à son début pour une barre et un signe |
| `hl-line-number` | Alignée à droite, de la couleur des commentaires ; elle ne peut pas être sélectionnée, si bien qu'une copie du bloc ne contient que le code |
| `hl-line-highlighted` | Sa teinte et une barre de la couleur des appels |
| `hl-line-inserted` | Sa teinte, une barre et un `+` de la couleur des lignes ajoutées |
| `hl-line-deleted` | Sa teinte, une barre et un `-` de la couleur des lignes retirées |

Une marque ne se dit jamais par la couleur seule : la barre et le signe la disent aussi, et le signe est caché aux
lecteurs d'écran. Chaque couleur de jeton garde le contraste AA des WCAG sur les trois teintes ; dans le thème clair,
dont les couleurs frôlent cette limite sur son fond, les teintes sont légères et la barre en dit l'essentiel.
