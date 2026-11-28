<!-- phil-dev source-sha256=b0487ed6d1dc065ad30f81e1c6c7b81c914d81e6fccaa155e2d693c6071dba8c -->
# Forme JSON

Une règle est stockée et échangée comme un objet JSON versionné :

```json
{
    "version": 1,
    "condition": {
        "all": [
            {"type": "fact.in", "operands": {"fact": "country", "values": ["CA", "FR"]}},
            {"not": {"type": "fact.present", "operands": {"fact": "unsubscribed_at"}}},
            {"any": [
                {"type": "fact.compare", "operands": {"fact": "orders", "operator": "gte", "value": 2}},
                {"type": "date.compare", "operands": {"fact": "joined", "operator": "before", "date": "2026-01-01"}}
            ]}
        ]
    }
}
```

Une condition est l'une de ces formes :

| Forme | Nœud |
|---|---|
| `{"all": [ … ]}` | `All`, une liste non vide |
| `{"any": [ … ]}` | `Any`, une liste non vide |
| `{"not": { … }}` | `Not` |
| `{"type": "…", "operands": { … }}` | `Leaf` ; `operands` peut être omis lorsque le type n'en demande aucun |

## Lecture

`RuleReader::fromJson()` lit le texte, `fromArray()` la même forme déjà décodée, comme le corps d'une requête d'API. Les
deux refusent avec `InvalidRule` :

- tout autre contenu que `version` et `condition` au sommet, une version autre que 1 ;
- une condition qui n'est pas un objet, qui contient plus d'une forme, ou toute autre clé ;
- un `all` ou un `any` vide ou qui n'est pas une liste, des opérandes donnés sous forme de liste ;
- un type que le registre ne contient pas, un opérande que le schéma ne déclare pas, un opérande requis manquant, une
  valeur que le genre de l'opérande refuse.

Le message nomme le pointeur JSON du nœud fautif. Les bornes de `Limits` s'appliquent pendant la lecture : les octets
avant le décodage, la profondeur du JSON pendant le décodage, la profondeur et les conditions pendant la construction.

## Écriture

`RuleWriter::toJson()` écrit la forme canonique : les opérandes dans l'ordre où leur schéma les déclare, les nombres de
genre `number` avec leur point décimal, les textes en UTF-8 sans échapper les barres obliques. Lire la forme canonique
redonne la même règle, qui écrit le même texte. `toArray()` donne la même forme sous forme de tableaux.

`RuleWriter::fingerprint()` est le SHA-256 de la forme canonique : deux règles de même empreinte disent la même chose.
Une liste calculée à partir d'une règle garde l'empreinte pour prouver quelle règle l'a sélectionnée.

## Versions

La version est celle de la forme, non celle de la règle. Une forme ultérieure viendra avec son propre numéro, et cette
bibliothèque refuse tout numéro qu'elle ne lit pas plutôt que de deviner.
