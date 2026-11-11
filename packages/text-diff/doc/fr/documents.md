<!-- phil-dev source-sha256=fd5041383cb3d2b1bd1a0b4b944211ea2aace2b9851c9758070d76d424486113 -->
# Comparer des documents

`TextDiff::documents()` compare deux documents en UJO (Universal JSON Output), le format de documents par blocs de
`phil/ujo`, bloc par bloc, et retourne un `DocumentDiff`.

```php
use Phil\Ujo\Ujo;

$ujo = new Ujo();
$diff = $differ->documents($ujo->decode($oldJson), $ujo->decode($newJson));

$diff->isIdentical();   // nothing changed
$diff->changes();       // the blocks that did not stay as they were
$diff->topLevel();      // the blocks at the top of either document
$diff->blocks;          // every block of either document, nested ones included
```

Comparez les documents dans leur forme canonique — décodés par `Ujo::decode()` ou passés par `Ujo::normalize()` — pour
que deux graphies des mêmes données, un alias d'un type de bloc ou une liste dans son ancienne forme, ne se lisent pas
comme des changements. Le HTML d'une différence n'en dépend pas : chaque bloc est normalisé de nouveau avant son rendu
(voir plus bas).

## Apparier les blocs

En UJO, chaque bloc a un identifiant qui le suit au fil des modifications. À chaque niveau — le haut du document, les
blocs d'une colonne, d'une bascule, d'une note — les identifiants des anciens et des nouveaux blocs sont comparés dans
l'ordre, par le même algorithme que les textes :

| `BlockChangeKind` | Quand |
|---|---|
| `Unchanged` | l'identifiant reste à sa place, et le type, les données, les réglages et les blocs imbriqués du bloc sont les mêmes |
| `Changed` | l'identifiant reste à sa place, et quelque chose a changé dans le bloc ou dans un bloc qui y est imbriqué |
| `Moved` | l'identifiant a quitté sa place et revient ailleurs au même niveau ; ses champs indiquent s'il a aussi été modifié |
| `Added` | l'identifiant n'est que dans le nouveau document |
| `Removed` | l'identifiant n'est que dans l'ancien document |

Un bloc déplacé est énuméré une seule fois, là où il se trouve maintenant. Un bloc qui passe à un autre niveau — sorti
d'une colonne, placé dans une bascule — se lit comme retiré de l'un et ajouté à l'autre. Au-delà de la borne de
modifications ([texts.md](texts.md)), chaque bloc du milieu est soit déplacé, soit ajouté et retiré, jamais conservé :
le résultat reste exact, mais moins précis.

Deux blocs au même identifiant dans un même document ne forment pas de l'UJO valide ; la comparaison n'échoue pas pour
autant, mais les apparie comme elle le peut.

## Un `BlockChange`

| Propriété | Valeur |
|---|---|
| `kind` | le `BlockChangeKind` |
| `id`, `type` | l'identifiant et le type du nouveau bloc, ou de l'ancien quand il est retiré |
| `parent` | l'identifiant du bloc dans lequel il est imbriqué, `null` au premier niveau |
| `oldIndex`, `newIndex` | sa position parmi ses frères dans chaque document, `null` là où il n'est pas |
| `old`, `new` | le `Block` dans chaque document, `null` là où il n'est pas |
| `fields` | les `FieldChange` du bloc lui-même, sans les blocs imbriqués |

`before()` et `after()` donnent l'ancien et le nouveau bloc sous forme de listes de zéro ou un bloc, prêtes pour un
`Document`.

`DocumentDiff::$blocks` énumère chaque bloc dans l'ordre de lecture du nouveau document : un bloc avant les blocs qui y
sont imbriqués, un bloc retiré là où il se trouvait.

## Changements de champs

Deux blocs appariés sont comparés valeur par valeur : leur `type`, leurs `data` et leurs `tunes`. Chaque valeur qui
diffère est un `FieldChange` :

| Propriété | Valeur |
|---|---|
| `path` | l'emplacement de la valeur dans le bloc : `type`, `data.text`, `data.items.0.content`, `tunes.alignment.alignment` |
| `old`, `new` | la valeur sous sa forme JSON, `null` là où elle manque |
| `diff` | quand les deux valeurs sont des textes, leur `Diff` par `Tokenizer::Html` : les champs de texte UJO contiennent du HTML en ligne, conservé entier |

Les tableaux sont comparés clé par clé : une valeur ajoutée à un bloc est un changement depuis `null`, et une valeur
retirée un changement vers `null`. **Les listes sont comparées par position** : un élément inséré en tête d'une liste
modifie chaque élément qui le suit. Les listes de blocs ne sont pas des valeurs : elles forment un niveau à part,
comparé par identifiant comme ci-dessus, et leurs blocs sont énumérés comme des `BlockChange` dont le `parent` est le
bloc qui les contient. Un conteneur dont le seul changement se trouve dans un bloc imbriqué est `Changed`, sans champ
propre.

## HTML

`TextDiff::documentToHtml()` écrit la différence de deux documents en HTML, un élément par bloc de premier niveau :

```html
<div class="text-diff-block text-diff-unchanged"><h2 id="b" class="ujo-header">Title</h2></div>
<div class="text-diff-block text-diff-removed"><del><p class="ujo-paragraph">Gone</p></del></div>
<div class="text-diff-block text-diff-moved"><p class="ujo-paragraph">Hello <ins>there </ins>world</p></div>
<div class="text-diff-block text-diff-added"><ins><p class="ujo-paragraph">New</p></ins></div>
```

Chaque bloc est d'abord normalisé par le `Normalizer` de `phil/ujo` — celui qui est donné au constructeur,
`new TextDiff(normalizer: $normalizer)`, ou un normaliseur par défaut —, puis rendu par son `HtmlRenderer` — celui qui
est donné en deuxième argument, avec les moteurs de rendu, le surligneur et les filtres de l'application, ou
`HtmlRenderer::default()`. La normalisation est une exigence de sécurité, pas une commodité : le moteur de rendu
échappe les textes et assainit les champs HTML, mais il fait confiance aux adresses, aux classes et aux styles que
vérifie le normaliseur. Un bloc lu par `Document::fromArray()` ou `JsonDecoder::decode()` seul pourrait donc introduire
un lien `javascript:` dans la page. Donnez le normaliseur avec lequel l'application lit ses documents, pour qu'il
connaisse les mêmes types ; un bloc qui enfreint son type lève `Phil\Ujo\Exception\InvalidDocument`. Un bloc ajouté
est enveloppé dans `<ins>`, un bloc retiré dans `<del>` (les deux peuvent contenir des blocs en HTML5) ; tout autre
bloc est la différence de son ancien et de son nouveau HTML par `Tokenizer::Html`, comme le décrit [html.md](html.md),
ce qui montre les changements des blocs imbriqués à l'intérieur de leur conteneur. Un bloc dont le normaliseur ou le
moteur de rendu ne connaît pas le type lève `Phil\Ujo\Exception\UnknownBlockType`.

La classe se termine par la valeur de la sorte de changement. Une feuille de style peut, par exemple, marquer le côté
des blocs modifiés :

```css
.text-diff-added, .text-diff-removed, .text-diff-changed, .text-diff-moved { border-inline-start: 3px solid; padding-inline-start: .5rem; }
.text-diff-added { border-color: #2da44e; }
.text-diff-removed { border-color: #cf222e; }
.text-diff-changed { border-color: #bf8700; }
.text-diff-moved { border-color: #0969da; }
```
