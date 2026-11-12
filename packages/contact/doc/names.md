# Person names

A `ContactCard` keeps a person's name as one text, `name`, and may also keep it in its parts, `structuredName`, a
`PersonName`. The parts let the name be written and sorted as its culture does: `Charles de Gaulle` sorts under
*Gaulle* in French, `Herman Van Rompuy` under *Van Rompuy* in the Dutch of Belgium, and `Bartók Béla` is written family
name first in Hungarian.

```php
$name = PersonName::of(given: 'Charles', family: 'Gaulle', particle: 'de', culture: 'fr');

$name->display();   // "Charles de Gaulle"
$name->sortKey();   // "Gaulle, Charles de"

$card = ContactCard::of(structuredName: $name);
$card->name;        // "Charles de Gaulle"
```

## The parts

| Part | Example | Note |
|---|---|---|
| `given` | `Ada` | The given name |
| `additional` | `['Augusta']` | Middle names, at most `PersonName::MAX_ADDITIONAL` (10) |
| `particle` | `de`, `van der`, `d'` | Goes with a family name; an elided particle, ending with `'` or `’`, is written against it |
| `family` | `Gaulle` | The family name, without its particle |
| `secondFamily` | `Márquez` | A second family name, as in Spanish |
| `honorific` | `Dr.` | Written by `formal()` |
| `generation` | `Jr.`, `III` | Written after the name |
| `credential` | `PhD` | Written by `formal()`, after a comma |
| `culture` | `fr-CA` | A BCP 47 language tag; `_` is read as `-` |
| `order` | `NameOrder::FamilyFirst` | Wins over the order of the culture; none by default |

`PersonName::of()` trims each text; an empty one is none. A name has a given name or a family name, each part is 255
characters or fewer and holds no control character, and a language tag has a language of two or three letters and
parts of one to eight letters or digits after it, 35 characters in all. `InvalidContact` is thrown otherwise. On a
card, the display form of the name is the card's `name` when the card is given none, and it is 255 characters or fewer
too.

## Writing and sorting

| Method | Gives | `Charles de Gaulle` in French |
|---|---|---|
| `display()` | The name as written every day, generation included | `Charles de Gaulle` |
| `formal()` | With the honorific and the credential | `Gén. Charles de Gaulle, PhD` |
| `familyName()` | The family name as written, particle and second family name included | `de Gaulle` |
| `surname()` | The family name with its particle, without the second family name | `de Gaulle` |
| `sortFamily()` | The family name a list sorts by | `Gaulle` |
| `sortKey()` | The text a list sorts by | `Gaulle, Charles de` |
| `separator()` | What separates the parts | a space |
| `displayOrder()` | The order the name is written in | `NameOrder::GivenFirst` |
| `equals()` | Whether two names have the same parts | |

Given name first, `display()` writes the given name, the additional names and the family name; family name first, the
family name, the additional names and the given name, as Vietnamese writes `Võ Nguyên Giáp`. Chinese, Japanese and
Korean names written in their own characters are joined without a space, `毛泽东`, and with one when romanised,
`Mao Zedong`.

`sortKey()` puts the sorted family name first, then the given and additional names, then what the sort leaves out of
the particle: `Gogh, Vincent van`. What the key keeps of a particle starts with a capital, as a sorted list prints it:
`Du Bellay, Joachim`. A culture that sorts by the given name puts it first: `Giáp, Võ Nguyên`. Sort the keys with a
collator of the reader's language, `Collator::compare()` of `ext-intl`: the key orders the parts, the collator orders
the letters.

Each method takes a `NameConvention` as an optional argument, which wins over the name's culture.

## The conventions of the library

`NameConventions::for($culture)` gives the convention of a culture, by its language and, for Dutch, its country:

| Culture | Order | Sort key | Example |
|---|---|---|---|
| Chinese, Japanese, Korean | Family name first, own characters joined | Family name | `毛泽东` → `毛, 泽东` |
| Hungarian | Family name first | Family name | `Bartók Béla` → `Bartók, Béla` |
| Vietnamese | Family name first | Given name | `Võ Nguyên Giáp` → `Giáp, Võ Nguyên` |
| Icelandic | Given name first | Given name | `Björk Guðmundsdóttir` → `Björk, Guðmundsdóttir` |
| Dutch of the Netherlands | Given name first | Without the particle | `Vincent van Gogh` → `Gogh, Vincent van` |
| German, Portuguese | Given name first | Without the particle | `Ursula von der Leyen` → `Leyen, Ursula von der` |
| French | Given name first | Without a leading `de` or `d'` | `Jean de La Fontaine` → `La Fontaine, Jean de` |
| Spanish | Given name first | Without a leading `de` | `Bartolomé de las Casas` → `Las Casas, Bartolomé de` |
| Any other, Dutch of Belgium included | Given name first | With the particle | `Herman Van Rompuy` → `Van Rompuy, Herman` |

A name without a culture follows the last row. An application that knows a culture better builds its own
`NameConvention` — its order, the leading words of a particle dropped from the sort key, whether the whole particle is
dropped, whether names sort by the given name, whether names in Chinese, Japanese or Korean characters are joined —
and gives it to the methods.

## In the other formats

| Format | The structured name |
|---|---|
| vCard | `N`, with the second family name and the generation of RFC 9554 when the name has them, `LANGUAGE` from the culture and `SORT-AS` when the culture sorts the name otherwise than it is written |
| schema.org | `givenName`, `additionalName`, `familyName`, `honorificPrefix` and `honorificSuffix` (generation and credential) of the `Person` |
| JSContact | The components of `name`, its `sortAs`, and the card's `language` — see [JSContact](jscontact.md) |
| Storage | The `structuredName` key — see [Storage](storage.md) |
