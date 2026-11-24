<!-- phil-dev source-sha256=dc310b52000dfc07b911507171025c15fef5242a3ecda48c879d7bbaac506912 -->
# Configuration

La configuration du bundle se trouve sous `phil_commerce_consignment` :

```yaml
# config/packages/phil_commerce_consignment.yaml
phil_commerce_consignment:
    issue_order: oldest_first
    issue_order_by_sku:
        BOOK-123: consigned_first
        ZINE-7: owned_first
    statement_number_prefix: ST-
    record_shipments: true
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `issue_order` | `oldest_first` | Quelles unités d'un SKU sortent en premier quand la boutique en détient des achetées et des consignées : `owned_first`, `consigned_first` ou `oldest_first`. |
| `issue_order_by_sku` | `[]` | Un autre ordre de sortie pour les SKU nommés, SKU → ordre. Les SKU sont conservés tels qu'ils sont écrits : `Book-1` n'est pas `book-1`. |
| `statement_number_prefix` | `ST-` | Le préfixe des numéros de relevé, avant huit chiffres — `ST-00000001` — : jusqu'à 24 lettres, chiffres, points, tirets, barres obliques ou croisillons, ou rien. Voir [Relevés](statements.md#les-numeros). |
| `record_shipments` | `true` | Écrit les ventes d'une commande au grand livre quand le module de logistique annonce `UnitsShipped`, dans la transaction de l'expédition. `false` le laisse à l'application : `ShippedOrders::record()` ou un message `RecordShipment`. Voir [Avec le module de logistique](logistics.md#commandes-expediees). |

L'ordre de sortie s'applique aux ventes et aux transferts entre emplacements : voir
[Le grand livre de propriété](ledger.md#lordre-de-sortie). Une règle que la configuration ne peut pas exprimer — par
famille de produits, par contrat de chaque consignataire — est une `IssueOrderPolicy` de l'application : voir
[Points d'extension](extension-points.md).

Ce que la boutique a convenu avec chaque consignataire — combien de temps ses unités restent, ce qui arrive aux
invendues, à quelle fréquence il reçoit un relevé — est une donnée, non une configuration : voir
[Ententes](agreements.md).
