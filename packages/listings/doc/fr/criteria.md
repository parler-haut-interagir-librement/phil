<!-- phil-dev source-sha256=e51070dad432ef1597120311d7fceb6d9fe1d19cdfa8f0476149ce9b59a66426 -->
# Critères

Un critère est un nom que définit l'application (des segments ASCII minuscules d'au plus 64 octets) et une valeur de
l'un de trois types :

| Type | Construit par | Contient | Correspond à un critère recherché de même nom et de même type quand |
|---|---|---|---|
| Choix | `Criterion::choice($name, $choices)` | de 1 à 32 valeurs distinctes d'au plus 64 lettres ASCII, chiffres, `.`, `_`, `:` ou `-`, dans l'ordre donné | ils partagent au moins une valeur |
| Plage | `Criterion::range($name, $min, $max)` | deux entiers, bornes comprises, `$min` n'étant pas supérieur à `$max` | ils partagent au moins un entier |
| Indicateur | `Criterion::flag($name, $flag)` | oui ou non | ils sont égaux |

Les valeurs d'un choix sont des clés, pas des libellés : l'application les traduit, et la même clé veut dire la même
chose dans toutes les langues. Une plage contient des nombres entiers : une fourchette de salaire en unités entières, un
nombre de joueurs, une tranche d'âge. Les champs qu'un type n'utilise pas sont vides : un choix n'a pas de bornes, une
plage n'a pas de choix.

```php
use Phil\Listings\Criteria;
use Phil\Listings\Criterion;

$listing = Criteria::of([
    Criterion::choice('day', ['sat', 'sun']),
    Criterion::range('players', 3, 5),
    Criterion::flag('beginners', true),
]);

$search = Criteria::of([Criterion::choice('day', ['sun']), Criterion::range('players', 4, 8)]);

$listing->meets($search);   // true: Sunday is shared, and 4 or 5 players fit both
```

`Criteria::of()` garde au plus 32 critères, un par nom. Les critères d'une annonce correspondent à ceux d'une recherche
quand chaque critère que veut la recherche correspond au critère de même nom de l'annonce : une annonce qui n'indique
pas un critère ne correspond pas à une recherche qui le veut, et une recherche qui ne veut rien correspond à toutes les
annonces.

`meets()` est la règle, pas la recherche : une application qui a beaucoup d'annonces les filtre d'abord dans son
stockage ou son moteur de recherche, puis applique `meets()` à ce qu'elle lit, ou indexe la même règle. Son coût est
borné par les 32 critères et les 32 valeurs de chaque côté.
