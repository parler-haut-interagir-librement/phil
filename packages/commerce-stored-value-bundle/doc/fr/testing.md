<!-- phil-dev source-sha256=983588da8e0116d6ec274a3686f53a326042679474808f1a0407d13a927c4419 -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `Instrument`, `Movement`, `MovementKind`, `GiftCardCode` | Chaque règle, l'expiration, l'annulation, l'historique d'un solde, la lecture et la génération des codes |
| `GiftCards`, `GiftCardSellables`, `StoreCredits` | La vente à la valeur nominale, l'émission une seule fois par commande, la recharge, l'annulation, le crédit accordé par client et par devise, une recharge ou un octroi rejoué sous sa clé d'idempotence |
| `StoredValuePayments`, `StoredValueGateway` | Le paiement de ce que l'instrument peut couvrir, un montant choisi, des refus qui ne laissent rien derrière eux, les remboursements vers l'instrument |
| Correspondance | Chaque colonne des deux tables |
| `PhilCommerceStoredValueBundle` | La configuration et ses bornes, les services avec DoctrineBundle, le vendable seul sans lui, la correspondance, le refus sans le noyau |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, le noyau
du commerce et le module : `StoredValueFlowTest` paie une commande avec une carte-cadeau et du crédit en magasin, puis
rembourse une partie du paiement par carte. Installé seul, là où FrameworkBundle est un prérequis de développement, la
suite est sautée.

Une suite d'intégration s'exécute sur une vraie base de données, par un gestionnaire d'entités de la correspondance du
module : six processus émettent les cartes d'une même commande au même moment et une seule série de cartes est
stockée ; une requête qui a compté les cartes trop tôt trouve celles de la première, et laisse utilisables la
transaction et le gestionnaire d'entités de l'appelant ; la base refuse deux fois un rang pour une commande, et deux
fois une clé pour un instrument. Elle s'exécute chaque fois sur un fichier SQLite, et sur MySQL 8 et PostgreSQL 17
quand `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL` y pointent — `composer test:services` les démarre depuis la
racine du monodépôt.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-stored-value-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```
