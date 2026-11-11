<!-- phil-dev source-sha256=c2533dbdbc1b3763e5335b11882bf0278339b2f3f3f5cfb96f6b0c1ace630f9c -->
# Points d'extension

Ce qu'une application implémente, remplace ou utilise.

## Langages

### `Language`

`Phil\Highlight\Language` : un langage que lit le coloriseur. Un service qui l'implémente reçoit l'étiquette
`phil_highlight.language` par configuration automatique, ou à la main, et est ajouté au coloriseur : voir
[Langages](languages.md).

| Méthode | Contrat |
|---|---|
| `name(): string` | le nom canonique, en minuscules, lettres, chiffres et `_+#.-`, jusqu'à 32 caractères |
| `aliases(): list<string>` | d'autres noms, écrits de la même façon |
| `tokenize(string $code): list<Token>` | ne lance jamais d'exception, ne perd jamais un octet : les textes des jetons mis bout à bout donnent le code |

Un langage étiqueté prend son nom et ses alias à tout langage qui les avait, fourni ou étiqueté plus tôt.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `aliases` | D'autres noms pour n'importe quel langage, fourni ou de l'application |
| `ujo.enabled` | Si le moteur de rendu UJO est décoré |

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\Highlight\Highlighter` | Injecté partout où du code est coloré hors des gabarits ; public. Une application qui définit son propre service sous cet id après le bundle conserve les langages étiquetés et les alias qui y sont ajoutés |
| `Phil\Ujo\Html\HtmlRenderer` | Décoré par `phil_highlight.ujo_renderer` ; les autres décorations du même service se composent avec lui |

## Thème

Les propriétés personnalisées `--hl-*` du [thème](theme.md) sont le contrat de la feuille de style : une page redéfinit
n'importe laquelle d'entre elles, sur `:root` ou sur un seul bloc, et les classes `hl-*` de `phil/highlight` sont le
contrat d'un thème qui lui est propre.
