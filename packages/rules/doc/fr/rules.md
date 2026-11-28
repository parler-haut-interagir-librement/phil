<!-- phil-dev source-sha256=c0e48e06f7e5a494e4f8755e5e7e5c07849ec99281a94905a50dbf0dc5a02f5e -->
# Règles

## L'arbre

| Nœud | Vrai lorsque |
|---|---|
| `All(...$conditions)` | chaque condition tient ; évaluée dans l'ordre, elle s'arrête à la première qui échoue |
| `Any(...$conditions)` | au moins une condition tient ; évaluée dans l'ordre, elle s'arrête à la première qui tient |
| `Not($condition)` | sa condition échoue |
| `Leaf($type, $operands)` | le type de condition nommé `$type` tient pour ses opérandes |

`All` et `Any` contiennent au moins une condition : une règle vide est refusée plutôt que de recevoir un sens par
convention. La bibliothèque n'évalue que ces quatre classes.

## Les faits

Une règle ne lit jamais rien d'elle-même : l'appelant passe une portée, une `FactSource`, et chaque condition lit par
son nom les faits dont elle a besoin. `Facts::of()` prend des valeurs, ou des fermetures calculées la première fois
qu'une condition les lit :

```php
use Phil\Rules\Fact\Facts;

$facts = Facts::of([
    'country' => $address->country,
    'orders' => static fn (): int => $orders->countFor($customer), // computed only if a condition reads it
]);
```

Une portée vit le temps d'une évaluation, ou des évaluations d'un même sujet. Une application peut implémenter
elle-même `FactSource`, par exemple au-dessus d'une entité, pourvu qu'elle ne conserve rien d'une requête à la
suivante.

## Les types intégrés

`ConditionTypes::builtIn()` enregistre six comparaisons génériques. Chacune lit le fait nommé par son opérande `fact` et
échoue avec la raison `fact.missing` lorsque la portée ne le contient pas.

| Type | Opérandes | Vrai lorsque |
|---|---|---|
| `fact.equals` | `fact`, `value` (scalaire) | le fait est la valeur ; les nombres se comparent par valeur, `2` et `2.0` de même, tout le reste doit être identique |
| `fact.in` | `fact`, `values` (scalaires) | le fait est l'une des valeurs |
| `fact.contains` | `fact`, `value` (scalaire) | le fait est une liste qui contient la valeur |
| `fact.compare` | `fact`, `operator` (`lt`, `lte`, `gt`, `gte`), `value` (nombre) | le fait est un nombre dans cette relation avec la valeur |
| `fact.present` | `fact` | la portée contient le fait et il n'est pas nul |
| `date.compare` | `fact`, `operator` (`before`, `after`, `on-or-before`, `on-or-after`), `date` (`YYYY-MM-DD`) | la date du calendrier du fait, dans son propre fuseau horaire, est dans cette relation avec la date |

Les dates se comparent comme des textes `YYYY-MM-DD`, jamais comme des objets. Un fait d'un mauvais genre échoue avec la
raison `fact.type`, et les détails nomment le genre attendu.

Les paquets ajoutent leurs propres types : un paquet d'audience déclare `ticket.holder`, une boutique `cart.total`.
Voir [Points d'extension](extension-points.md).

## Le résultat

`Rule::evaluate()` renvoie un `Result` :

- `passed` : la réponse ;
- `evaluations` : le nombre de feuilles évaluées ;
- `trace` : une `Step` par condition, évaluée ou sautée, avec le pointeur JSON de son nœud (`/condition/all/1/not`),
  son genre, son issue et, pour une feuille, son type, son code de raison et ses détails ;
- `explanation()` : les feuilles qui ont décidé. Lorsque la règle échoue, ce sont celles de la branche qui a échoué :
  l'enfant en échec d'un `All` en échec, chaque enfant d'un `Any` en échec, la condition d'un `Not` qui tenait.

```php
foreach ($result->explanation() as $step) {
    printf("%s: %s %s\n", $step->path, $step->type, $step->reason); // /condition/all/0: fact.in fact.unlisted
}
```

Les codes de raison sont des textes stables qu'une application traduit. Les détails des types intégrés nomment des faits
et des opérateurs, jamais la valeur qu'un fait contenait, de sorte qu'une trace peut être journalisée ;
`Step::toArray()` donne toute la trace sous forme de tableaux simples.

## Limites

`Limits` borne une règle ; au-delà d'une borne, `LimitExceeded` est lancée.

| Borne | Valeur par défaut | Vérifiée |
|---|---|---|
| `maxBytes` | 65 536 | à la lecture de la forme JSON |
| `maxDepth` | 16 | à la lecture ou à la construction d'une règle |
| `maxNodes` | 256 | à la lecture ou à la construction d'une règle |
| `maxEvaluations` | 256 | à chaque évaluation, en comptant les feuilles évaluées |

L'arbre d'une règle est fini et immuable : son évaluation se termine, et les bornes gardent cette fin proche. Chaque
type de condition lit ses opérandes et les faits qu'on lui donne, et n'appelle aucune règle à son tour.
