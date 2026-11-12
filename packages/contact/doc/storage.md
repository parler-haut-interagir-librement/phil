# Storage

`ContactCardArray::from($card)` writes a card as an array of texts, `ContactCardArray::to($array)` reads it back:
what `phil/contact-bundle`'s column type stores as JSON.

```json
{"name": "Ada Lovelace", "organization": null, "title": null,
 "emails": ["ada@example.com"],
 "phones": [{"role": "mobile", "number": "+15145550123;ext=12"}],
 "urls": [{"type": "Phil\\Url\\KnownUrlType::Website", "url": "https://example.com/"}],
 "handles": [{"type": "Phil\\InstantMessenger\\KnownMessenger::Telegram", "handle": "adalove"}],
 "structuredName": {"given": "Ada", "family": "Lovelace", "particle": null, "additional": ["Augusta"],
  "secondFamily": null, "honorific": null, "generation": null, "credential": null, "culture": "en-GB", "order": null}}
```

`structuredName` is `null` for a card without one. Its `order` is `given-first`, `family-first` or `null`, the order of
the culture.

Kinds of address and messaging services are written `Enum\Class::Case`. When a card is read back, a missing key is
empty, and `UnreadableContact` is thrown for anything else that is not what `from()` writes: a text that is no text,
a list that is no list, a role no `PhoneRole` has, a reference to anything but a case of an enum implementing
`UrlType` or `MessengerType`, a structured name that is no object or has no known order, or a value its type refuses.

## Cards stored without a structured name

A card stored before it had a structured name has no `structuredName` key, and is read back without one: its `name`
stays its only name. No migration of the data is needed. An application that wants the parts of the names it already
keeps asks for them, through the form of `phil/contact-bundle` for one, or splits them itself and saves the cards
again. An application that renames a case renames it in the
stored cards too.

## Encrypting stored cards

A card is personal data from end to end: names, addresses, phone lines and handles. Where it is stored in a
database, encrypt its column at rest with `#[Encrypted]` of Symfony's KeyManagement component, from its Doctrine ORM
bridge `symfony/doctrine-orm-key-management`. The attribute keeps the column's own type: the card is first written
as JSON by that type, `phil/contact-bundle`'s one for instance, then encrypted under a master key of the KMS, and
stored as a binary column.

```php
use Doctrine\ORM\Mapping as ORM;
use Phil\Contact\ContactCard;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[ORM\Column(type: 'phil_contact', nullable: true)]
#[Encrypted(key: 'member.contact')]
private ?ContactCard $contact = null;

#[ORM\Column(length: 180)]
#[Encrypted(key: 'member.email')]
private string $email = '';

#[ORM\Column(length: 64)]
#[BlindIndexed('email', 'member-email')]
private string $emailIndex = '';
```

An encrypted column cannot be searched. A card holds several addresses and phone lines, so the one value the
application looks people up by goes in a column of its own, encrypted too, with a blind index beside it:
`#[BlindIndexed]` fills that sibling column on every flush with a keyed digest of the value, and the application finds
the row by the digest of what it is given. `Projection\Email` folds an address before it is indexed; a phone number
written in E.164 is indexed by `Projection\Verbatim`. A blind index tells which rows share a value: index what has
many distinct values and is looked up by equality, nothing else.

The master keys and the index's data key are configured in `key_management`, with the application's KMS or a local
`sodium://` client. They rotate in the component: `key-management:rewrap-data-keys` wraps the stored data keys again
without rewriting a row when a data key store is configured; otherwise the old master key stays in the KMS while
cards sealed under it remain. The data key of a blind index never rotates. Both attributes are experimental in
Symfony 8.2.
