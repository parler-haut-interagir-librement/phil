<!-- phil-dev source-sha256=596d96648501f0a0c89db8bfd1f50a17814af65de8ea5e47ae911197937cdbf1 -->
# Référence

Chaque classe du bundle, avec son rôle.

| Classe | Nature | Rôle |
|---|---|---|
| `Phil\UjoBundle\PhilUjoBundle` | `final class`, `AbstractBundle` | Le bundle : la configuration `phil_ujo` |
| `Phil\UjoBundle\Infrastructure\DependencyInjection\UjoServices` | `final class` | Déclare les services de `phil/ujo`, le champ, le validateur et l'extension Twig |
| `Phil\UjoBundle\Infrastructure\Twig\UjoExtension` | `final readonly class` | Les filtres et les fonctions `ujo_html` et `ujo_text` |
| `Phil\UjoBundle\Infrastructure\Form\UjoType` | `final class`, `AbstractType` | Un champ qui contient du JSON UJO |
| `Phil\UjoBundle\Infrastructure\Form\UjoTransformer` | `final readonly class`, `DataTransformerInterface` | Du JSON vers du JSON canonique ou un `Document`, et retour |
| `Phil\UjoBundle\Infrastructure\Validator\ValidUjo` | `final class`, `Constraint` | Un document valide à un niveau de validation, de types acceptés |
| `Phil\UjoBundle\Infrastructure\Validator\ValidUjoValidator` | `final class`, `ConstraintValidator` | Son validateur |

Voir [configuration.md](configuration.md), [twig.md](twig.md) et [forms-and-validation.md](forms-and-validation.md).
