<!-- phil-dev source-sha256=3413b3832e5c94198f2d7de67e4369279bc9a1215e64c84434c2b7c19362e413 -->
# Points d'extension

Ce qu'une application configure, remplace ou utilise. Les ressources sont des références de `phil/resource`, et les
*slugs* sont ceux de `phil/slug`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `slugger` | L'identifiant de service du générateur de *slugs* qui produit les *slugs* des étiquettes et les clés de catégorie : un générateur nommé de `phil/slug-bundle`, `phil_slug.slugger.tags`, ou un générateur décoré ; `null` pour le générateur par translittération |
| `fallback_locale` | La langue du nom d'une catégorie quand il n'y en a aucun dans la langue demandée |

Voir [Étiquettes](tags.md) et [Catégories](categories.md).

## Les ressources de l'application

Toute ressource est étiquetée ou classée par sa référence — `article`, `media.image`, les sortes que choisit
l'application — et `taggedWith()` les relit par sorte : la taxonomie n'en sait rien d'autre. Un bundle qui étiquette
ses propres ressources, comme `phil/calendar-bundle` le fait pour ses événements, choisit une sorte qui lui est propre.

## `TaxonomyRepository`

`Phil\TaxonomyBundle\Domain\Repository\TaxonomyRepository` — étiquettes, catégories, traductions et attributions, un
alias public de `DoctrineTaxonomyRepository`. Un stockage propre à l'application garde les méthodes par lot —
`tags()`, `categoriesWithIds()`, `translationsOf()` — qui en lisent beaucoup d'un coup, et `branchIds()`, qui lit
toute la branche en une seule lecture : les déplacements restent ainsi sans cycle sans lire l'arbre nœud par nœud.

## Services et formulaires

| Point | Comment |
|---|---|
| `Taxonomy` | Injecté pour trouver ou créer, renommer, déplacer et fusionner, et pour étiqueter les ressources |
| `TagsType` | Les étiquettes en texte, séparées par des virgules |
| `CategoryType` | Une catégorie choisie dans l'arbre |
| Plus que ce que fait un champ | Une extension de type de formulaire de l'application sur l'un ou l'autre type — voir [Formulaires](forms.md) |
