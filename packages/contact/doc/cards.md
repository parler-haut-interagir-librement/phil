# Cards

```php
$card = ContactCard::of(
    name: 'Ada Lovelace',
    organization: 'Analytical Engines',
    title: 'Countess',
    emails: [Email::of('ada@example.com')],
    phones: [new PhoneLine(PhoneRole::Voice, PhoneNumber::parse('+1 514 555 0123')), new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+1 514 555 0124'))],
    urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com')],
    handles: [Handle::of(KnownMessenger::Signal, '+15145550123')],
);
```

A card names a **person** when it has a `name` — working for its `organization` when it has one —, an
**organisation** when it has only an `organization`. Texts are trimmed, an empty one is none, and each is 255
characters or fewer, or `InvalidContact` is thrown.

A person's name may also be given in its parts, `structuredName`, a `PersonName` that writes and sorts the name as
its culture does; without a `name`, the card takes the display form of its structured name. See
[Person names](names.md).

| Method | Gives |
|---|---|
| `displayName()` | The name, or the organisation's |
| `isPerson()` | Whether the card names a person |
| `isEmpty()` | Whether the card holds nothing |
| `phonesFor(PhoneRole)` | The phone lines of a role, in their order |

## Emails

`Email::of()` trims the address and lowers its domain; the local part is kept as written. A domain in another script
is checked in its ASCII form and kept as written, `ada@exämple.com`. `link()` is the `mailto:` address.

## Phone lines

A `PhoneLine` is a `PhoneNumber` with its `PhoneRole`: `Voice`, `Mobile`, `Fax`, `Text` for text messages only, or
`Pager`. Whether a number is toll-free is told by its numbering plan, `$line->number->type()`; the role says how the
card's owner uses it.
