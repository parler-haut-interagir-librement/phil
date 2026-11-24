<!-- phil-dev source-sha256=58887b6c0b3b3cde4158c626a567bc13e431c0d8ac0f0d71ce6c4fa24b6187e8 -->
# Unités invendues

Les unités d'un consignataire qui ne se sont pas vendues sont soit rendues au consignataire, soit achetées par la
boutique, comme son [entente](agreements.md) le dit. `UnsoldStock` liste les unités dues et fait l'un ou l'autre, dans
le grand livre et dans le stock.

## Unités dues

```php
foreach ($unsold->due($clock->now()) as $units) {
    $units->consignor;  // Owner
    $units->sku;
    $units->location;
    $units->units;      // what the layer still holds
    $units->dueAt;      // the end of the term, extensions included
    $units->action;     // UnsoldPolicy::ReturnToConsignor or UnsoldPolicy::BuyOut
}
```

Chaque couche consignée qui détient encore des unités est due quand l'entente en vigueur à sa réception a une durée,
alors écoulée. La liste va de la première échéance à la dernière, de tous les consignataires ou d'un seul, sur au plus
`$limit` couches — 500 par défaut —, lues parmi les 5 000 plus anciennes couches consignées. Une couche déplacée à un
autre emplacement garde l'instant de sa réception, et donc son échéance.

## Rendre des unités

```php
$slip = $unsold->giveBack('RS-2026-014', Owner::consignor('author-17'), 'store', [
    'BOOK-123' => 3,
    'ZINE-7' => 2,
], $clock->now());
$entityManager->flush();
```

Un bordereau de retour prend, SKU par SKU, les unités du consignataire à l'emplacement dans leurs couches, la réception
la plus ancienne d'abord — la première échue —, et écrit une écriture `given_back` pour chaque couche, sous la référence
du bordereau. Il retire ensuite les unités de la tablette par le port `StockMovements`. Le `ReturnSlip` qu'il renvoie
liste les couches et les unités, pour le bordereau que le consignataire signe.

Tout est vérifié avant qu'une unité bouge : plus d'unités que le consignataire n'en a à l'emplacement dans le grand livre,
ou que la tablette n'en détient, refuse tout le bordereau, et ni le grand livre ni le stock ne changent. Un bordereau
nomme de 1 à 500 SKU. Des unités pas encore échues peuvent aussi être rendues : un consignataire qui demande ses unités
plus tôt les reçoit.

## La boutique achète les unités

```php
$slip = $unsold->buyOut('BO-2026-003', Owner::consignor('author-17'), 'BOOK-123', 'store', 2, unitCost: null, at: $clock->now());
$slip->owed();  // what the shop owes the consignor for them
```

Un rachat change le propriétaire des unités, non leur place : elles quittent les couches du consignataire, les plus
anciennes d'abord, et chaque partie arrive dans une nouvelle couche de la boutique à l'emplacement, au coût unitaire
convenu, reçue à l'instant du rachat. Rien ne bouge dans le stock.

Le coût convenu est le `unitCost` donné ou, s'il est `null`, la part du consignataire sur les couches qui ont une part :
une part est déjà un prix convenu par unité. Les unités laissées à commission n'ont aucun prix avant de se vendre : leur
rachat exige un `unitCost`, et est refusé sans lui avant qu'une unité bouge.

Le consignataire a droit au coût comme pour une vente : l'écriture `bought_out` le porte comme revenu et comme part du
consignataire, sans commission. Il paraît sur le prochain [relevé](statements.md) du consignataire et dans son
[solde](payouts.md#le-solde). Les ventes ultérieures de ces unités sont celles de la boutique, à la marge de leur
nouveau coût.

## Avec le module de logistique

`LogisticsStockMovements` retire de la tablette les unités rendues par `Stock::adjust()`, sous la référence du
bordereau, après avoir vérifié que le niveau les a en main. Voir [Avec le module de logistique](logistics.md#comment-le-stock-bouge).
