<!-- phil-dev source-sha256=6b0050c8bf0a39dc37d666605db99b7b37d9e12ac70bea568c4a8f80b7a67b98 -->
# Points d'extension

La bibliothèque est du simple PHP et ne déclare aucun service. Ce qu'une application ajoute, ce sont des langages, et
ce sur quoi elle compte, c'est la forme du HTML.

## `Language`

`Phil\Highlight\Language` : un langage que lit le colorateur, ajouté par `Highlighter::with()` sous son nom et ses
alias, à la place de tout langage qui les avait. Voir [Ajouter un langage](extending.md).

| Méthode | Contrat |
|---|---|
| `name(): string` | Le nom canonique, en minuscules : lettres, chiffres et `_+#.-`, jusqu'à 32 caractères |
| `aliases(): list<string>` | D'autres noms, écrits de la même façon |
| `tokenize(string $code): list<Token>` | Ne lève jamais d'exception et ne perd jamais un octet : les textes des jetons, joints, forment le code |

Un langage de programmation est le plus souvent une `Language\Syntax` lue par `Language\Code`, sans classe à lui ; un
langage d'une autre forme implémente lui-même `Language`.

## Colorateurs

| Point | Comment |
|---|---|
| Les langages connus | `Highlighter::default()` pour chaque langage fourni, `Highlighter::of()` pour quelques-uns choisis, puis `with()` pour ceux de l'application |
| Remplacer un langage fourni | `with()` un langage qui porte son nom |
| La disposition d'un bloc | Des `LineOptions` sur `toHtml()` ou `highlight()` : numéros, plages mises en évidence, annotations ; voir [Lignes](lines.md) |
| L'endroit où va le HTML | `highlight()` a la forme du `HtmlRenderer::withCodeHighlighter()` de `phil/ujo` : les blocs de code des documents UJO sont colorés par lui ; voir [Documents UJO](ujo.md) |

## Le HTML et les thèmes

Les classes `hl-*` des balises span, une par `TokenType`, sont le contrat d'un thème : une feuille de style de
l'application les colore comme elle le veut ; voir [HTML et thèmes](html-and-themes.md). Les types de jetons sont une
énumération fermée : un langage y fait correspondre ce qu'il lit.
