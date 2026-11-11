# Validation and storage

## `ValidAddress`

```php
#[ValidAddress]
public ?Address $address = null;
```

An `Address` is complete and right for its country; each violation is on its field's path, `address.postalCode`.
`null` is valid.

| Code | Message |
|---|---|
| `MISSING_ERROR` | *This value is required in an address of this country.* |
| `UNUSED_ERROR` | *This value is not part of an address of this country.* |
| `INVALID_POSTAL_CODE_ERROR` | *This postal code is not one of this country or region.* |
| `UNKNOWN_SUBDIVISION_ERROR` | *This place is not one of this country.* |

## `phil_address`

With DoctrineBundle, the column type `phil_address`: an `Address` in one JSON column, as `AddressArray` writes it.

```php
#[ORM\Column(type: 'phil_address', nullable: true)]
private ?Address $address = null;
```

A value that is not an `Address` is refused on writing; a stored text that is no JSON, nested deeper than an
address, or no address, on reading.
