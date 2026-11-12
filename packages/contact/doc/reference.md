# Reference

| Type | Kind | Role |
|---|---|---|
| `ContactCard` | `final readonly class` | A person or an organisation, and how to reach them |
| `PersonName` | `final readonly class` | The name of a person in its parts, written and sorted as its culture does |
| `NameOrder` | string-backed enum | Given name first or family name first |
| `NameConvention` | `final readonly class` | How a culture writes and sorts names |
| `NameConventions` | `final class` | The conventions of the library, by culture |
| `Email` | `final readonly class` | An email address |
| `PhoneLine` | `final readonly class` | A phone number with its role |
| `PhoneRole` | string-backed enum | What a phone line is for, and its vCard type |
| `VCard` | `final class` | A card as vCard 4.0 |
| `SchemaOrg` | `final class` | A card as schema.org JSON-LD |
| `ContactCardArray` | `final class` | A card to and from an array, for storage |
| `JSContactCard` | `final readonly class` | A card as JSContact exchanges it: uid, addresses, unmapped properties, notices |
| `JSContactWriter` | `final class` | A card as JSContact 1.0 |
| `JSContactReader` | `final readonly class` | A JSContact card read, strict and bounded |
| `JSContactNotice` | `final readonly class` | A part of a JSContact card read but not kept |
| `Exception\ContactException` | interface | Every exception of the library |
| `Exception\InvalidContact` | `final class`, `InvalidArgumentException` | An email that is none, a text too long, a person name or a JSContact card that cannot be made |
| `Exception\UnreadableContact` | `final class`, `UnexpectedValueException` | A stored card that cannot be read back |
| `Exception\UnreadableJSContact` | `final class`, `UnexpectedValueException` | A JSContact card that cannot be read |

The classes of `Phil\Contact\JSContact\` are internal: the parts of one reading of a JSContact card.
