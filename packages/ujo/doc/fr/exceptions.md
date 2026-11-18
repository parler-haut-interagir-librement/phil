<!-- phil-dev source-sha256=702b00b4f81adac8b4a02ae8d3c59ea046759c3e9d021a38b451932ce9b52841 -->
# Exceptions

Chaque exception implémente `Phil\Ujo\Exception\UjoException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidJson` | `InvalidArgumentException` | Un texte au-delà de la limite de taille, qui n'est pas du JSON, imbriqué plus profondément que la limite, ou dont le niveau supérieur n'est pas un objet |
| `InvalidDocument` | `InvalidArgumentException` | La structure d'un document ou d'un bloc, ou les données d'un bloc, enfreignent le modèle ; un texte qui ne peut pas s'écrire en JSON. Son `path` dit où, comme `blocks[2].data.level` |
| `UnknownBlockType` | `InvalidArgumentException` | Un bloc ou un réglage d'aucun type enregistré, ou un bloc sans moteur de rendu. Son `type` le nomme |
| `InvalidConfiguration` | `LogicException` | Un profil en ligne qui n'est pas enregistré |
| `InputTooLarge` | `InvalidArgumentException` | Un fragment HTML donné à l'assainisseur en ligne (pour être assaini, rendu ou converti en texte) est plus long que son plafond, `maxBytes`, 4 Mio par défaut ; `$bytes` et `$limit` donnent les deux longueurs |

Les messages disent où et pourquoi, par exemple :

- `The UJO JSON cannot be parsed: Syntax error.`
- `The UJO document is invalid at blocks[0].data.level: 7 is above the maximum 6.`
- `The UJO document is invalid at blocks[3].id: the identifier "a1" is already used by the block at blocks[0].`
- `The block type "raw" at blocks[1] is not registered.`
- `No HTML renderer is registered for the block type "callout".`
- `The input is 4194305 bytes long, above the limit of 4194304 bytes.`

Une application qui reçoit des documents de rédacteurs attrape `UjoException` et affiche le message : il nomme le bloc
et la valeur en faute.
