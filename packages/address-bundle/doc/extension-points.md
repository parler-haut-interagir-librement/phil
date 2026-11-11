# Extension points

What an application configures, extends or listens to. The types of addresses are `phil/address`'s, whose data
the bundle reads as they are.

## Configuration

| Option | What it extends |
|---|---|
| `default_country` | The country of a new address, or `null` to ask for it |
| `format_url` | The endpoint the Stimulus controller reads, `__COUNTRY__` standing for the country; `null` leaves the controller out — see [Installation](installation.md) |

## Forms

| Point | How |
|---|---|
| The countries offered | The `countries` option of `AddressType` |
| The country of a new address | Its `default_country` option |
| The recipient's fields | Its `with_recipient` option |
| Labels | The `phil_address` translation domain: an application's translations override the shipped ones |
| More than the form does | A form type extension of the application's on `AddressType`, as on any Symfony form type |

See [Forms](forms.md).

## The Stimulus controller

`phil--address-bundle--address` dispatches `changed`, with the country's format, once the fields follow a new
country, and `failed`, with the error, when the format cannot be read: an application's controller listens to them
to fill or show more.

## Validation and storage

| Point | How |
|---|---|
| Rules beyond the country's | A constraint of the application's beside `ValidAddress`, which checks what the country's format says |
| Where an address is kept | The `phil_address` column type, JSON through `AddressArray` — see [Validation and storage](validation-and-storage.md) |

## Twig

`address`, `address_lines`, `address_label`, `country_name` and `address_schema` write what `phil/address` gives; a
layout of the application's own reads `address_lines` and places each line itself — see [Twig](twig.md).
