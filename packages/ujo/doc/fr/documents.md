<!-- phil-dev source-sha256=c8538dfcc9b28ba4ee4aaa96832574bb82e4090d3e37602c2aa2cdcd4e3ddeea -->
# Documents

## La forme JSON

Un document en UJO (Universal JSON Output) est un objet JSON fait d'une heure d'enregistrement, d'une version
facultative et de blocs ordonnés :

```json
{
  "time": 1762387200000,
  "blocks": [
    {"id": "oUq2g_tl8y", "type": "paragraph", "data": {"text": "Hello <b>world</b>"}, "tunes": {"alignment": {"alignment": "left"}}}
  ],
  "version": "2.31.7"
}
```

| Clé | Obligatoire | Forme |
|---|---|---|
| `time` | non | l'heure de l'enregistrement, en millisecondes, un entier positif |
| `blocks` | oui | une liste de blocs |
| `version` | non | la version de l'éditeur, un texte |
| `id` du bloc | oui | de 1 à 64 lettres, chiffres, `_` ou `-`, unique dans le document, blocs imbriqués compris |
| `type` du bloc | oui | un nom qui commence par une lettre, de 64 caractères au plus : le type, ou l'un de ses alias |
| `data` du bloc | oui | un objet, dont le type décide la forme |
| `tunes` du bloc | non | un objet de réglages nommés |

Aucune autre clé n'est acceptée, à aucun des deux niveaux.

## Valeurs

`Document` et `Block` sont des classes `final readonly`. Leurs constructeurs prennent les valeurs telles quelles ;
`Document::fromArray()` et `Block::fromArray()` vérifient la structure ci-dessus et lancent `InvalidDocument` avec le
chemin de ce qui ne va pas.

```php
$block = new Block('a1', 'paragraph', ['text' => 'Hello']);
$block = $block->withData(['text' => 'Bye'])->withTunes(['anchor' => ['anchor' => 'bye']]);

$document = new Document([$block], time: 1762387200000, version: '2.31.7');
$document->toArray();     // the JSON form, `time` and `version` left out when unknown
```

La structure n'est pas les données : un `Block` peut contenir n'importe quelles données. `Normalizer` vérifie les
données de chaque bloc selon son type ([normalization.md](normalization.md)), et `Ujo::decode()` fait les deux.

## Blocs imbriqués

Un type peut contenir des blocs dans ses données : les colonnes de `columns`, le contenu de `toggle`, `note`,
`descriptive`, `wide`, `columnFlow` et `container`, les sections de `statblock`. Une fois normalisés, ce sont des
objets `Block` à l'intérieur de `data`.

```php
$block->children();                  // the blocks nested directly in this one, in order
$document->walk();                   // every block, depth first, a block before those it holds
$document->find('oUq2g_tl8y');       // a block by identifier, wherever it is, or null
```

`toArray()` écrit les blocs imbriqués sous leur propre forme JSON.

## Lire et écrire le JSON

`JsonDecoder` lit strictement :

- un texte plus long que sa limite (4 Mio par défaut) est refusé avant l'analyse ;
- un JSON invalide, ou imbriqué plus profondément que sa limite (64 par défaut), est refusé avec la raison donnée par
  l'analyseur ;
- un niveau supérieur qui n'est pas un objet est refusé ;
- l'objet doit ensuite avoir la structure ci-dessus.

```php
$document = new JsonDecoder(maxBytes: 1_048_576, maxDepth: 32)->decode($json);
```

Un nombre trop grand pour un entier est lu comme un texte : un `time` au-delà des entiers de PHP est donc refusé
plutôt qu'arrondi. Un objet vide et une liste vide ne se distinguent plus une fois décodés : les deux sont acceptés là
où l'un ou l'autre l'est.

`JsonEncoder` écrit la forme JSON canonique : l'UTF-8 et les barres obliques non échappés, `1.0` gardé comme nombre à
virgule flottante, et les valeurs vides de `data`, `tunes`, `meta`, `file` et `settings` écrites `{}`. Un texte qui
n'est pas de l'UTF-8 valide ne peut pas être écrit : `InvalidDocument` le dit. `encode($document, pretty: true)` met la
sortie en retrait.

`Ujo::encode()` normalise avant d'écrire : ce qui est stocké est donc toujours canonique.
