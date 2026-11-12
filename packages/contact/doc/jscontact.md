# JSContact

JSContact, RFC 9553, is the JSON form of a contact card that JMAP Contacts (RFC 9610) and recent address books
exchange. The library writes a card as JSContact 1.0 and reads one back. The stored form of a card does not change:
JSContact is a mapping for import and export.

```php
$document = new JSContactCard('urn:uuid:'.$id, $card, [$address]);

$json = JSContactWriter::toJson($document);       // application/jscontact+json, JSContactCard::MEDIA_TYPE

$read = new JSContactReader()->read($json);
$read->card;        // a ContactCard
$read->addresses;   // the Address values of phil/address
$read->unmapped;    // the properties the library does not map, as they were read
$read->notices;     // what was read but not kept
```

## `JSContactCard`

| Property | Holds |
|---|---|
| `uid` | The card's identifier, 1 to 1024 bytes, required by JSContact |
| `card` | The `ContactCard` |
| `addresses` | The postal addresses, `Address` values of `phil/address`, which the card does not hold |
| `unmapped` | The properties the library does not map — `notes`, `anniversaries`, `photos`, `speakToAs`, a vendor's `example.com:property` —, JSON values as `json_decode()` gives them without its associative flag, so that an empty object stays one |
| `notices` | A `JSContactNotice` for each part read but not kept: its JSON pointer, `/phones/p1/contexts`, and the reason |

The unmapped properties are written back by `JSContactWriter`, after the mapped ones; they never hold a property the
writer writes itself, `JSContactCard::MAPPED`, or `InvalidContact` is thrown. An application that keeps them stores
them beside the card.

## The mapping

| JSContact | Card | Written | Read |
|---|---|---|---|
| `uid` | `JSContactCard::$uid` | Always | Required |
| `kind` | Person or organisation | `individual`, or `org` for a card that names only its organisation | `org` makes the card name its organisation; any kind other than `individual` and `org` is read as a person's, with a notice |
| `language` | The culture of the structured name | From the culture, else from the unmapped `language` | The culture of the structured name; kept unmapped when no structured name takes it |
| `name` | `name` and `structuredName` | `full`, the components in display order, `isOrdered`, `defaultSeparator`, and `sortAs.surname` when the culture sorts the name otherwise than it is written | See below |
| `organizations` | `organization` | One entry | The first entry's `name` |
| `titles` | `title` | One entry | The first entry of kind `title` |
| `emails` | `emails` | One entry each | Each `address` |
| `phones` | `phones` | `number` as a `tel:` address, and the feature of the role | Each `number`, its role from `features` |
| `onlineServices` | `handles`, or `urls` | `service`, the enum case's name, `user`, and `uri` when the service has a link | A handle when `service` names an accepted messaging service, else a web address when `uri` is one of an accepted kind |
| `links` | `urls` | One entry each | Each `uri`, its kind detected |
| `addresses` | `JSContactCard::$addresses` | `components` and `countryCode` | See below |

Entries are written under the keys `o1`, `t1`, `e1`, `p1`, `s1`, `l1` and `a1`, numbered from 1.

### Names

The components `title`, `given`, `given2`, `surname`, `surname2`, `generation` and `credential` are the honorific,
the given name, the additional names, the surname, the second family name, the generation and the credential. A
particle is written with its surname, `de Gaulle`, and `sortAs.surname` is the sort form, `Gaulle`; on reading, the part
of the surname before its sort form, ending with a space or an apostrophe, is the particle. A particle the culture
keeps in the sort key is therefore read back as part of the family name, which writes and sorts the name the same way.

With `isOrdered`, a surname before the given name reads as `NameOrder::FamilyFirst`, kept only when it is not the
order of the culture. A notice reports a component of another kind, a second component of a kind kept once, an
additional name beyond the tenth, a separator — separators follow the convention of the name —, a `sortAs` or a
`defaultSeparator` the culture does not give, a language the name cannot take as its culture, and components that
make no name. The card of an organisation keeps `full` and reports the components.

### Phone lines

| Role | Feature written | Read from |
|---|---|---|
| `Mobile` | `mobile` | `mobile`, with `voice` and `text` implied |
| `Fax` | `fax` | `fax` |
| `Pager` | `pager` | `pager` |
| `Voice` | `voice` | `voice`, or no feature |
| `Text` | `text` | `text` |

A line takes the role of its first feature in the order of the table; a notice reports each other feature, `video`,
`textphone` or `main-number` among them. A number without its country code is read in the reader's `region`.

### Addresses

The writer writes each address line as a `name` component, the dependent locality as `district`, the locality as
`locality`, the administrative area as `region`, and the postal code then the sorting code each as `postcode`. The
recipient fields of an address are left out: the card names its owner.

The reader writes the street components — `number`, `name`, `direction`, `block`, `building`, `floor`, `apartment`,
`room`, `postOfficeBox` and `landmark` — on the address lines, in their order: a `name` after a line that already has
one starts the next line, and an address keeps three lines. The first `district`, `locality` and `region` are kept,
the first `postcode` is the postal code and the second the sorting code. A notice reports any other component, an
address without its `countryCode`, and an address `phil/address` refuses.

## The reader

```php
$reader = new JSContactReader(
    maxBytes: 65_536,
    maxDepth: 16,
    maxEntries: 100,
    region: 'CA',
    messengers: [EnumSet::all(KnownMessenger::class), EnumSet::all(MyMessenger::class)],
    urlTypes: [EnumSet::all(KnownUrlType::class)],
);
```

| Argument | Default | Means |
|---|---|---|
| `maxBytes` | 65 536 | The longest text read |
| `maxDepth` | 16 | The deepest nesting of its JSON |
| `maxEntries` | 100 | The most entries of one map or list |
| `region` | `null` | The country a phone number without its country code is read in |
| `messengers` | Every `KnownMessenger` | The messaging services an online service is read as |
| `urlTypes` | Every `KnownUrlType` | The kinds of web address accepted |

The reader is strict. `UnreadableJSContact` is thrown for a text beyond the bounds, no JSON, no `Card` of version
`1.0`, no `uid`, a property of the wrong JSON type, an entry key that is no JSContact `Id`, an entry whose `@type` is
not the expected one, an entry without its required text, and a card the library refuses, a name longer than 255
characters for one. A value its type refuses — an email that is none, a number no plan knows, a web address of no
accepted kind — leaves its entry out with a notice. Nothing is dropped silently: a property is mapped, kept unmapped,
or reported.

The reader holds no state between two cards: one instance serves every request of a worker.
