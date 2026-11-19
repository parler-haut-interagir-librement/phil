<!-- phil-dev source-sha256=00f235d8e6ed8f7f2c3e6c938dbc0ec4c4f54bd972168772c6343aa8002f646e -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `Register`, `CashRounding` | Chaque règle, l'arrondi à l'incrément le plus proche dans les deux sens |
| `CashSession`, `CashMovement` | Le fonds de caisse, chaque sorte de mouvement, la fermeture, l'excédent et le manque, une session fermée |
| `RegisterSale`, `SessionPayment`, `HeldSale`, `Layaway`, `ImportedSale` | Chaque règle, la vie d'une mise de côté |
| `Registers`, `CashSessions`, `SessionReport` | Une seule session ouverte par caisse, une seule caisse principale par lieu, des terminaux à NIP seulement à la caisse principale, le rapport de fin de journée au complet |
| `TerminalPayments`, `TerminalGateway`, `TerminalAdapters`, `FakePaymentTerminal`, `TerminalNotificationController` | Un débit présenté, capturé et compté une seule fois, échoué, annulé, remboursé ; un appareil qui échoue ; des notifications falsifiées et inconnues ; chaque réponse |
| `SalePresets`, `CustomerDisplay`, `TillDraws`, `CashDifferences` | Les préréglages et le client identifié, l'afficheur sans la personne, les dés saisis une fois payé, la période et le seuil du rapport |
| `CashierSwitch` | Désactivé par défaut, NIP faibles refusés, empreinte et nouvelle empreinte, la limite de débit selon la caisse et l'utilisateur, un NIP manquant lu comme un mauvais |
| `RegisterCheckout`, `RegisterPayments`, `RegisterGateway` | La mise en attente, le pourboire, l'enregistrement de la vente ; une session à prix taxes comprises, un panier tarifé autrement refusé ; l'argent comptant avec arrondi et monnaie rendue, les méthodes enregistrées, les paiements partagés, les remboursements |
| `Layaways`, `OfflineSale`, `OfflineSales` | Les acomptes, l'achèvement, l'annulation ; la lecture d'une vente, l'importation unique, le recalcul des prix, le plafonnement des paiements, la caisse principale seulement, jamais par-dessus une commande existante |
| `OfflineSaleController` | Chaque réponse |
| Correspondance | Chaque colonne des douze tables |
| `DoctrinePosRepository` | `tests/Integration` : ce qui est gardé relu, ainsi que les filtres et l'ordre de chaque requête, les moments gardés en UTC, sur SQLite en mémoire toujours, et sur MySQL 8.4 et PostgreSQL 17 quand leurs URL sont définies |
| `PhilCommercePosBundle` | La configuration et ses bornes, les services avec DoctrineBundle, rien sans lui, la correspondance, le refus sans le noyau, un débit sur le terminal factice réglé par le contrôleur, le changement de caissier désactivé et activé, le contrôleur Stimulus trouvé par StimulusBundle par AssetMapper et chargé à la demande |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, le noyau
du commerce et le module : `RegisterFlowTest` fait tourner une journée — une vente en attente, une vente en argent
comptant avec pourboire, une mise de côté, un dépôt, la fermeture. Installé seul, là où FrameworkBundle est un
prérequis de développement, la suite est sautée.

`tests/js` exécute la file d'attente hors ligne, son stockage IndexedDB et son contrôleur Stimulus avec l'exécuteur de
tests de Node, Stimulus étant résolu vers un *stub* et IndexedDB vers un petit faux qui règle ses requêtes comme le fait
un navigateur.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-pos-bundle/tests
```

```sh
node --import ./packages/commerce-pos-bundle/tests/js/register.mjs --test packages/commerce-pos-bundle/tests/js/offline-queue.test.mjs
```

La suite d'intégration tourne sur MySQL et PostgreSQL avec les serveurs de `phil/dev-tools`, dans Docker ; sans leurs URL,
ces deux-là sont sautées. Elle supprime et crée les tables du module dans la base de test :

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/commerce-pos-bundle/tests/Integration
vendor/bin/phil-dev services down
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `CashSessions::report()` sur SQLite en mémoire, dans une
unité de travail neuve, pour des sessions de 20, 200 et 800 ventes. Depuis la racine du monodépôt :

```bash
composer bench -- packages/commerce-pos-bundle/tests/Benchmark
```
