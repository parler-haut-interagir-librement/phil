<!-- phil-dev source-sha256=d534419cff43c22ca71d59aac3e0c635c76d2953e5e77fedef4b6df65030e0c8 -->
# Exceptions

Chaque exception implémente `Phil\Ujo\Exception\UjoException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidJson` | `InvalidArgumentException` | Un texte au-delà de la limite de taille, qui n'est pas du JSON, imbriqué plus profondément que la limite, ou dont le niveau supérieur n'est pas un objet |
| `InvalidDocument` | `InvalidArgumentException` | La structure d'un document ou d'un bloc, ou les données d'un bloc, enfreignent le modèle ; un texte qui ne peut pas s'écrire en JSON. Son `path` dit où, comme `blocks[2].data.level` |
| `UnknownBlockType` | `InvalidArgumentException` | Un bloc ou un réglage d'aucun type enregistré, ou un bloc sans moteur de rendu. Son `type` le nomme |
| `InvalidConfiguration` | `LogicException` | Un profil en ligne qui n'est pas enregistré |
| `InputTooComplex` | `InvalidArgumentException` | Un fragment HTML donné à l'assainisseur en ligne compte plus de balises et de commentaires que `maxTokens`, 100 000 par défaut, ou imbrique ses éléments plus profondément que `maxDepth`, 512 par défaut ; `$budget` nomme le budget, `tokens` ou `depth`, et `$limit` sa valeur |
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
