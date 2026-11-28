<!-- phil-dev source-sha256=70bf32a06d254c9a80ccd0611ce6c3210666a1483914d158fd96e63504a05473 -->
# Validation

## `MeasurementRange`

La mesure se situe entre `min` et `max`, bornes comprises, écrites comme les écrit `Measurement::toString()` et
comparées dans n'importe quelle unité de leur famille : `max: '30 kg'` refuse 70 livres.

```php
#[MeasurementRange(min: '0 kg', max: '30 kg')]
public ?Measurement $weight = null;
```

| Violation | Code | Message |
|---|---|---|
| Sous `min` | `MeasurementRange::TOO_LOW_ERROR` | `This measurement should be {{ limit }} or more.` |
| Au-dessus de `max` | `MeasurementRange::TOO_HIGH_ERROR` | `This measurement should be {{ limit }} or less.` |
| D'une autre famille que les bornes | `MeasurementRange::FAMILY_ERROR` | `This measurement cannot be compared with {{ limit }}.` |

L'une ou l'autre borne peut être omise, pas les deux. Les bornes sont lues par le registre de l'application, de sorte
que ses propres unités peuvent servir ; une borne qu'il ne sait pas lire est une erreur de la contrainte et lance
`ConstraintDefinitionException`.

## `MeasurementUnit`

La mesure est de la `family` donnée, dans l'une des `units` données quand il y en a.

```php
#[MeasurementUnit(family: 'mass', units: ['g', 'kg'])]
public ?Measurement $weight = null;
```

| Violation | Code | Message |
|---|---|---|
| D'une autre famille | `MeasurementUnit::FAMILY_ERROR` | `This value should be a measurement of {{ family }}.` |
| Dans une unité hors de la liste | `MeasurementUnit::UNIT_ERROR` | `The unit {{ unit }} is not accepted.` |

Chaque message est une option de sa contrainte, et est traduit en anglais et en français dans le domaine `validators`.
`null` est valide pour les deux contraintes, comme pour toute contrainte sauf `NotNull`.
