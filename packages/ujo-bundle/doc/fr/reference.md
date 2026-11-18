<!-- phil-dev source-sha256=1d5d4a16198d3f16c26148f916c0e8cb90a2e9ad5df891eba36b92ef762dc2fb -->
# Référence

Chaque classe du bundle, avec son rôle.

| Classe | Nature | Rôle |
|---|---|---|
| `Phil\UjoBundle\PhilUjoBundle` | `final class`, `AbstractBundle` | Le bundle : la configuration `phil_ujo` |
| `Phil\UjoBundle\Infrastructure\DependencyInjection\UjoServices` | `final class` | Déclare les services de `phil/ujo` (dont l'`InlineSanitizer` avec `max_bytes`), le champ, le validateur et l'extension Twig |
| `Phil\UjoBundle\Infrastructure\Twig\UjoExtension` | `final readonly class` | Les filtres et les fonctions `ujo_html` et `ujo_text` |
| `Phil\UjoBundle\Infrastructure\Form\UjoType` | `final class`, `AbstractType` | Un champ qui contient du JSON UJO |
| `Phil\UjoBundle\Infrastructure\Form\UjoTransformer` | `final readonly class`, `DataTransformerInterface` | Du JSON vers du JSON canonique ou un `Document`, et retour |
| `Phil\UjoBundle\Infrastructure\Validator\ValidUjo` | `final class`, `Constraint` | Un document valide à un niveau de validation, de types acceptés |
| `Phil\UjoBundle\Infrastructure\Validator\ValidUjoValidator` | `final class`, `ConstraintValidator` | Son validateur |

Voir [configuration.md](configuration.md), [twig.md](twig.md) et [forms-and-validation.md](forms-and-validation.md).
