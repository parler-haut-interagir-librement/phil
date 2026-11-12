# vCard and schema.org

## vCard 4.0

`VCard::of($card)` writes the card as RFC 6350 describes, for a `.vcf` file (`VCard::MEDIA_TYPE`, `text/vcard`):

| Property | From |
|---|---|
| `KIND` | `org` for an organisation, `individual` otherwise |
| `FN`, `ORG`, `TITLE` | The display name, the organisation, the title |
| `N` | The structured name: family name with its particle and second family name, given name, additional names, honorific, generation and credential; then the second family name and the generation, as RFC 9554 adds them, when the name has them |
| `N;LANGUAGE=…` | The culture of the structured name |
| `N;SORT-AS="…"` | The sorted family name and the given name, when the culture sorts the name otherwise than it is written: `N;LANGUAGE=fr;SORT-AS="Gaulle,Charles":de Gaulle;Charles;;;` |
| `EMAIL` | Each email |
| `TEL;VALUE=uri;TYPE=…` | Each phone line, as a `tel:` address with its extension, typed `voice`, `cell`, `fax`, `text` or `pager` |
| `URL` | Each web address |
| `IMPP` | Each handle whose service has a link: `xmpp:…`, `https://t.me/…` |

Texts escape `\`, `,`, `;` and line breaks (CRLF, LF or a lone CR); `SORT-AS` escapes `^` and `"` as RFC 6868 does,
and writes a comma of a name as a space. Lines longer than 75 octets are folded, never inside a character; lines end
with CRLF.

## schema.org

`SchemaOrg::of($card)` gives the JSON-LD as an array:

```php
[
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => 'Ada Lovelace',
    'jobTitle' => 'Countess',
    'worksFor' => ['@type' => 'Organization', 'name' => 'Analytical Engines'],
    'email' => ['ada@example.com'],
    'telephone' => ['+15145550123'],
    'faxNumber' => ['+15145550124'],
    'sameAs' => ['https://example.com/'],
]
```

A person with a structured name also has `honorificPrefix`, `givenName`, `additionalName`, `familyName` — with its
particle and second family name — and `honorificSuffix`, the generation and the credential separated by a comma. An
organisation is an `Organization`, without `jobTitle`. Empty properties are left out; the others are lists. Phone
numbers are in E.164, without their extensions.

## JSContact

See [JSContact](jscontact.md).
