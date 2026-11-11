<!-- phil-dev source-sha256=7a4a7bf5cb3adbe4bc292cc799e897d1ca3d7d464305b3bd5f67ea1221b9afac -->
# Configuration

```yaml
# config/packages/phil_money.yaml
phil_money:
    currencies: [CAD, USD]  # default: [], every known currency
    default_currency: CAD   # default: null, the first accepted currency
    doctrine_mapping: true  # default: true
```

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `currencies` | `[]` | Les codes ISO 4217 qu'accepte l'application, dans l'ordre où un choix les propose. Vide, elle accepte toutes les devises que connaissent les données CLDR, et ne propose dans un choix que celles qui sont en usage : ayant cours légal dans au moins un pays aujourd'hui. |
| `default_currency` | `null` | La devise dans laquelle commence un montant vide. Sans elle, la première de `currencies` ; avec une liste vide, aucune. |
| `doctrine_mapping` | `true` | Associe `Phil\Money\Money` comme objet intégrable de l'ORM, lorsque l'application configure l'ORM. |

La configuration est vérifiée à la construction du conteneur : un code que ne connaissent pas les données des devises,
ou une valeur par défaut hors d'une liste non vide, arrête le noyau en nommant le code.

## Services

| Service | Enregistré | Rôle |
|---|---|---|
| `Phil\MoneyBundle\Application\AcceptedCurrencies` | toujours | Les devises acceptées et la devise par défaut ; injectez-le là où l'application a besoin de la liste |
| `Phil\MoneyBundle\Infrastructure\Form\MoneyType` | toujours, étiquette `form.type` | Le type de formulaire |
| `Phil\MoneyBundle\Infrastructure\Validator\AcceptedCurrencyValidator` | toujours, étiquette `validator.constraint_validator` | Le validateur de `AcceptedCurrency` |
| `Phil\MoneyBundle\Infrastructure\Validator\MoneyRangeValidator` | toujours, étiquette `validator.constraint_validator` | Le validateur de `MoneyRange` |
| `Phil\MoneyBundle\Infrastructure\Twig\MoneyExtension` | lorsque Twig est installé, étiquettes `twig.attribute_extension` et `twig.runtime` | Le filtre `format_money` et les fonctions `money_schema` et `price_schema` |

Lorsque DoctrineBundle est enregistré, le bundle ajoute ceci au début de sa configuration :

```yaml
doctrine:
    dbal:
        types:
            phil_currency: Phil\MoneyBundle\Infrastructure\Doctrine\CurrencyType
    orm:                     # only when the application configures the ORM and doctrine_mapping is true
        mappings:
            PhilMoney:
                type: xml
                is_bundle: false
                dir: '%vendor%/phil/money-bundle/config/doctrine'
                prefix: Phil\Money
```

Une application avec plusieurs gestionnaires d'entités définit `doctrine_mapping: false` et déclare l'association
`PhilMoney` sur les gestionnaires qui en ont besoin.

## Taux de change

Le bundle n'en garde aucun et ne convertit rien : un montant reste dans sa devise, et `Money` refuse d'en combiner deux.
Une application qui convertit (une liste de prix dans une deuxième devise, un rapport dans une seule) possède ses taux
et leurs dates, et multiplie par eux avec son propre arrondi.
