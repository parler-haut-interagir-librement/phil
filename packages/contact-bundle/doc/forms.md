# Forms

## `ContactCardType`

The card's name, organisation and title, and four collections: emails (`EmailAddressType`), phone lines
(`PhoneLineType`: a role and `phil/phone-bundle`'s field), web addresses (`phil/url-bundle`'s field) and handles
(`phil/instant-messenger-bundle`'s field). Empty entries are dropped, an empty card is `null`; a bad entry makes its
own field invalid.

```php
$builder->add('contact', ContactCardType::class, [
    'region' => 'FR',
    'url_types' => [KnownUrlType::Website, KnownUrlType::Facebook],
    'messenger_types' => [KnownMessenger::Signal, KnownMessenger::WhatsApp],
]);
```

| Option | Default | Means |
|---|---|---|
| `region` | `null`: `phil_phone.default_region` | The country phone numbers without country code are read in |
| `url_types` | `null`: every international kind | The kinds of web address accepted |
| `messenger_types` | `null`: every international service | The messaging services offered |
| `structured_name` | `false` | Whether the form asks for the parts of a person name, a `PersonNameType` |
| `culture` | `null` | The culture of the names entered, given to `PersonNameType` |

With `structured_name`, the card's name is given by its parts when the name field is left empty, and follows them
when they change: a card whose name is the display form of its structured name shows an empty name field.

## `PersonNameType`

A person name in its parts, to and from a `PersonName` of `phil/contact`: honorific, given name, additional names in
one field separated by spaces, particle, family name, second family name, generation, credential, the order the name
is written in when it is not its culture's, and its culture, a BCP 47 language tag. The fields carry the
`autocomplete` tokens browsers fill them with: `honorific-prefix`, `given-name`, `additional-name`, `family-name` and
`honorific-suffix` for the generation. A name without given nor family name is `null`; a name `phil/contact` refuses
makes the field invalid, `This name is not valid.`.

```php
$builder->add('name', PersonNameType::class, ['culture' => 'fr-CA']);
```

| Option | Default | Means |
|---|---|---|
| `culture` | `null`: a field of the form | The BCP 47 language tag every name entered takes; the form then has no culture field |

See the person names of `phil/contact` for how a culture orders and sorts a name.

Labels are in the `phil_contact` translation domain, in English and French.

## The theme and the Stimulus controller

With TwigBundle, the bundle adds `@PhilContact/form/theme.html.twig` to the form themes: each collection gets an
*Add* button, and each entry a *Remove* button, driven by the Stimulus controller
`phil--contact-bundle--collection`. A new entry is the collection's prototype with an index no entry had: the theme
gives the controller the index after the highest key on the page, since the entries kept from a submission that
failed may skip numbers. The controller dispatches `added`, with the index, and `removed`.
