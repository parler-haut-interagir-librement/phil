# phil/contact-bundle

**A contact card form, a JSON column, and vCard and JSContact for Symfony applications.**

phil/contact-bundle brings the cards of `phil/contact` into Symfony. `ContactCardType` is a form for a card's name,
organisation and title, with collections of emails, phone lines, web addresses and handles. People add and remove
entries with the buttons of its form theme, driven by a Stimulus controller. Empty entries are dropped, and a bad
entry makes its own field invalid. On request, the form also asks for a person's name in its parts, through
`PersonNameType`, which a culture orders and sorts. The bundle also registers the Doctrine column type
`phil_contact`, which keeps a card in one JSON column. `VCardResponse` sends a card as a `.vcf` attachment and
`JSContactResponse` as JSContact, and the JSContact reader is a service for imports. The Twig functions
`contact_vcard` and `contact_json_ld` write a card as vCard or as schema.org JSON-LD, a node of the page's graph when
phil/structured-data-bundle gathers one, and `person_name` writes a name as it is displayed or sorted. Labels and
messages are in English and French.

It is for Symfony applications where an editor or an administrator enters the contact details of a person or an
organisation, and where visitors download them or search engines read them. The phone, web address and handle fields
come from `phil/phone-bundle`, `phil/url-bundle` and `phil/instant-messenger-bundle`. The form accepts what those
packages accept, and its options limit the kinds of web address and the messaging services offered.

Outside Symfony, or for the card alone, use `phil/contact`. The bundle is a thin layer over it: form transformers,
the column type and the Twig extension each translate between Symfony and the library's value types.

## Status

| Part | State |
|---|---|
| `ContactCardType`, with `EmailAddressType` and `PhoneLineType` | Implemented and tested |
| `PersonNameType` and the `structured_name` option | Implemented and tested |
| The form theme and the Stimulus controller `collection` | Implemented and tested |
| The column type `phil_contact` | Implemented and tested |
| `VCardResponse`; Twig `contact_vcard`, `contact_json_ld`, `person_name` | Implemented and tested |
| `JSContactResponse`; the JSContact reader as a service, configured | Implemented and tested |
| Labels and messages in English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the sibling bundles, registering, configuration. |
| [Forms](doc/forms.md) | `ContactCardType`, `PersonNameType`, their options, the theme and its Stimulus controller. |
| [Doctrine](doc/doctrine.md) | The column type `phil_contact`, and structured names in stored cards. |
| [vCard, JSContact and Twig](doc/vcard-and-twig.md) | `VCardResponse`, `JSContactResponse`, the JSContact reader, `contact_vcard`, `contact_json_ld`, `person_name`. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/contact-bundle
```

```php
#[ORM\Column(type: 'phil_contact', nullable: true)]
private ?ContactCard $contact = null;

$builder->add('contact', ContactCardType::class, ['structured_name' => true]);
```

```twig
{{ contact_json_ld(shop.contact) }}
{{ person_name(shop.contact, 'sort') }}
```

## Requirements

PHP 8.5 or later; `phil/contact`, `phil/phone-bundle`, `phil/url-bundle` and `phil/instant-messenger-bundle` 0.1;
`symfony/dependency-injection`, `symfony/form` and `symfony/options-resolver` 8.2 or later.
Doctrine DBAL, HttpFoundation and Twig are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
