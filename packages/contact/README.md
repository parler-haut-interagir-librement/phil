# phil/contact

**A contact card for a person or an organisation, ready to save as vCard or JSON-LD.**

phil/contact holds a contact card as an immutable value object. A card names a person, who may work for an
organisation, or an organisation alone. It carries a title, email addresses, phone lines each with its role (voice,
mobile, fax, text or pager), web addresses and messaging handles. A person's name may be kept in its parts — given
name, particle, family name and the rest — and is then written and sorted as its culture does: `Charles de Gaulle`
sorts under *Gaulle* in French, `Bartók Béla` is written family name first in Hungarian. The card is written as a
vCard 4.0 file, as RFC 6350 describes it, as schema.org JSON-LD, a `Person` or an `Organization`, and as JSContact
(RFC 9553), which it also reads, strictly and within bounds. It also turns into an array of texts for one JSON column.
It does not hold postal addresses, which belong to `phil/address` and travel beside the card in JSContact, nor opening
hours, which belong to `phil/opening-hours`, nor a contact form, which `phil/contact-form-bundle` gives.

It is for any application that shows people how to reach someone: a directory entry, a shop's page, an organiser's
profile. Phone numbers, web addresses and handles are no plain strings. They are the value types of `phil/phone`,
`phil/url` and `phil/instant-messenger`, so a card only holds what those packages accept.

It is a plain PHP library, usable in any PHP code. In a Symfony application, reach for `phil/contact-bundle`, which
adds the form, the Doctrine column type, vCard downloads and Twig functions.

The design keeps the card apart from its formats. `ContactCard` and its parts hold checked values only, and `VCard`,
`SchemaOrg`, `JSContactWriter` and `ContactCardArray` each write the card in one format. The naming conventions of
cultures are typed values, `NameConvention`, that an application may replace. Reading a stored card back throws
`UnreadableContact` for anything `ContactCardArray` does not write; reading JSContact keeps what the card does not map
and reports what it reads without keeping, never dropping anything silently.

## Status

| Part | State |
|---|---|
| `ContactCard`, `Email`, `PhoneLine` and `PhoneRole` | Implemented and tested |
| `PersonName`, `NameConvention`, `NameConventions`: names written and sorted by culture | Implemented and tested |
| `VCard`: vCard 4.0, escaped and folded | Implemented and tested |
| `SchemaOrg`: `Person` or `Organization` JSON-LD | Implemented and tested |
| `ContactCardArray`: to and from an array, for storage | Implemented and tested |
| `JSContactWriter`, `JSContactReader`: JSContact 1.0, bounded and fuzzed | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Cards](doc/cards.md) | `ContactCard`, emails, phone lines and their roles. |
| [Person names](doc/names.md) | `PersonName`, its parts, and the conventions of cultures. |
| [vCard and schema.org](doc/vcard-and-schema-org.md) | The vCard and JSON-LD exports. |
| [JSContact](doc/jscontact.md) | Writing and reading JSContact, the mapping, the notices and the bounds. |
| [Storage](doc/storage.md) | `ContactCardArray`, what is checked when a card is read back, and [encrypting stored cards](doc/storage.md#encrypting-stored-cards) with `#[Encrypted]` and `#[BlindIndexed]`. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/contact
```

```php
use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\Contact\VCard;
use Phil\Phone\PhoneNumber;

$card = ContactCard::of(
    organization: 'Analytical Engines',
    emails: [Email::of('ada@example.com')],
    phones: [new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+1 514 555 0123'))],
    structuredName: PersonName::of(given: 'Ada', family: 'Lovelace', culture: 'en-GB'),
);

$card->name;                          // "Ada Lovelace"
$card->structuredName->sortKey();     // "Lovelace, Ada"
VCard::of($card);                     // "BEGIN:VCARD\r\nVERSION:4.0\r\nKIND:individual\r\nFN:Ada Lovelace\r\n…"
```

## Requirements

PHP 8.5 or later with `ext-filter`, `ext-intl` and `ext-mbstring`; `phil/phone`, `phil/url`,
`phil/instant-messenger` and `phil/address` 0.1, and `phil/enums` 0.3.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
