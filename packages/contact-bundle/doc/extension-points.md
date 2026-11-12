# Extension points

What an application configures, extends, overrides or listens to. The cards are `phil/contact`'s; phone numbers,
web addresses and handles come through `phil/phone-bundle`, `phil/url-bundle` and `phil/instant-messenger-bundle`.

## The application's own kinds and services

A card's web addresses and handles accept the application's own enums — implementing `Phil\Url\UrlType` and
`Phil\InstantMessenger\MessengerType` — through the `url_types` and `messenger_types` options of `ContactCardType`,
as the fields of those bundles accept them.

## Forms

| Point | How |
|---|---|
| The country of phone numbers | The `region` option of `ContactCardType`; `phil_phone.default_region` without it |
| The kinds of web address and the services | Its `url_types` and `messenger_types` options |
| The parts of a person name | Its `structured_name` option, and `PersonNameType` on its own |
| The culture of the names entered | The `culture` option of `ContactCardType` and `PersonNameType`; a culture field without it |
| Labels | The `phil_contact` translation domain: an application's translations override the shipped ones |
| More than the form does | A form type extension of the application's on `ContactCardType`, `EmailAddressType`, `PhoneLineType` or `PersonNameType` |

See [Forms](forms.md).

## The theme and the Stimulus controller

`@PhilContact/form/theme.html.twig` is added to the form themes; a theme of the application's listed after it
overrides its blocks. `phil--contact-bundle--collection` dispatches `added`, with the index of the new entry, and
`removed`: an application's controller listens to them.

## Storage, downloads and Twig

| Point | How |
|---|---|
| Where a card is kept | The `phil_contact` column type, JSON through `ContactCardArray` — see [Doctrine](doctrine.md) |
| A vCard download | `VCardResponse` returned by the application's controller, under the file name it chooses |
| A JSContact export | `JSContactResponse` returned by the application's controller |
| A JSContact import | The `JSContactReader` service in the application's controller; its bounds and region under `phil_contact.jscontact`; another reader, with the application's messaging services and kinds of address, built by the application |
| Structured data and names | `contact_vcard`, `contact_json_ld` and `person_name` — see [vCard, JSContact and Twig](vcard-and-twig.md) |
