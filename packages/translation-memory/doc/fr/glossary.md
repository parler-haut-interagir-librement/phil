<!-- phil-dev source-sha256=8fa90efb5a4d4c878304cd74dcf90e6f94834e51dc881949378be3a02ec8477a -->
# Glossaire

Un `Glossary\Glossary` contient les `Term` d'une paire de langues.

```php
$glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr'), [
    new Term('hit points', ['points de vie', 'PV'], forbidden: ['vie']),
    new Term('Dungeon Master'),                                  // kept as it is
    new Term('Gnome', ['Gnome'], caseSensitive: true, note: 'The people, not the garden ornament.'),
]);
```

| Terme | Sens |
|---|---|
| `source` | Le terme dans la langue source, espaces normalisées |
| `translations` | Les traductions permises ; aucune quand le terme est conservé tel quel, comme un nom ou une marque |
| `forbidden` | Les traductions refusées |
| `caseSensitive` | Si la casse compte, dans la source comme dans la cible |
| `note` | Une note pour les traducteurs |
| `forms` | Les formes fléchies du terme : `hit point` pour `hit points` |
| `translationForms` | Les formes fléchies de ses traductions (`point de vie` pour `points de vie`), ou du terme quand il est conservé |

## Trouver des termes

`find($source)` donne les `TermHit` d'une source, dans l'ordre de lecture : le terme, les mots tels qu'écrits, et leur
position en caractères dans le texte brut. Les termes correspondent en mots entiers (jamais à l'intérieur d'un mot plus
long) et littéralement, sans égard à la casse sauf si le terme le précise. Quand deux termes se chevauchent, le plus
long l'emporte : `hit points` avant `hit`.

## Vérifier une cible

`check($source, $target)` donne les `TermIssue` d'une cible :

| Sorte | Quand |
|---|---|
| `missing` | La cible contient les traductions permises (ou le terme conservé) moins de fois que la source ne contient le terme ; le problème indique combien de chacun |
| `forbidden` | La cible contient une traduction refusée hors d'une traduction permise : `vie` est refusé alors que `points de vie` est permis |

Seuls les termes de la source sont vérifiés.

## Formes fléchies

Un terme et ses traductions changent dans une phrase : un point de vie, deux points de vie. Les formes d'un terme sont
trouvées comme le terme lui-même, et les formes de ses traductions comptent comme ses traductions :

```php
$term = new Term('hit points', ['points de vie'], forms: ['hit point'], translationForms: ['point de vie']);

$term->variants();   // ['hit points', 'hit point'] — what find() looks for
$term->accepted();   // ['points de vie', 'point de vie'] — what check() counts
```

Les formes égales au terme ou à une traduction sont omises, et les doublons gardés une fois.

Un `Inflector` produit les formes à leur place : quand on lui en donne un, le glossaire ajoute les formes qu'il donne
pour chaque terme, dans la langue source, et pour chaque traduction, dans la langue cible ;
`$term->inflected($inflector, $source, $target)` fait de même pour un seul terme. `RegularPlurals` donne le pluriel
régulier en anglais et en français, variantes régionales comprises :

| Langue | Règle | Exemples |
|---|---|---|
| Anglais | Le dernier mot : `-es` après `s`, `x`, `z`, `ch`, `sh` ; `-ies` après une consonne et `y` ; `-s` sinon | `hit point`, `hit points` ; `box`, `boxes` ; `city`, `cities` |
| Français | Chaque mot avant la première préposition : inchangé après `s`, `x`, `z` ; `-aux` pour `-al` ; `-x` après `au`, `eu` et sept noms en `-ou` ; `-s` sinon, et pour `bal`, `bleu`, `festival`, `pneu` et semblables | `point de vie`, `points de vie` ; `épée longue`, `épées longues` ; `cheval`, `chevaux` |

Les mots qui contiennent un chiffre ou une apostrophe sont laissés tels quels, et les autres langues ne reçoivent
aucune forme. Les pluriels irréguliers (`mouse`, `œil`) et les autres flexions (genres, cas, conjugaisons) vont dans les
formes du terme, ou dans un fléchisseur de l'application.

```php
$glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr-CA'), $terms, new RegularPlurals());
```

## Échange

Un glossaire part et arrive en TBX : voir [TMX et TBX](tmx-and-tbx.md).
