<!-- phil-dev source-sha256=21f71f8e9332c12d0729ab2057a8fd1a49e23b4cf3f7d433071533994e05c480 -->
# Référence

Chaque type du bundle, par couche.

## `Phil\MoneyBundle\Application`

| Type | Nature | Rôle |
|---|---|---|
| `AcceptedCurrencies` | `final class` | Les devises acceptées, dans l'ordre, et la devise par défaut ; pour une liste ouverte, les devises en usage ; `isKnown()` pour la configuration |

## `Phil\MoneyBundle\Infrastructure`

| Type | Nature | Rôle |
|---|---|---|
| `Form\MoneyType` | type de formulaire | Un champ de montant et, quand plusieurs devises sont proposées, un choix de devise |
| `Form\MoneyTransformer` | transformateur de modèle | Entre un `?Money` et les valeurs des deux champs |
| `Form\LocalizedDecimal` | `final class` | Un décimal lu et écrit dans une locale, sans nombre à virgule flottante |
| `Validator\AcceptedCurrency`, `Validator\AcceptedCurrencyValidator` | contrainte et validateur | La devise est acceptée |
| `Validator\MoneyRange`, `Validator\MoneyRangeValidator` | contrainte et validateur | Le montant se situe entre deux bornes |
| `Twig\MoneyExtension` | extension Twig par attributs | Le filtre `format_money` ; les fonctions `money_schema` et `price_schema` |
| `Doctrine\CurrencyType` | type DBAL | `phil_currency` |
| `Doctrine\MoneyMappingDriver` | `MappingDriver` de Doctrine | Associe `Phil\Money\Money` avec les attributs de `MoneyEmbeddable` |
| `Doctrine\MoneyEmbeddable` | `#[ORM\Embeddable]`, interne | Les attributs de `Money` : `minorUnits` dans une colonne `bigint` `amount`, `currency` dans une colonne `phil_currency` `currency` |

## Fichiers

| Fichier | Rôle |
|---|---|
| `translations/validators.en.php`, `translations/validators.fr.php` | Les messages des contraintes et celui du formulaire |

## Le bundle

`Phil\MoneyBundle\PhilMoneyBundle`, un `AbstractBundle` avec l'alias `phil_money`
([configuration.md](configuration.md)). `PhilMoneyBundle::DOCTRINE_MAPPING` nomme le paramètre,
`phil_money.doctrine_mapping`, que le bundle définit quand l'ORM est configuré et que `doctrine_mapping` est activé ; sa
passe de compilation ajoute alors `MoneyMappingDriver` au gestionnaire d'entités par défaut.
