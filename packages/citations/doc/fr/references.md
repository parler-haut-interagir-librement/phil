<!-- phil-dev source-sha256=ab7a0708dfd7267dcdccda3b950845af3f4b34a17c9feb1e52088dc8ebc2da64 -->
# Références et import

## Le modèle

Un `Model\Reference` est un document cité tel que CSL-JSON le conserve :

| Partie | Type | Contenu |
|---|---|---|
| `id` | `string` | La clé que nomment les citations, jamais vide. |
| `type` | `Model\ItemType` | L'un des 45 types de documents de CSL 1.0.2 : `ArticleJournal`, `Book`, `Chapter`, `Webpage`… |
| noms | `list<Model\Name>` par `Model\NameVariable` | `author`, `editor`, `translator` et les autres rôles de CSL, dans l'ordre. |
| dates | `Model\CslDate` par `Model\DateVariable` | `issued`, `accessed`, `original-date`, `event-date`, `submitted`, `available-date`. |
| champs | `string` par nom CSL | `title`, `container-title`, `publisher`, `publisher-place`, `volume`, `issue`, `page`, `edition`, `DOI`, `URL`… |

Un `Name` contient un nom de famille, un prénom, une particule détachable (« de » dans « Charles de Gaulle », classé
sous G), une particule non détachable (« van » dans « van Gogh »), un suffixe, ou un littéral pour un organisme ; il
exige un nom de famille ou un littéral. Une `CslDate` contient une `PartialDate` — une année de −9999 à 9999, puis un
mois, un jour ou une saison quand ils sont connus —, une seconde pour un intervalle, un indicateur d'approximation, ou
un texte littéral comme « forthcoming ». Une date qui n'existe pas est refusée avec `InvalidReference`, comme un
identifiant vide, une variable inconnue et un champ nommé comme une variable de nom ou de date.

## CSL-JSON

```php
use Phil\Citations\CslJson;

$json = new CslJson();
$references = $json->decode($text);   // list<Reference>
$text = $json->encode($references);   // pretty-printed, Unicode and slashes unescaped
```

`decode` lit une liste d'éléments, ou un seul élément. Les noms peuvent être des objets ou de simples chaînes (prises
comme littéraux) ; les dates peuvent être des `date-parts`, un texte `raw` (`2020`, `2020-05-01`, `2019/2020`) ou un
`literal` ; un mois supérieur à 12 est une saison de CSL. Les nombres à la place d'un texte sont pris comme du texte ;
les valeurs qui ne sont ni l'un ni l'autre, et les clés que CSL-JSON ne définit pas pour les noms, sont omises.
L'entrée est bornée : 5 Mo et 10 000 éléments par défaut (`maxBytes`, `maxItems`), une profondeur de 16, des parties de
date d'au plus cinq chiffres. Tout le reste lève `InvalidReference`.

## BibTeX

```php
use Phil\Citations\Import\BibTeXReader;

$references = new BibTeXReader()->read($bibtex);
```

Le lecteur accepte des entrées entre accolades ou entre parenthèses, des valeurs entre accolades, entre guillemets, en
nombres ou en macros jointes par `#`, `@string` et les macros de mois, et omet `@comment`, `@preamble` et tout texte
hors d'une entrée. Les valeurs sont rendues simples : accents LaTeX en lettres composées (`\'e`, `{\"o}`, `\c{c}`,
`\v s`), lettres nommées (`\ss`, `\o`, `{\aa}`), caractères échappés, tirets, espaces liées et guillemets, accolades
protectrices retirées ; une commande inconnue garde son argument. Les noms sont lus dans les trois formes de BibTeX, un
nom entre accolades comme un littéral, `others` omis.

| BibTeX | CSL |
|---|---|
| `article`, `book`, `inbook`, `incollection`, `inproceedings`, `phdthesis`, `techreport`, `online`, `misc`… | `article-journal`, `book`, `chapter`, `chapter`, `paper-conference`, `thesis`, `report`, `webpage`, `document`… |
| `journal`, `journaltitle`, `booktitle` | `container-title` |
| `address`, `location` | `publisher-place` |
| `school`, `institution`, `organization` | `publisher`, quand il n'y en a pas |
| `number` | `issue` pour un article, `number` sinon |
| `year`, `month`, `day`, `date` | `issued` |
| `urldate` | `accessed` |
| `pages`, `series`, `chapter`, `type`, `howpublished`, `subtitle` | `page`, `collection-title`, `chapter-number`, `genre`, `medium`, ajouté à `title` |

`crossref` donne à un enfant les champs qui lui manquent, tirés de son parent, et le titre du parent comme son
`booktitle`. La clé de chaque entrée devient l'identifiant de la référence.

## RIS

```php
use Phil\Citations\Import\RisReader;

$references = new RisReader()->read($ris);
```

Un enregistrement va de sa balise `TY` à sa balise `ER` ; une ligne sans balise prolonge la valeur qui la précède, et le
texte hors des enregistrements est omis. `AU`/`A1` sont les auteurs, `A2`/`ED` les directeurs, `A3` les directeurs de
collection, `A4` les traducteurs, chacun sous la forme « Nom, Prénom, Suffixe » ou un littéral ; `PY`, `Y1` ou `DA` est
la date de publication ; `SP` et `EP` forment l'intervalle de pages ; `SN` est un ISSN pour un périodique, un ISBN
sinon. L'identifiant est la balise `ID`, ou le nom de famille du premier auteur et l'année rendus en ASCII (`smith2020`,
puis `smith2020a`), ou `ref` et le numéro de l'enregistrement.

## Limites

Les deux lecteurs prennent un `Import\ImportLimits` et refusent l'entrée qui le dépasse avec `InvalidImport`, dont
`inputLine` dit où ; rien n'est tronqué en silence, sauf les noms au-delà de `maxNames`.

| Limite | Valeur par défaut |
|---|---|
| `maxBytes` | 5 000 000 octets lus ; en BibTeX, aussi les octets de toutes les valeurs ensemble, macros développées |
| `maxEntries` | 10 000 entrées ou enregistrements |
| `maxFieldLength` | 65 536 octets pour une valeur, macros développées |
| `maxDepth` | 32 niveaux d'accolades |
| `maxNames` | 1 000 noms dans un champ |

L'entrée doit être en UTF-8.

## Doublons

Une `ReferenceList` contient les références qu'un texte peut citer. Une référence qui a le DOI d'une référence déjà
listée — préfixes `doi:` et `https://doi.org/` et casse mis à part —, ou le même titre, la même année et le même
premier auteur quand elle n'a pas de DOI, est le même document sous un autre identifiant : citer l'un ou l'autre
identifiant cite le premier, et la bibliographie le liste une fois. La même référence donnée deux fois est gardée une
fois ; deux références différentes sous un même identifiant lèvent `InvalidReference`, et citer un identifiant que la
liste ne contient pas lève `UnknownReference`.
