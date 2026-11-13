<!-- phil-dev source-sha256=555226288c12bc1c6bf5cec9711ab7ffdd16b8b33687b5d5ea1e78cefd961d0d -->
# Codes

Un code est une suite de symboles tirés de façon uniforme dans un alphabet, suivie d'un symbole de contrôle.
`CodeFormat` dit à quoi ressemble un code, en tire de nouveaux et lit ce qu'une personne a saisi.

## L'alphabet

`CodeAlphabet` contient de 16 à 36 chiffres et lettres capitales ASCII distincts. La valeur par défaut est la Base32 de
Crockford, `0123456789ABCDEFGHJKMNPQRSTVWXYZ` : ni `I`, ni `L`, ni `O`, qu'un lecteur prend pour `1` et `0`, et ni `U`.

```php
use Phil\AccessCodes\CodeAlphabet;

$alphabet = new CodeAlphabet();                       // Crockford's Base32, 5 bits per symbol
$hexadecimal = new CodeAlphabet('0123456789ABCDEF');  // 4 bits per symbol
```

À la lecture d'un code, le texte est mis en capitales, ses traits d'union et ses espaces sont retirés, et les lettres
que l'on confond avec un chiffre sont lues comme ce chiffre : `O` pour `0`, `I` et `L` pour `1`. Une lettre n'est lue
comme son chiffre que si elle est hors de l'alphabet et que le chiffre y est : un alphabet qui contient `O` la garde.

## Le format

```php
use Phil\AccessCodes\CodeFormat;

$format = new CodeFormat();   // 12 symbols of Crockford's Base32, 60 bits, a check symbol, groups of 4
$format->display('7KQ2M9XDH4TBH');   // "7KQ2-M9XD-H4TB-H"
$format->parse(' 7kq2 m9xd-h4tb-h'); // "7KQ2M9XDH4TBH"

$longer = CodeFormat::withEntropy(80);                    // the shortest format of 80 bits: 16 symbols
$plain = new CodeFormat(length: 10, checkSymbol: false, groupSize: 5);
```

| Argument | Valeur par défaut | Bornes |
|---|---|---|
| `alphabet` | Base32 de Crockford | N'importe quel `CodeAlphabet` |
| `length` | 12 | De 1 à 64 symboles aléatoires, portant au moins 40 bits |
| `checkSymbol` | `true` | — |
| `groupSize` | 4 | De 0 à 64 symboles entre deux traits d'union à l'affichage d'un code ; 0 affiche un seul bloc |

Le plancher de 40 bits vaut quel que soit l'alphabet : 8 symboles de la Base32 de Crockford, 10 en hexadécimal. Avec
l'empreinte à clé du magasin et une limite de tentatives, deviner un code d'un lot d'un million à 40 bits demande environ
un million de tentatives par réussite ; à 60 bits, la valeur par défaut, environ 10¹⁸.

`generate()` tire les symboles avec `Random\Randomizer::getBytesFromString()`, sur le moteur `Secure` sauf si un test en
donne un autre, puis ajoute le symbole de contrôle. La forme canonique d'un code est la suite de ses symboles sans
séparateurs : c'est ce dont on calcule l'empreinte et ce que porte un lien d'échange.

## Le symbole de contrôle

Le symbole de contrôle suit l'algorithme Luhn mod N sur l'alphabet : en partant de la droite, la valeur d'un symbole sur
deux est doublée et ses chiffres en base N sont additionnés, et le symbole de contrôle amène la somme à un multiple
de N. Il repère tout symbole mal saisi et la plupart des permutations de deux voisins, de sorte qu'un code mal recopié
d'une carte imprimée est refusé avant toute recherche.

## Lire un code

`parse()` renvoie la forme canonique, ou lève `InvalidCode`, dont le message ne répète jamais le texte :

| Refusé | Message |
|---|---|
| Plus de 256 caractères tels que saisis | `A code is at most 256 characters as typed; …` |
| Un mauvais nombre de symboles | `A code has 13 symbols; 12 were given.` |
| Un caractère hors de l'alphabet | `The code holds a symbol outside its alphabet.` |
| Un symbole de contrôle qui ne correspond pas | `The check symbol of the code does not match: a symbol was mistyped.` |

Un échange transforme chacun de ces refus en résultat `Unknown` ; voir [Échange](redemption.md).
