<!-- phil-dev source-sha256=d8f07bdc4b84249045bf94cffab451811084adcf479e8d9cf34b7a6b59c81798 -->
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

## Fichiers

| Fichier | Rôle |
|---|---|
| `config/doctrine/Money.orm.xml` | `Phil\Money\Money` comme objet intégrable |
| `translations/validators.en.php`, `translations/validators.fr.php` | Les messages des contraintes et celui du formulaire |

## Le bundle

`Phil\MoneyBundle\PhilMoneyBundle`, un `AbstractBundle` avec l'alias `phil_money`
([configuration.md](configuration.md)).
