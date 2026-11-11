<!-- phil-dev source-sha256=837ec6ca3f563fa4edb1bd03b371aa8c3b98f94e2e46aedd6f66adb745402cb8 -->
# Validation et stockage

## `ValidAddress`

```php
#[ValidAddress]
public ?Address $address = null;
```

Une `Address` est complète et convient à son pays ; chaque violation porte sur le chemin de son champ,
`address.postalCode`. `null` est valide.

| Code | Message |
|---|---|
| `MISSING_ERROR` | *This value is required in an address of this country.* |
| `UNUSED_ERROR` | *This value is not part of an address of this country.* |
| `INVALID_POSTAL_CODE_ERROR` | *This postal code is not one of this country or region.* |
| `UNKNOWN_SUBDIVISION_ERROR` | *This place is not one of this country.* |

## `phil_address`

Avec DoctrineBundle, le type de colonne `phil_address` : une `Address` dans une seule colonne JSON, telle que l'écrit
`AddressArray`.

```php
#[ORM\Column(type: 'phil_address', nullable: true)]
private ?Address $address = null;
```

Une valeur qui n'est pas une `Address` est refusée à l'écriture. Un texte stocké qui n'est pas du JSON, qui est imbriqué
plus profondément qu'une adresse ou qui n'est pas une adresse est refusé à la lecture.
