<!-- phil-dev source-sha256=2f60979652fb2d0027f4a824ceab3b6cbbe457f6072ba821de226655b863f2b2 -->
# Le modèle

## Sujets

Un `Subject` est celui à qui une relation est donnée :

| Sujet | Écrit | Signifie |
|---|---|---|
| `Subject::of($reference)` | `member:ann` | Un objet. |
| `Subject::every('member')` | `member:*` | Tous les objets d'un type : un seul tuple pour tous les membres. |
| `Subject::set($reference, 'member')` | `group:staff#member` | Tous les sujets qui détiennent `member` sur `group:staff`. |

`Subject::fromString()` relit ce qu'écrit `toString()`. L'identifiant d'un sujet ne contient aucun `#` et n'est jamais
`*` seul, qui nomme tous les objets d'un type. Une `ResourceReference` de `phil/resource` est acceptée partout où un
sujet l'est, et représente cet objet précis.

## Tuples

Un `RelationTuple` est le fait stocké : `$subject` détient `$relation` sur `$object`, jusqu'à `$expiresAt` quand elle
est donnée. Il est connu par son objet, sa relation et son sujet — `key()` écrit `object#relation@subject` — ; l'écrire
de nouveau remplace son expiration. Une relation compte de 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, et
commence par une lettre, comme la règle d'un type de ressource ; les identifiants sont du texte UTF-8.

## Types et relations

Un `Model` contient des `TypeDefinition` : un type et ses relations, chacune avec une `Rewrite` qui dit comment elle
est calculée. Un type qui n'est jamais que sujet, comme `member`, est déclaré sans relations.

| Réécriture | Contient |
|---|---|
| `Rewrite::direct('member', 'member:*', 'group#member')` | Les tuples écrits sur la relation, donnés à des sujets des types nommés. |
| `Rewrite::computed('editor')` | Tout sujet qui détient `editor` sur le même objet. |
| `Rewrite::fromRelated('parent', 'viewer')` | Tout sujet qui détient `viewer` sur un objet vers lequel `parent` pointe. |
| `Rewrite::union($a, $b, …)` | Les sujets de n'importe laquelle d'entre elles. |
| `Rewrite::intersection($a, $b, …)` | Les sujets de chacune d'entre elles. |
| `Rewrite::exclusion($base, $subtract)` | Les sujets de `$base` que `$subtract` ne détient pas. |

Une relation héritée le long d'un arbre est un `fromRelated()` sur une relation `parent` ; un groupe dans un groupe est
un type direct `group#member` sur la relation `member` du groupe lui-même ; un rôle qui en inclut un autre est un
`computed()`.

```php
TypeDefinition::of('doc', [
    'owner'   => Rewrite::direct('member'),
    'parent'  => Rewrite::direct('folder'),
    'blocked' => Rewrite::direct('member'),
    'editor'  => Rewrite::union(Rewrite::direct('member', 'group#member'), Rewrite::computed('owner')),
    'viewer'  => Rewrite::exclusion(
        Rewrite::union(Rewrite::direct('member', 'member:*', 'group#member'), Rewrite::computed('editor'), Rewrite::fromRelated('parent', 'viewer')),
        Rewrite::computed('blocked'),
    ),
])
```

`TypeDefinition::with()` donne le même type avec d'autres relations, et refuse une relation déjà déclarée : une
application ajoute ses propres relations à un type qu'un bundle déclare.

## Ce que le modèle vérifie à la construction

Le constructeur refuse, avec `InvalidRelation` :

- un type déclaré deux fois ;
- un type direct qui nomme un type que le modèle ne déclare pas, ou un ensemble de sujets sur une relation que ce type
  ne déclare pas ;
- une relation `computed()` que le même type ne déclare pas ;
- un `fromRelated()` dont la relation suivie n'est pas une relation directe du type, ou dont les objets — les types
  directs qui sont un seul objet — n'incluent aucun type qui déclare la relation lue.

Ainsi, une vérification, une liste et une écriture ne rencontrent jamais que ce que le modèle déclare. `accepts()` dit
si un tuple peut être donné à un sujet : l'une des réécritures directes de la relation accepte le type du sujet. Une
relation calculée seulement à partir d'autres ne reçoit aucun tuple.

## Écrire des tuples

`RelationWriter` écrit ce que le modèle accepte :

```php
$writer->grant($doc, 'viewer', $member, new DateTimeImmutable('+30 days')); // until that second
$writer->revoke($doc, 'viewer', $member);
$writer->update(revoke: [$oldTuple], grant: [$newTuple]);                   // all of it or nothing
$writer->forget($member);                                                   // every tuple on it or given to it
```

Une mise à jour touche au plus 1 000 tuples. Chaque tuple accordé est vérifié d'abord : un tuple que le modèle
n'accepte pas fait refuser toute la mise à jour, et rien n'est écrit.
