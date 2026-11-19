<!-- phil-dev source-sha256=050979606df5745e18c437f9a6cbf3e3a6540c32d6ae213c62b8bb5dd649f066 -->
<!-- phil-dev source-sha256=0 -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `Location`, `StockLevel`, `StockMovement`, `Reservation` | Chaque mouvement et ses bornes, les comptages sous les réservations, rien sous zéro, les réservations tenues une seule fois |
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
| `Parcel`, `ShippingAddress`, `LabelRequest`, `ShippingCost`, `ShippingMargin` | Chaque borne, les adresses incomplètes refusées pour une étiquette, les champs d'une ligne, une marge qui est une perte |
| `ShippingLabels` | Chaque transporteur interrogé, ceux qui ne répondent pas laissés de côté, le moins cher d'abord, l'échec d'un transporteur montré, un coût noté une seule fois, la marge dans la devise de la commande |
| `CanadaPostCarrier`, `CanadaPostCredentials` | Les requêtes envoyées — le jeton, les en-têtes, les corps JSON, les délais — et les réponses lues, par le `MockHttpClient` de Symfony sur des réponses gardées dans `tests/Fixtures/CanadaPost` ; le jeton gardé, renouvelé et oublié ; chaque réponse non fiable refusée, les liens vers d'autres hôtes refusés, les réponses trop grandes coupées |
| Mappage | Chaque colonne des seize tables |
| `PhilCommerceLogisticsBundle` | Les services avec DoctrineBundle, rien sans lui sauf les types de documents, le mappage, les étiquettes des transporteurs et des imprimeurs, les étiquettes du noyau, la série et la fenêtre des conventions configurées, Postes Canada configuré ou non, le refus sans le noyau |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, le noyau
du commerce et le module. `LogisticsFlowTest` reçoit du stock, réserve et expédie une commande sur deux emplacements,
facture sa livraison, et relit chaque niveau, chaque réservation et chaque ligne du journal ; elle stocke le coût de
deux expéditions et relit la marge de la commande ; elle stocke des commandes en attente, des conventions et des
travaux d'impression, des bons de commande, des numéros de série, une liste de prélèvement numérotée par le registre du
noyau et des vagues, et relit chacun par sa propre requête ; et elle engage le stock d'une précommande par les
`DeferredSales` du noyau. Aucun test n'appelle un transporteur par le réseau. Quand le module est installé seul, là où
FrameworkBundle est un prérequis de développement, la suite est sautée.

Une suite d'intégration, `tests/Integration`, fait rouler l'adaptateur Doctrine sur MySQL 8 et PostgreSQL 17 — ses
tables créées à partir du mappage, des bons de commande avec des *SKU* numériques, des numéros de série, des
conventions trouvées par leurs jours, des documents avec et sans expédition, des expéditions et des vagues relues —
quand `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL` nomment un serveur, et elle est sautée sinon :

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

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine
du monodépôt :

```sh
composer bench -- packages/commerce-logistics-bundle/tests/Benchmark
```
