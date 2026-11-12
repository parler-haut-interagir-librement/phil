# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. What an application
extends lives in the libraries a card is made of.

| Point | How |
|---|---|
| The kinds of web addresses | A card's `urls` are `TypedUrl`s of `phil/url`: an application adds a kind by writing an enum that implements `Phil\Url\UrlType` |
| The messaging services | A card's `handles` are `Handle`s of `phil/instant-messenger`: an application adds a service by writing an enum that implements `Phil\InstantMessenger\MessengerType` |
| The country of a phone number | Given when the `PhoneNumber` of a `PhoneLine` is parsed, by `phil/phone` |
| How a culture writes and sorts names | A `NameConvention` of the application's, given to the methods of `PersonName` |
| The messaging services and kinds of address JSContact is read as | The `messengers` and `urlTypes` arguments of `JSContactReader` |
| The bounds of a JSContact card read | The `maxBytes`, `maxDepth` and `maxEntries` arguments of `JSContactReader` |
| What JSContact carries beyond the card | `JSContactCard::$unmapped`, kept and written back; `JSContactCard::$notices`, what was read but not kept |
| Where a card is kept | `ContactCardArray::from()` gives an array for one JSON column, `ContactCardArray::to()` reads it back |
| How a card is published | `VCard::of()` for a vCard 4.0 download, `SchemaOrg::of()` for JSON-LD, `JSContactWriter` for JSContact; another format is written over the card's public properties |
| Catching the library's errors | Every exception implements `Phil\Contact\Exception\ContactException` |

A stored card names each kind and service by its enum class and case, `App\Url\Kind::Forum`: renaming or removing a
case the data still names makes `ContactCardArray::to()` throw `UnreadableContact`, so a case is migrated in the data
before it leaves the code.
