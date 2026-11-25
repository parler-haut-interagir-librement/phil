<!-- phil-dev source-sha256=eef225dcf645646a21a6c073be9866816087daef32e1d3e43d965657288ccbd0 -->
<!-- phil-dev source-sha256=0 -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `Location`, `StockLevel`, `StockMovement`, `Reservation` | Chaque mouvement et ses bornes, les comptages sous les réservations, rien sous zéro, les réservations tenues une seule fois |
| `UnitsShipped`, `ShippedLine` | Un événement par appel qui a déplacé des unités, une ligne par *SKU* et par emplacement, les unités en attente sur la ligne de leur niveau, le moment en UTC, rien pour une commande sans rien de retenu, le journal écrit d'abord, des unités expédiées sans répartiteur |
| `Stock::returnFromCustomer()`, `Reservation::returnPart()` | Des unités remises sur n'importe quelle tablette sous le numéro de la commande, comme `returned` ; jamais plus que ce qui est parti et n'est pas revenu, sur les réservations de la commande pour le *SKU*, dans leur ordre ; un autre *SKU* n'est pas compté ; rien déplacé lors d'un refus |
| `Stock::adjust()`, `StockLevel::adjust()` | Des différences à la hausse et à la baisse au journal comme `adjusted`, jusqu'à zéro et pas en dessous, une différence de zéro refusée avant toute recherche, moins en main que de réservé |
| `PurchaseLine` | Le coût unitaire et le propriétaire reportés à la réception, des *SKU* numériques, des lignes d'unités seules qui ne stockent rien, chaque borne d'une ligne |
| `Costing`, `CostLayer`, `CostShare`, `ShopUnitsFirst` | Les deux méthodes par les réceptions, les expéditions, les comptages, les ajustements, les transferts et les retours ; les coûts d'approche répartis à l'unité mineure près, leurs restes, par unités quand aucune ligne ne coûte rien ; les unités sans coût ; les retours au coût auquel ils sont partis ; les unités en consignation hors de toute couche de valeur ; le `StockOwnership` d'une application interrogé et ses mauvaises réponses refusées |
| `DeliveryMethod` | Chaque règle, les zones et la devise desservies, le seuil de gratuité |
| `Stock` | Le journal de chaque mouvement, les emplacements inconnus et identiques, les emplacements fermés, les réservations par priorité, les pénuries qui ne réservent rien, la libération et l'expédition une seule fois, les commandes en attente réservées au premier emplacement ouvert et expédiées seulement une fois reçues, rien déplacé quand un niveau manque d'unités en main |
| `StockPolicy`, `DeferredStock` | Les commandes en attente permises et interdites, aucune sans stockage des politiques ; le stock d'une vente différée retenu et libéré au moment de l'horloge |
| `ConventionStock`, `Convention`, `ConventionQuantity`, `PrintJob` | Les quantités saisies et ce qui reste à imprimer, la fenêtre à ses deux bouts, les commandes passées en ligne envoyées à l'impression ou réservées selon le disponible, un autre canal qui prend dans le stock, un chemin décidé une seule fois, les services `PrintOnDemand` appelés une fois par commande et par *SKU* |
| `Purchasing`, `PurchaseOrder` | Les réceptions dans le journal sous le numéro du bon, les *SKU* numériques, les quantités au-delà de l'attendu, les bons fermés, chaque borne d'un bon |
| `SerialNumbers`, `SerialNumber` | Enregistrés, déplacés, expédiés et remis en stock ; jamais plus qu'en main, un numéro de série une seule fois, des lots bornés |
| `ShippingDocuments`, `LogisticsDocumentTypes` | Les deux séries et leurs préfixes, les listes de prélèvement par emplacement avec les lignes non suivies en dernier, les bordereaux de livraison avec le message-cadeau et les prix cachés, un document numéroté une seule fois |
| `ShippingWaves`, `ShippingWave` | Les commandes planifiées, déplacées et retirées, les vagues reportées et énumérées par jour, une vague libérée laissée telle quelle |
| `DeliveryOptions`, `ShippingProcessor` | Les modes offerts par priorité, les frais par un vrai `OrderModifier`, la gratuité après les promotions, les modes qui ne desservent plus |
| `Shipments`, `Shipment`, les réservations partielles | Plusieurs colis, ce qui reste, l'état d'exécution, les lignes non suivies, les refus, la libération après une partie |
| `PaidShipmentAuthorization`, `AnyShipmentAuthorization`, `DenyShipmentForcing`, `ShipmentForced` | Une commande impayée, avec un acompte seulement, un paiement non capturé, payée en une ou deux parties, payée en trop, remboursée sous son total ou jusqu'à lui, de rien ; la première autorisation qui dit oui qui met fin à la recherche ; personne qui force par défaut ; une expédition forcée gardée avec qui et pourquoi, ses unités sorties de la tablette, annoncée en UTC ; son auteur et sa raison vérifiés, une commande passée et ce qui reste toujours exigés |
| `Parcel`, `ShippingAddress`, `LabelRequest`, `ShippingCost`, `ShippingMargin` | Chaque borne, les adresses incomplètes refusées pour une étiquette, les champs d'une ligne, une marge qui est une perte |
| `ShippingLabels` | Chaque transporteur interrogé, ceux qui ne répondent pas laissés de côté, le moins cher d'abord, l'échec d'un transporteur montré, un coût noté une seule fois, la marge dans la devise de la commande |
| `CanadaPostCarrier`, `CanadaPostCredentials` | Les requêtes envoyées — le jeton, les en-têtes, les corps JSON, les délais — et les réponses lues, par le `MockHttpClient` de Symfony sur des réponses gardées dans `tests/Fixtures/CanadaPost` ; le jeton gardé, renouvelé et oublié ; chaque réponse non fiable refusée, les liens vers d'autres hôtes refusés, les réponses trop grandes coupées |
| Mappage | Chaque colonne des dix-huit tables, `returned` à 0 par défaut, chaque valeur des énumérations de coûts allant dans sa colonne |
| `PhilCommerceLogisticsBundle` | Les services avec DoctrineBundle, rien sans lui sauf les types de documents, le mappage, les étiquettes des transporteurs et des imprimeurs, les étiquettes du noyau, la série et la fenêtre des conventions configurées, Postes Canada configuré ou non, le calcul des coûts désactivé par défaut et configuré, sa devise obligatoire, le refus sans le noyau |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, le noyau
du commerce et le module. `LogisticsFlowTest` reçoit du stock, réserve et expédie une commande sur deux emplacements,
facture sa livraison, et relit chaque niveau, chaque réservation et chaque ligne du journal ; elle stocke le coût de
deux expéditions et relit la marge de la commande ; elle stocke des commandes en attente, des conventions et des
travaux d'impression, des bons de commande, des numéros de série, une liste de prélèvement numérotée par le registre du
noyau et des vagues, et relit chacun par sa propre requête ; et elle engage le stock d'une précommande par les
`DeferredSales` du noyau. `StockPortsFlowTest` annonce une expédition partielle à un écouteur du répartiteur d'événements
du noyau une seule fois, quel que soit le nombre de vidages qui suivent, reprend un retour de client, ajuste un niveau,
et relit le coût unitaire et le propriétaire d'une ligne de bon de commande, et un bon de commande sans conditions
stocké comme null. `CostingFlowTest` configure le calcul des coûts en premier entré, premier sorti, reçoit avec un coût
d'approche et des unités en consignation, expédie, passe un vol en perte, reprend un retour et lit la valeur par
`InventoryValuation`. Aucun test n'appelle un transporteur par le réseau. Quand le module est installé seul, là où
FrameworkBundle est un prérequis de développement, la suite est sautée.

L'adaptateur Doctrine roule sur SQLite dans la suite unitaire, `tests/Unit/Infrastructure/SqliteLogisticsTest`, et sur
MySQL 8.4 et PostgreSQL 17 dans la suite d'intégration, `tests/Integration` — ses tables créées à partir du mappage, les
filtres et l'ordre de chaque requête, les égalités départagées par sa clé suivante, des bons de commande avec des *SKU* numériques et les
conditions de leurs lignes, des numéros de série, des conventions trouvées par leurs jours, des documents avec et sans
expédition, des expéditions et des vagues relues ; deux ajustements d'un même niveau faits en même temps dans deux
gestionnaires d'entités, le second refusé par la version du niveau puis repris, les deux comptés ; une base de
0.1.0-alpha comparée au mappage, qui ajoute `returned`, `line_terms`, `reason` et les deux tables du calcul des coûts et
ne change rien d'autre, ses lignes relues après le changement ; des couches et des entrées de coût lues dans une même
unité de travail avant d'être vidées et d'une unité de travail à l'autre, le stock évalué à un moment, le coût des
ventes d'une période et le journal des coûts paginé. SQLite roule sur un fichier du répertoire temporaire, ou sur celui
que nomme `PHIL_TEST_SQLITE_PATH`, et n'a besoin d'aucun serveur ; MySQL et PostgreSQL roulent quand `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL` nomment
un serveur, et sont sautés sinon :

```sh
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/commerce-logistics-bundle/tests/Integration
```

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-logistics-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. `CostingBench`
mesure une expédition qui vide de 1 à 100 couches de coût sous les deux méthodes, et un coût d'approche réparti sur de
1 à 100 lignes. Depuis la racine du monodépôt :

```sh
composer bench -- packages/commerce-logistics-bundle/tests/Benchmark
```
