# Reference

Every class of the bundle, with its role.

| Class | Kind | Role |
|---|---|---|
| `Phil\UjoBundle\PhilUjoBundle` | `final class`, `AbstractBundle` | The bundle: the `phil_ujo` configuration |
| `Phil\UjoBundle\Infrastructure\DependencyInjection\UjoServices` | `final class` | Declares the services of `phil/ujo`, the field, the validator and the Twig extension |
| `Phil\UjoBundle\Infrastructure\Twig\UjoExtension` | `final readonly class` | The `ujo_html` and `ujo_text` filters and functions |
| `Phil\UjoBundle\Infrastructure\Form\UjoType` | `final class`, `AbstractType` | A field holding UJO JSON |
| `Phil\UjoBundle\Infrastructure\Form\UjoTransformer` | `final readonly class`, `DataTransformerInterface` | JSON to canonical JSON or `Document`, and back |
| `Phil\UjoBundle\Infrastructure\Validator\ValidUjo` | `final class`, `Constraint` | A valid document at a validation level, of accepted types |
| `Phil\UjoBundle\Infrastructure\Validator\ValidUjoValidator` | `final class`, `ConstraintValidator` | Its validator |

See [configuration.md](configuration.md), [twig.md](twig.md) and [forms-and-validation.md](forms-and-validation.md).
