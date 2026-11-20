<!-- phil-dev source-sha256=cb358cbd0f7002b0726bb75aa7c0fe0d8360e97187fa56fd55e77677232cb869 -->
<!-- phil-dev source-sha256=0 -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `Partner`, `OutgoingMessage`, `ReceivedMessage`, `SyndicatedProduct`, `FeedItem`, `LedgerEntry` | Chaque règle, le calendrier des nouvelles tentatives, l'abandon, les descriptions arrivées dans le désordre, le règlement |
| `Partners`, `Publisher`, `Courier`, `Ledger` | L'alimentation des revendeurs et leurs coûts, la signature et la livraison, les nouvelles tentatives, les limites, les partenaires suspendus, les soldes |
| `Forwarder`, `SyndicatedSellables` | Le regroupement par fournisseur, le registre, l'idempotence, les refus ; la résolution et la disponibilité |
| `Inbox`, `PartnerKey` | La vérification par la clé propre au partenaire, l'audience, la péremption, la déduplication, chaque type de message et ses refus |
| `ShipmentTracking`, `PartnerReturns`, `PartnerShipment`, `PartnerReturn`, `MessageData` | Un fournisseur et un revendeur, chacun avec son propre stockage, qui échangent un colis et un retour par des messages signés : chacun envoyé une seule fois, les bornes contre le registre des deux côtés, un retour reçu une seule fois et jamais au-delà de ce qui a été annoncé, des crédits égaux des deux côtés et un crédit différent refusé, les soldes après le crédit |
| `SyndicationWebhookController`, `HttpClientTransport`, `DeliverMessagesCommand` | Les réponses, la requête HTTP, la limite de la commande |
| Correspondance | Chaque colonne des neuf tables |
| `DoctrineSyndicationRepository`, `FedSkuIndex` | Sur SQLite en mémoire : les revendeurs à qui une UGS est fournie, trouvés par l'index au fil des changements de listes, les derniers prix et les prix en vigueur lus pour plusieurs revendeurs ou UGS d'un coup |
| `PhilCommerceSyndicationBundle` | La configuration et ses bornes, les services avec DoctrineBundle, rien sans lui, la correspondance, le refus sans le noyau |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, le noyau
du commerce et le module : `SyndicationFlowTest` enregistre des partenaires, met le flux en file, reçoit deux fois un
message signé par le contrôleur, règle le registre, et stocke un colis, un retour et son crédit dans un processus d'un
autre fuseau horaire, en relisant chaque moment en UTC. Installé seul, là où FrameworkBundle est un prérequis de
développement, la suite est sautée.

Cible de fuzzing, `partner-messages` donne au lecteur des colis et des retours du JSON que personne n'a écrit, à partir
des germes de `tests/Fuzz/corpus/partner-messages` : il retourne un colis ou un retour, ou refuse le message avec
`InvalidValue`, et ne lève jamais autre chose ni n'avertit. Depuis la racine du monodépôt :
`composer fuzz -- partner-messages`.

Une suite d'intégration, `tests/Integration`, fait rouler l'adaptateur Doctrine sur MySQL 8 et PostgreSQL 17 — ses
tables créées à partir de la correspondance dans un processus d'un autre fuseau, les moments relus en UTC, la
réclamation et la purge d'un message, les prix en vigueur, le registre d'une commande et de son retour, les colis et les
retours relus — quand `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL` nomment un serveur, et elle est sautée sinon :

```sh
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/commerce-syndication-bundle/tests/Integration
```

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-syndication-bundle/tests
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
composer bench -- packages/commerce-syndication-bundle/tests/Benchmark
```
