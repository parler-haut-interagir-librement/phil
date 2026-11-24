<!-- phil-dev source-sha256=a33cca502621349c5ed737e737153f29fb32ba50ffe9d22bc54287caac917a31 -->
# Ententes

Les modalités de chaque ligne de réception — une part ou une commission — disent ce qu'un consignataire gagne sur
chaque unité vendue. L'**entente** dit le reste de ce que la boutique et le consignataire ont convenu : combien de temps
les unités restent, ce qui arrive à celles qui ne se sont pas vendues, et à quelle fréquence le consignataire reçoit un
relevé.

```php
use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;

$agreements->agree(
    Owner::consignor('author-17'),
    effectiveFrom: $clock->now(),
    termDays: 90,
    unsoldPolicy: UnsoldPolicy::ReturnToConsignor,
    frequency: StatementFrequency::Monthly,
    at: $clock->now(),
);
$entityManager->flush();
```

| Champ | Ce qu'il dit |
|---|---|
| `termDays` | Combien de jours les unités restent, à partir de leur réception : de 1 à 3 650, ou `null` pour aucune fin. |
| `unsoldPolicy` | Ce qui arrive aux unités invendues à la fin : `ReturnToConsignor`, `Extend` ou `BuyOut`. |
| `extensionDays`, `extensions` | Avec `Extend` seulement : les jours qu'ajoute chaque prolongation, et le nombre de prolongations, de 1 à 12 — la durée et chaque prolongation tiennent en 3 650 jours. |
| `frequency` | À quelle fréquence le consignataire reçoit un relevé : `Weekly`, `Monthly` ou `Quarterly`. |

## Ce qui arrive aux unités invendues

| Politique | Quand les unités sont dues | Ensuite |
|---|---|---|
| `ReturnToConsignor` | À la fin de la durée | Elles sont rendues. |
| `Extend` | À la fin de la durée et de chaque prolongation | Elles sont rendues. |
| `BuyOut` | À la fin de la durée | La boutique les achète, au coût convenu. |

`UnsoldStock::due()` liste les unités dues, avec ce qu'il faut en faire : voir [Unités invendues](unsold-units.md). Le
bundle les liste ; il ne rend ni n'achète jamais une unité de lui-même.

## Versions

Une entente n'est jamais modifiée. Une nouvelle version s'applique aux unités reçues à partir de son instant ; les
unités reçues sous la version précédente gardent sa durée et sa politique. Chaque version est vérifiée contre le passé :

- la première version d'un consignataire peut commencer à n'importe quel instant, passé compris, pour couvrir les unités
  déjà en boutique ;
- chaque version suivante commence après la dernière, et jamais avant l'instant où elle est écrite.

`ConsignorAgreements::agreementAt()` donne la version en vigueur à un instant — pour les unités reçues alors —, ou `null`
avant la première. Les unités reçues avant la première entente d'un consignataire, ou sous une entente sans durée, ne
sont jamais dues.

Les modalités de chaque ligne de réception restent celles écrites à la réception : une nouvelle entente ne change ni une
part ni une commission. Un consignataire qui change ses tarifs le fait à sa réception suivante.

## Fréquence des relevés

`ConsignorStatements::lastPeriod()` donne la dernière période entière écoulée à un instant, selon la fréquence de
l'entente alors en vigueur — mensuelle sans entente —, en UTC : la semaine à partir du lundi, le mois civil, le trimestre
civil. Voir [Relevés](statements.md).
