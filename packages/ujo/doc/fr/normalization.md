<!-- phil-dev source-sha256=7088a34ad3c2d356e0845900d1df8221013601baea562e819e5e85d4f87cfe03 -->
# Normalisation

`Normalizer::normalize()` vérifie chaque bloc d'un document selon son type et retourne le document sous forme
canonique. `Ujo::decode()`, `encode()`, `toHtml()` et `toText()` l'appellent tous. Un document que le normaliseur a
retourné est déjà canonique : redonné au même normaliseur (rendu ou encodé après avoir été décodé), il est retourné tel
quel, sans seconde vérification, sauf s'il est demandé à un niveau de validation auquel il n'a pas été vérifié.

## Les niveaux de validation

Un document se vérifie à l'un de deux niveaux de `Validation`, donné à `Normalizer::normalize()`, `Ujo::normalize()`
et `Ujo::decode()` :

| Niveau | Vérifie | Quand |
|---|---|---|
| `Validation::Shape`, par défaut | la structure, chaque valeur de son type et de sa forme, les valeurs qu'un type exige présentes, les bornes qu'une valeur respecte pour être rendue (un niveau de titre de 1 à 6), les limites plus bas, l'assainissement | chaque enregistrement, brouillon compris |
| `Validation::Publication` | la forme, et ce qu'un document doit contenir pour être lu : les valeurs qu'un type exige remplies, les bornes qu'il pose aux textes et aux listes, son crochet `publication` | la publication, la planification, l'envoi en relecture |

```php
$draft = $ujo->decode($json);                                   // saved as it is being written
$ready = $ujo->decode($json, Validation::Publication);          // refused while a heading is empty
```

`encode()`, `toHtml()` et `toText()` vérifient la forme : un brouillon peut être prévisualisé. Un document vérifié
pour la publication a aussi passé la forme, et n'est pas vérifié de nouveau pour elle ; un document vérifié pour sa
forme est vérifié de nouveau quand on le demande pour la publication.

Ce que les types standard exigent pour être publiés :

| Type | Pour publier |
|---|---|
| `header`, `quote` | un texte qu'un lecteur voit, pas seulement des balises ou des espaces |
| `list` | un élément au moins, le contenu de chaque élément rempli, éléments imbriqués compris |
| `code` | du code autre que des espaces |
| `table` | une rangée au moins |
| `personality` | un nom |

## Ce qu'il fait, bloc par bloc

1. **Lit la structure** du bloc (`Block::fromArray()`).
2. **Trouve le type** par son nom ou son alias dans son `BlockTypeRegistry` ; un type inconnu lance `UnknownBlockType`.
   Le bloc est compté : au-delà de `maxBlocks` blocs dans le document, blocs imbriqués compris (10 000 par défaut), ou
   au-delà du `maxPerDocument` de son type, il est refusé.
3. **Replie les enfants plats** : un type avec un crochet `fold` (`toggle`, pour les blocs bascule et repliables) dit
   combien des blocs suivants lui appartiennent ; ils passent dans `data.blocks`. Un nombre plus grand que ce qui
   suit, ou négatif, lance `InvalidDocument`.
4. **Note l'identifiant**, et refuse celui qui est déjà utilisé ailleurs dans le document.
5. **Met à niveau** les formes anciennes et tierces avec le crochet `upgrade` du type, qui voit le nom sous lequel le
   bloc a été enregistré.
6. **Vérifie les données** selon la `Shape` du type : valeurs obligatoires présentes, aucune valeur inconnue (sauf si
   la forme est ouverte), chaque valeur conforme à son champ (assainie, bornée, de sa forme), valeurs par défaut
   écrites, valeurs dans l'ordre de la forme.
7. **Exécute le crochet `check`** pour les règles entre valeurs : des rangées de tableau d'une même longueur, une
   intégration sur un hôte de son service.
8. **Pour la publication**, vérifie les valeurs déclarées `filled()` et les bornes des champs `Bounded` à la lecture
   de chaque forme, puis **exécute le crochet `publication`** pour les règles entre valeurs qu'un document publié
   respecte.
9. **Vérifie les réglages** par leur nom ou leur alias dans son `TuneRegistry`, chacun sous son nom canonique.

Les blocs imbriqués passent par les mêmes étapes, un niveau plus bas ; au-delà de `maxDepth` niveaux (8 par défaut),
ils sont refusés. Chaque bloc garde son identifiant ; le type devient le nom canonique.

## Les limites

Les limites bornent ce que coûte le rendu d'un document, pas seulement sa taille : chaque champ HTML est assaini de
nouveau à chaque rendu, et une table des matières ou une liste d'abréviations lit le document entier.

| Limite | Valeur par défaut | Refuse |
|---|---|---|
| `maxDepth` | 8 (`DEFAULT_MAX_DEPTH`) | les blocs imbriqués plus profondément |
| `maxBlocks` | 10 000 (`DEFAULT_MAX_BLOCKS`) | un document de plus de blocs, blocs imbriqués compris |
| `maxHtmlLength` | 100 000 (`DEFAULT_MAX_HTML_LENGTH`) | un champ HTML plus long, en caractères, avant son assainissement |
| `maxRawLength` | 50 000 (`DEFAULT_MAX_RAW_LENGTH`) | la même chose pour un champ du profil `raw`, le HTML d'un bloc `raw` |
| `BlockType::$maxPerDocument` | 10 pour `toc` et `abbreviations`, aucune sinon | plus de blocs du type dans un même document |

Les erreurs nomment leur chemin, `blocks[2].data.items[0].content`. Un enfant replié dans une bascule est nommé sous
sa bascule, `blocks[0].data.blocks[1]`.

## Configurer

```php
$normalizer = new Normalizer(
    types: BlockTypeRegistry::default()->with(StandardTypes::raw())->without('chart'),
    tunes: TuneRegistry::default(),
    sanitizer: new InlineSanitizer(['rich' => $myProfile]),
    maxDepth: 4,
    maxBlocks: 500,
    maxHtmlLength: 20_000,
    maxRawLength: 10_000,
);
```

Le registre par défaut contient chaque type de [block-types.md](block-types.md) sauf `raw` : le HTML brut n'est
accepté que là où il est ajouté.

## Ajouter un type

Un type est un nom, une `Shape` de valeurs `Property` et, au besoin, les quatre crochets et un `maxPerDocument`, le
nombre maximal de blocs du type qu'un document peut contenir ; donnez-en un au type dont le moteur de rendu lit le
document entier :

```php
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Schema\{Bounded, Choice, Html, ListOf, Plain, Property, Shape};
use Phil\Ujo\Type\BlockType;

$callout = new BlockType('callout', new Shape([
    'tone' => Property::defaults(new Choice(['calm', 'loud']), 'calm'),
    'text' => Property::required(new Html())->filled(),
    'points' => Property::defaults(new Bounded(new ListOf(new Plain(200)), 1, 5), []),
]), aliases: ['Callout'], publication: static function (array $data, string $path): void {
    if ('loud' === $data['tone'] && [] === $data['points']) {
        throw InvalidDocument::at($path.'.points', 'a loud callout lists its points to publish');
    }
});

$registry = BlockTypeRegistry::default()->with($callout);
```

| Champ | Valeur |
|---|---|
| `Html($profile = 'rich')` | un texte, assaini avec un profil, d'au plus `maxHtmlLength` caractères (`maxRawLength` pour `raw`) |
| `Plain($maxLength, $pattern)` | un texte gardé tel quel ; `Plain::token()`, `length()`, `percentage()`, `color()` |
| `Integer($min, $max)` | un nombre entier |
| `Boolean` | `true` ou `false` |
| `Choice($choices)` | un texte d'une liste |
| `Link($schemes, $relative)` | une adresse de ces schémas, ou relative |
| `ListOf($item, $min, $max)` | une liste de valeurs d'un même champ |
| `Shape($properties, $open)` | un objet ; ouverte, elle garde les valeurs inconnues après celles qui sont déclarées |
| `Blocks` | des blocs imbriqués |
| `Lazy($factory)` | un champ construit à l'usage, pour les formes qui se contiennent elles-mêmes |
| `Bounded($field, $min, $max)` | un champ `Sized` dont la taille reste dans des bornes pour la publication ; un brouillon peut être plus court ou plus long |

`Property::required()`, `optional()` (omise quand elle est absente) et `defaults()` (écrite avec sa valeur par défaut
quand elle est absente), chacune avec `->nullable()` pour accepter `null` et `->filled()` pour être remplie à la publication : présente, autre
que `null`, d'une taille supérieure à zéro quand son champ est `Sized`, non vide sinon. Une valeur absente et écrite
avec sa valeur par défaut est vérifiée aussi.

`Html`, `Plain`, `Link`, `ListOf`, `Blocks`, `Dictionary` et `Bounded` sont `Sized` : `size()` compte les caractères
qu'un lecteur voit d'un texte (balises omises, références de caractères lues, espaces des deux bouts omis), les
éléments d'une liste, les blocs ou les entrées ; `unit()` nomme ce qu'elle compte, pour les messages. Un champ à vous
l'implémente pour être borné. Donnez aussi un moteur de rendu au nouveau
type ([html-rendering.md](html-rendering.md)).
