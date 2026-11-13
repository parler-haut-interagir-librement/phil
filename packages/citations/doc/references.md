# References and import

## The model

A `Model\Reference` is one cited document as CSL-JSON keeps it:

| Part | Type | Holds |
|---|---|---|
| `id` | `string` | The key citations name, never empty. |
| `type` | `Model\ItemType` | One of the 45 item types of CSL 1.0.2: `ArticleJournal`, `Book`, `Chapter`, `Webpage`… |
| names | `list<Model\Name>` by `Model\NameVariable` | `author`, `editor`, `translator` and the other roles of CSL, in order. |
| dates | `Model\CslDate` by `Model\DateVariable` | `issued`, `accessed`, `original-date`, `event-date`, `submitted`, `available-date`. |
| fields | `string` by CSL name | `title`, `container-title`, `publisher`, `publisher-place`, `volume`, `issue`, `page`, `edition`, `DOI`, `URL`… |

A `Name` holds a family, a given name, a dropping particle (« de » in « Charles de Gaulle » sorted under G), a
non-dropping particle (« van » in « van Gogh »), a suffix, or a literal for an organisation; it needs a family or a
literal. A `CslDate` holds a `PartialDate` — a year from −9999 to 9999, then a month, a day or a season when known —,
a second one for a range, an approximation flag, or a literal text such as « forthcoming ». A date that does not exist
is refused with `InvalidReference`, and so are an empty id, an unknown variable and a field named like a name or a
date variable.

## CSL-JSON

```php
use Phil\Citations\CslJson;

$json = new CslJson();
$references = $json->decode($text);   // list<Reference>
$text = $json->encode($references);   // pretty-printed, Unicode and slashes unescaped
```

`decode` reads a list of items, or one item alone. Names may be objects or plain strings (taken as literals); dates
may be `date-parts`, a `raw` text (`2020`, `2020-05-01`, `2019/2020`) or a `literal`; a month above 12 is CSL's season.
Numbers in place of text are taken as text; values that are neither, and keys CSL-JSON does not define for names, are
left out. The input is bounded: 5 MB and 10 000 items by default (`maxBytes`, `maxItems`), a depth of 16, date parts
of at most five digits. Anything else throws `InvalidReference`.

## BibTeX

```php
use Phil\Citations\Import\BibTeXReader;

$references = new BibTeXReader()->read($bibtex);
```

The reader takes entries in braces or parentheses, values in braces, in quotes, as numbers or as macros joined by `#`,
`@string` and the month macros, and leaves out `@comment`, `@preamble` and any text outside an entry. Values are made
plain: LaTeX accents to composed letters (`\'e`, `{\"o}`, `\c{c}`, `\v s`), named letters (`\ss`, `\o`, `{\aa}`), escaped
characters, dashes, ties and quotes, protective braces removed; an unknown command keeps its argument. Names are read
in BibTeX's three forms, a name in braces as a literal, `others` left out.

| BibTeX | CSL |
|---|---|
| `article`, `book`, `inbook`, `incollection`, `inproceedings`, `phdthesis`, `techreport`, `online`, `misc`… | `article-journal`, `book`, `chapter`, `chapter`, `paper-conference`, `thesis`, `report`, `webpage`, `document`… |
| `journal`, `journaltitle`, `booktitle` | `container-title` |
| `address`, `location` | `publisher-place` |
| `school`, `institution`, `organization` | `publisher`, when there is none |
| `number` | `issue` for an article, `number` otherwise |
| `year`, `month`, `day`, `date` | `issued` |
| `urldate` | `accessed` |
| `pages`, `series`, `chapter`, `type`, `howpublished`, `subtitle` | `page`, `collection-title`, `chapter-number`, `genre`, `medium`, appended to `title` |

`crossref` gives a child the fields it lacks from its parent, and the parent's title as its `booktitle`. Each entry
key becomes the reference's id.

## RIS

```php
use Phil\Citations\Import\RisReader;

$references = new RisReader()->read($ris);
```

A record runs from its `TY` tag to its `ER` tag; a line without a tag continues the value before it, and text outside records
is left out. `AU`/`A1` are authors, `A2`/`ED` editors, `A3` collection editors, `A4` translators, each « Last, First,
Suffix » or a literal; `PY`, `Y1` or `DA` is the issued date; `SP` and `EP` make the page range; `SN` is an ISSN for a
periodical, an ISBN otherwise. The id is the `ID` tag, or the first author's family and the year made ASCII
(`smith2020`, then `smith2020a`), or `ref` and the record's number.

## Limits

Both readers take an `Import\ImportLimits` and refuse the input past it with `InvalidImport`, whose `inputLine` says
where; nothing is truncated in silence except the names past `maxNames`.

| Limit | Default |
|---|---|
| `maxBytes` | 5 000 000 bytes of input; in BibTeX, also the bytes of all values together, macros expanded |
| `maxEntries` | 10 000 entries or records |
| `maxFieldLength` | 65 536 bytes for one value, macros expanded |
| `maxDepth` | 32 levels of braces |
| `maxNames` | 1 000 names in one field |

The input must be UTF-8.

## Duplicates

A `ReferenceList` holds the references a text may cite. A reference with the DOI of one already listed — `doi:` and
`https://doi.org/` prefixes and case aside —, or with its title, year and first author when it has no DOI, is the same
document under another id: citing either id cites the first, and the bibliography lists it once. The same reference
given twice is kept once; two different references under one id throw `InvalidReference`, and citing an id the list
does not hold throws `UnknownReference`.
