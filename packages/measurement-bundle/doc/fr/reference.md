<!-- phil-dev source-sha256=655b967ff1a4352e8120931c796c5cf50ea14d316b04873cdde841f14cd49c63 -->
# Référence

## Classes

| Classe | Rôle |
|---|---|
| `PhilMeasurementBundle` | Le bundle, sa configuration `phil_measurement`, et l'enregistrement du type de colonne à son démarrage. |
| `Infrastructure\Form\MeasurementType` | Le type de formulaire d'une mesure : `family`, `units`, `unit`, `channel`. |
| `Infrastructure\Form\MeasurementTransformer` | Entre une `Measurement` et les champs `value` et `unit`, dans la locale de la requête. |
| `Infrastructure\Validator\Attribute\MeasurementRange` | La contrainte d'une mesure entre deux bornes. |
| `Infrastructure\Validator\MeasurementRangeValidator` | Son validateur, qui lit les bornes avec le registre. |
| `Infrastructure\Validator\Attribute\MeasurementUnit` | La contrainte de la famille et de l'unité d'une mesure. |
| `Infrastructure\Validator\MeasurementUnitValidator` | Son validateur. |
| `Infrastructure\Twig\MeasurementExtension` | Les filtres `format_measurement`, `measurement_in_channel` et `preferred_units`. |
| `Infrastructure\Doctrine\MeasurementType` | Le type de colonne `phil_measurement`. |

## Services

| Service | Classe | Rôle |
|---|---|---|
| `Phil\Measurement\UnitRegistry` | `UnitRegistry` | Les unités de la bibliothèque avec celles de `units` ; public sous le nom `phil_measurement.registry`. |
| `phil_measurement.standard_registry` | `UnitRegistry` | Les unités de la bibliothèque seules, que le registre ci-dessus étend. |
| `Phil\Measurement\ChannelConversions` | `ChannelConversions` | Les unités de `channels`. |
| `Phil\Measurement\Cldr\UnitPreferences` | `UnitPreferences` | Les préférences de CLDR, avec le registre. |
| `Phil\Measurement\Cldr\UnitFormatter` | `UnitFormatter` | Les motifs de CLDR. |
| `Phil\MeasurementBundle\Infrastructure\Form\MeasurementType` | | Étiqueté `form.type`. |
| `…\MeasurementRangeValidator`, `…\MeasurementUnitValidator` | | Étiquetés `validator.constraint_validator`. |
| `Phil\MeasurementBundle\Infrastructure\Twig\MeasurementExtension` | | Étiqueté `twig.attribute_extension` et `twig.runtime`. |

## Paramètres

| Paramètre | Valeur |
|---|---|
| `phil_measurement.doctrine_type` | L'option `doctrine_type`. |

## Traductions

`translations/validators.en.php` et `translations/validators.fr.php` contiennent les messages du type de formulaire et
des contraintes.
