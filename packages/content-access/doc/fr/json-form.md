<!-- phil-dev source-sha256=94371371dae85c0f216821feaf0e8b422c2127e893801f90053b33b2ee3adfc1 -->
# Forme JSON

Une politique est stockée sous une forme JSON versionnée, lue par `Json\AccessPolicyReader` et écrite par
`Json\AccessPolicyWriter` :

```json
{
    "version": 1,
    "audience": "restricted",
    "rule": {"version": 1, "condition": {"type": "access.feature", "operands": {"feature": "tier:gold"}}},
    "public_from": "2027-01-15T00:00:00+00:00",
    "preview": true,
    "when_denied": "locked"
}
```

| Membre | | |
|---|---|---|
| `version` | requis | `1` |
| `audience` | requis | `public`, `members` ou `restricted` |
| `rule` | pour `restricted` seulement | une règle sous la forme versionnée de `phil/rules` |
| `public_from` | facultatif | un instant RFC 3339 avec un décalage numérique |
| `preview` | facultatif | `true` ou `false`, `false` par défaut |
| `when_denied` | facultatif | `locked`, par défaut, ou `hidden` |

```php
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\Json\AccessPolicyReader;
use Phil\ContentAccess\Json\AccessPolicyWriter;
use Phil\Rules\Json\RuleReader;

$reader = new AccessPolicyReader(new RuleReader(AccessConditions::types()));
$policy = $reader->fromJson($stored);       // or fromArray() for an API body already decoded

$writer = new AccessPolicyWriter();
$writer->toJson($policy);
$writer->fingerprint($policy);
```

## Ce qui est refusé

`InvalidPolicy` est lancée, avec le JSON Pointer du membre fautif, pour :

- un document plus gros que `maxBytes` du lecteur (65 536 par défaut), refusé avant d'être décodé ;
- un document qui n'est pas du JSON, ou imbriqué sur plus de 128 niveaux ;
- tout ce qui n'est pas un objet, et tout membre absent du tableau ;
- une version autre que `1` ;
- un public inconnu, un `preview` qui n'est pas un booléen, un `when_denied` inconnu ;
- un instant sans décalage numérique, avec `Z`, avec des fractions de seconde, ou d'un jour qui n'existe pas ;
- une politique restreinte sans objet de règle, une règle sur tout autre public ;
- une règle refusée par le lecteur de `phil/rules`, son exception gardée comme précédente — un type de condition
  inconnu, un opérande refusé, une règle qui dépasse les limites du lecteur ;
- les contradictions refusées à la construction d'une politique (voir les [politiques](policies.md)).

## La forme canonique

L'écrivain donne les membres dans l'ordre du tableau, la règle sous la forme canonique de `phil/rules`, l'instant en
UTC, et écrit toujours `preview` et `when_denied`. Une forme canonique se relit à l'identique, et `fingerprint()` en
est le SHA-256 en hexadécimal : deux politiques qui signifient la même chose ont la même empreinte.
