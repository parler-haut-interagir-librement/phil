<!-- phil-dev source-sha256=2d6e7bd2cb14b004591298a392791d12942d01add8385854b724a723b1444cbd -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau :

- chaque formule — l'écoulement et son arrondi, un stock de zéro, le seuil du stock dormant à la seconde près — seule ;
- les totaux des ventes avec plusieurs devises, des retours plus grands que les ventes d'une période et des lignes sans
  valeur ;
- les trois rapports sur les adaptateurs en mémoire ;
- `PosSoldLines` sur les entités du point de vente et du noyau de commerce, derrière des dépôts en mémoire ;
- `LogisticsStockFigures` sur les niveaux et le journal du module de logistique, derrière un dépôt en mémoire ;
- la configuration, les étiquettes et les alias que le bundle enregistre.

La suite fonctionnelle, sous `tests/Functional`, démarre un noyau avec FrameworkBundle, le bundle de rapports et ce
bundle, et exporte chaque rapport en CSV par `Reports` du bundle de rapports.

Aucun adaptateur du bundle n'écrit de requête : ils lisent les modules par leurs dépôts, de sorte que les suites n'ont
besoin d'aucun serveur de base de données. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants
couverts.
