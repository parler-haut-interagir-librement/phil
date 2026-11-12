# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilContactBundle` | `final class`, `AbstractBundle` | Alias `phil_contact`; the column type, the form theme, the Twig extension, the JSContact reader and its configuration |
| `Infrastructure\Form\ContactCardType`, `ContactCardTransformer` | form type, data transformer | The card form |
| `Infrastructure\Form\EmailAddressType`, `EmailTransformer` | form type, data transformer | An email |
| `Infrastructure\Form\PhoneLineType`, `PhoneLineTransformer` | form type, data transformer | A phone line |
| `Infrastructure\Form\PersonNameType`, `PersonNameTransformer` | form type, data transformer | A person name in its parts |
| `Infrastructure\Doctrine\ContactCardColumnType` | DBAL type | `phil_contact` |
| `Infrastructure\Http\VCardResponse` | HTTP response | A card as a `.vcf` file |
| `Infrastructure\Http\JSContactResponse` | HTTP response | A card as JSContact |
| `Infrastructure\Twig\ContactExtension` | Twig extension | `contact_vcard`, `contact_json_ld`, `person_name` |
| `Phil\Contact\JSContactReader` | service | The JSContact reader of `phil/contact`, its bounds from `phil_contact.jscontact` |
| `assets/src/collection_controller.js` | Stimulus controller | Adding and removing entries |
| `templates/form/theme.html.twig` | form theme | The collections' buttons |
