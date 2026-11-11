<!-- phil-dev source-sha256=7f844ec0efa39327e9026ee43e2ac5bccc723cc8a1581c0fec0a6cffba1c948b -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Une application y ajoute des nœuds, des règles et des
types. Elle peut implémenter le collecteur dans lequel les parties d'une page versent leurs nœuds.

## `NodeCollector`

`Phil\StructuredData\Contract\NodeCollector` — l'endroit où les parties d'une application ajoutent les nœuds d'une
page.

| Méthode | Contrat |
|---|---|
| `add(Node ...$nodes): void` | Garde les nœuds pour le graphe de la page en construction, fusionnés par `@id` avec les autres |

Un paquet qui décrit quelque chose sur une page — une fiche de contact, un événement, un produit — dépend de cette
seule interface, jamais d'un cadriciel : s'il reçoit un collecteur, il y ajoute son nœud ; s'il n'en reçoit aucun, il
peut imprimer son propre élément de script. Dans une application Symfony, phil/structured-data-bundle fournit le
collecteur de la requête en cours.

## `TypeRules`

`Phil\StructuredData\Validation\TypeRules` — ce dont un nœud d'un type a besoin, ce qu'il devrait avoir, et son
parent.

```php
$validator = new Validator()->withRules(
    new TypeRules('Recipe', required: ['name', 'recipeIngredient'], recommended: ['image', 'totalTime'], parent: 'CreativeWork'),
);
```

| Argument | Contrat |
|---|---|
| `type` | Le type schema.org auquel les règles s'appliquent |
| `required` | Des propriétés, ou des listes de propriétés dont une seule suffit, dont l'absence est une erreur |
| `recommended` | La même chose, mais l'absence est un avertissement |
| `parent` | Un type dont les règles s'appliquent aussi ; une boucle de parents n'est suivie qu'une fois |

`new Validator($rules)` prend l'ensemble complet au lieu de `Rules::schemaOrg()` ; `withRules()` ajoute des règles à
l'ensemble actuel et remplace celles des mêmes types.

## Autres types

Un type qu'aucune fabrique ne construit s'écrit `Thing::of('Recipe', …, properties: [...])` ou
`Node::of('Recipe', [...])`. Ses propriétés sont libres ; le validateur vérifie les valeurs des propriétés qu'il
connaît par leur nom, quel que soit le type.
