<!-- phil-dev source-sha256=197dd898ffe74336a40c1ba16d8d06c5f91476564fc2a4ee97be98db4386eb7f -->
# Points d'extension

Ce qu'une application configure ou étend. Les montants et les devises sont ceux de `phil/money`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `currencies` | Les devises acceptées, dans l'ordre où un choix les propose ; vide pour toutes les devises que connaissent les données CLDR |
| `default_currency` | La devise dans laquelle commence un montant vide |
| `doctrine_mapping` | Si `Phil\Money\Money` est associé comme objet intégrable de l'ORM ; `false` pour une association propre à l'application |

Voir [Configuration](configuration.md).

## Formulaires et validation

| Point | Comment |
|---|---|
| La devise d'un champ | L'option `currency` ou `currencies` de `MoneyType` ; voir [Formulaires](forms.md) |
| Son erreur | L'option `invalid_message` |
| Les devises et les montants acceptés | `AcceptedCurrency` et `MoneyRange` avec leurs `min`, `max` et messages ; voir [Validation](validation.md) |
| Les messages dans d'autres langues | Le domaine `validators` : le bundle fournit l'anglais et le français, une application en ajoute ou en remplace d'autres |
| Plus que ce que fait le champ | Une extension de type de formulaire de l'application sur `MoneyType` |

## Stockage et Twig

| Point | Comment |
|---|---|
| L'endroit où un montant est conservé | L'objet intégrable `Money`, ou deux colonnes de l'application avec le type de colonne `phil_currency` ; voir [Doctrine](doctrine.md) |
| L'affichage d'un montant | Le filtre `format_money` avec sa `locale`, et `money_schema` et `price_schema` pour les données structurées ; voir [Twig](twig.md) |
