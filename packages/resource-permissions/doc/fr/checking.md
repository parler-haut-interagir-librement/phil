<!-- phil-dev source-sha256=ff0c47e51841bf4f66e778d0fcceccedd0d7985210e05d10d39b42cb95aeb9f9 -->
# Vérifier et lister

`RelationChecker` implémente `Contract\RelationCheck` à partir d'un `Model`, d'un `TupleStore` et d'un `Budget`.

## `check()` et `explain()`

```php
$checker->check($member, 'viewer', $doc);   // bool
$checker->explain($member, 'viewer', $doc); // ?Explanation
```

Une vérification parcourt la réécriture de la relation :

- **directe** : une lecture du magasin trouve le sujet lui-même, et tous les objets de son type quand le sujet est un
  objet ; puis chaque ensemble de sujets écrit sur la relation — `group:staff#member` — est vérifié à son tour ;
- **calculée** : l'autre relation, sur le même objet ;
- **issue d'un objet lié** : chaque objet vers lequel la relation suivie pointe, et la relation lue sur lui ;
- l'**union** répond oui au premier opérande qui répond oui ; l'**intersection** exige chaque opérande ; l'**exclusion**
  exige la base et non le côté soustrait.

L'`Explanation` contient les tuples suivis, de l'objet au sujet : `doc:1#parent@folder:sub`,
`folder:sub#parent@folder:root`, `folder:root#editor@group:g#member`, `group:g#member@member:ann`. Une intersection
donne les tuples de chacun de ses opérandes, l'un après l'autre. `lines()` les écrit en texte, pour montrer *pourquoi*
un membre voit un élément.

Un sujet peut être un ensemble : `check(Subject::set($group, 'member'), 'viewer', $doc)` dit si les membres du groupe,
pris ensemble, se voient donner la relation.

## `objects()`

```php
$list = $checker->objects($member, 'viewer', 'doc', limit: 100);
$list->objects;  // list<ResourceReference>, ordered by identifier
$list->ids();    // list<string>, for WHERE id IN (:ids)
$list->complete; // false when cut
```

Une liste parcourt les réécritures à rebours : du sujet aux groupes auxquels il appartient, aux dossiers que ces groupes
peuvent voir, puis aux documents qu'ils contiennent, chaque étape étant une lecture indexée du magasin. Un groupe dans
un groupe, ou toute relation qui revient sur elle-même, est rassemblé par tours jusqu'à ce qu'un tour n'ajoute rien.
Chaque candidat est ensuite vérifié avec `check()` : la liste ne contient donc jamais un objet qu'une vérification
refuserait. Une exclusion ou une intersection filtre les candidats de son premier opérande.

La limite va de 1 à 1 000. La liste est incomplète quand plus d'objets réussissent la vérification que la limite, ou
quand les candidats atteignent le `maxCandidates` du budget ; une application qui parcourt davantage de pages utilise
une requête à elle sur les tuples, ou une relation plus étroite.

## Le budget

```php
new Budget(maxDepth: 32, maxReads: 500, maxFanOut: 200, maxCandidates: 1000);
```

| Limite | Valeur par défaut | Borne |
|---|---|---|
| `maxDepth` | 32 | Les relations suivies l'une dans l'autre. |
| `maxReads` | 500 | Les lectures du magasin par une vérification, ou par le rassemblement d'une liste. |
| `maxFanOut` | 200 | Les ensembles de sujets, ou les objets liés, derrière une relation. Les sujets qui sont un seul objet ne comptent pas : une seule lecture les trouve. |
| `maxCandidates` | 1 000 | Les objets qu'une liste examine. |

Chaque limite va de 1 à 100 000. Une vérification ou une liste qui dépasse une limite lève `TraversalBudgetExceeded` :
la réponse est inconnue, et l'appelant refuse l'accès. Les voters de `phil/access-bundle` refusent et le journalisent.

## Cycles

Une étape déjà en cours de parcours — un groupe qui se contient lui-même par un autre, un dossier qui est son propre
ancêtre — est un cycle : ce chemin répond non, et le parcours continue avec les autres. Un non trouvé pendant qu'un
cycle était coupé n'est jamais retenu pour plus tard dans le même appel, puisqu'un autre chemin peut répondre oui. Une
exclusion dont le côté soustrait a rencontré un cycle répond non : un non qui peut venir de la coupure n'accorde jamais
l'accès.

## Expiration

Un tuple avec une expiration cesse de compter à cette seconde, selon l'horloge du magasin : aucune lecture ne le
retourne, donc chaque vérification et chaque liste l'ignorent aussitôt, sans tâche planifiée. `TupleStore::purgeExpired()`
supprime les lignes expirées plus tard.

## Performance

Une vérification coûte une lecture par étape parcourue : la profondeur de l'arbre et des groupes, jamais le nombre de
tuples. Une liste coûte une lecture par détenteur de la relation par laquelle elle rassemble, puis une vérification par
candidat. `tests/Benchmark/RelationCheckerBench.php` mesure les deux sur une chaîne de 4 à 16 dossiers avec des groupes
imbriqués.
