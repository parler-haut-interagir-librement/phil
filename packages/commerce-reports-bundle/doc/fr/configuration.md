<!-- phil-dev source-sha256=72b2b302949a6108b7f0054bf8248fba3f1fd71b419e146a64278cdbcc6ce0be -->
# Configuration

La configuration du bundle se trouve sous `phil_commerce_reports` :

```yaml
# config/packages/phil_commerce_reports.yaml
phil_commerce_reports:
    dead_stock_days: 90
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `dead_stock_days` | `90` | Le nombre de jours sans vente après lequel une UGS ayant des unités disponibles est du stock dormant, de 1 à 3650. Le paramètre `days` d'une requête le remplace. |

Les périodes, les formats, le plus grand nombre de lignes, les planifications et les livraisons sont des options du
bundle de rapports, sous `phil_reporting`.
