<!-- phil-dev source-sha256=69ff4be69d54dd742d7a0dcf27b5a08abae8e549c8931da974b9c4255b45d3c2 -->
# Exceptions

Chaque exception implémente `Phil\Ujo\Exception\UjoException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidJson` | `InvalidArgumentException` | Un texte au-delà de la limite de taille, qui n'est pas du JSON, imbriqué plus profondément que la limite, ou dont le niveau supérieur n'est pas un objet |
| `InvalidDocument` | `InvalidArgumentException` | La structure d'un document ou d'un bloc, ou les données d'un bloc, enfreignent le modèle ; un texte qui ne peut pas s'écrire en JSON. Son `path` dit où, comme `blocks[2].data.level` |
| `UnknownBlockType` | `InvalidArgumentException` | Un bloc ou un réglage d'aucun type enregistré, ou un bloc sans moteur de rendu. Son `type` le nomme |
| `InvalidConfiguration` | `LogicException` | Un profil en ligne qui n'est pas enregistré |

Les messages disent où et pourquoi, par exemple :

- `The UJO JSON cannot be parsed: Syntax error.`
- `The UJO document is invalid at blocks[0].data.level: 7 is above the maximum 6.`
- `The UJO document is invalid at blocks[3].id: the identifier "a1" is already used by the block at blocks[0].`
- `The block type "raw" at blocks[1] is not registered.`
- `No HTML renderer is registered for the block type "callout".`

Une application qui reçoit des documents de rédacteurs attrape `UjoException` et affiche le message : il nomme le bloc
et la valeur en faute.
