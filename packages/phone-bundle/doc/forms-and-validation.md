# Forms and validation

## `PhoneType`

A `tel` field, to and from a `PhoneNumber`. A number typed without its country code is read in the field's `region`,
the bundle's `default_region` unless the field names its own; a number in no numbering plan makes the field invalid
with *This value is not a valid phone number.* A number of the field's country is shown nationally,
`(514) 555-0123`, any other internationally, `+33 6 12 34 56 78`. The field's `maxlength` is
`PhoneNumber::MAX_INPUT`, 100 characters, the longest input `parse()` reads.

```php
$builder->add('phone', PhoneType::class, ['region' => 'FR', 'required' => false]);
```

## `ValidPhoneNumber`

```php
#[ValidPhoneNumber(types: [PhoneNumberType::Mobile, PhoneNumberType::FixedLineOrMobile], regions: ['CA', 'US'])]
public ?PhoneNumber $mobile = null;
```

A `PhoneNumber` or a text — read in `region`, or the bundle's `default_region` — is valid when it is in its plan, of
one of the `types` and from one of the `regions` the constraint names; both default to all. `null` and the empty text
are valid.

| Code | Message |
|---|---|
| `INVALID_ERROR` | *This value is not a valid phone number.* |
| `TYPE_ERROR` | *This phone number is not of an accepted type.* |
| `REGION_ERROR` | *This phone number is not from an accepted country: {{ regions }}.* |

A number of a wrong type and country gets both violations.
