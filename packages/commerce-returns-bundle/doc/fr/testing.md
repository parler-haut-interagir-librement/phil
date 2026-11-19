<!-- phil-dev source-sha256=8783a6d265ef61c4395ee2e955585849ae0d81428d28065e6401d26109daabeb -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `ReturnRequest`, `ReturnLine`, `ReturnLabel`, les énumérations | Chaque règle, chaque étape et les étapes refusées, les questions, l'expiration, les étiquettes, les bordereaux numérotés une seule fois, l'anonymisation ; la valeur, les frais et leur arrondi, les unités manquantes, les imputations des promotions spéciales jamais sous zéro, la livraison ; chaque refus |
| `Returns`, `Settlement`, `ReturnPolicy` | La valeur des lignes avec promotions et taxes, les quantités restantes et libérées, le délai depuis le passage et depuis la dernière expédition, la limite de débit par client, la remise en stock, les remboursements à plusieurs paiements, les manques, le crédit en magasin, les échanges pour moins et pour plus, les questions, l'expiration, les étiquettes, les événements de chaque étape |
| `ReturnValuation`, `DeliveryRefund` | Une promotion spéciale brisée par un produit, par les deux, paire par paire, la taxe qu'elle a épargnée, son imputation plafonnée ; un ensemble refusé en partie ; la livraison sous chaque politique, une fois par commande, libérée, exonérée |
| `ReturnCreditNotes`, `ReturnCreditNote` | Les lignes partielles avec leur taxe, les frais, les promotions spéciales, la livraison, les unités manquantes ; une note par retour ; les lignes créditées en entier par le noyau, avant et sous le verrou ; les retours non reçus, les commandes sans reçu |
| `ReturnSlips`, `ReturnDocumentTypes`, `PagedMediaReturnSlips` | Un bordereau numéroté une seule fois dans sa série, ses lignes et son étiquette ; seulement un retour autorisé ; un PDF dans la locale demandée, sur plus de pages pour beaucoup de lignes, en anglais sans traducteur |
| `CatalogSetParts`, `PromotionSpecials`, `LogisticsShipmentDates` | Les produits composés comme ensembles, les prix de lot comme promotions spéciales, la dernière expédition d'une commande |
| `ReturnsGateway` | La méthode `exchange`, ses remboursements |
| `StoredValueStoreCredit` | Le crédit par le module de valeur stockée |
| Correspondance | Chaque colonne des tables |
| `PhilCommerceReturnsBundle` | La configuration et ses bornes, la politique, les services avec DoctrineBundle, le crédit en magasin, les ensembles, les promotions spéciales et les dates d'expédition avec leurs modules, les bordereaux avec ou sans moteur de rendu, rien sans Doctrine, la correspondance, le refus sans le noyau |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, le noyau
du commerce, les modules de valeur stockée, de catalogue et de promotions, et celui-ci : `ReturnFlowTest` mène un retour
jusqu'au crédit en magasin, refuse une partie d'un ensemble du catalogue, reprend une promotion spéciale, rend la
livraison, numérote un bordereau et une note de crédit dans leurs séries et les relit, fait expirer un retour trouvé en
SQL, et annonce chaque étape à un écouteur. Installé seul, là où FrameworkBundle est un prérequis de développement, la
suite est sautée.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-returns-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/commerce-returns-bundle/tests/Benchmark
```
