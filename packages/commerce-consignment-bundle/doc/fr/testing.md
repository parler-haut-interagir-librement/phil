<!-- phil-dev source-sha256=89eef051f52d7c5f9c7a76100ad31c4aff56fb4274a7addfb8a8da9d28aaf65d -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit/Domain` | Le grand livre sur le dépôt en mémoire : cinq exemplaires achetés et cinq consignés d'un même SKU, ventes sous chaque ordre de sortie, retours, transferts, inventaires, marges, unités rendues et rachetées, et refus ; ententes, périodes, relevés et versements |
| `tests/Unit/Application` | `ConsignmentStock` avec des mouvements de stock enregistrés, `ShippedOrders` avec des commandes de `phil/commerce-bundle` ; ententes, relevés sur deux mois avec le retour d'une vente antérieure, versements et contrepassations, unités à l'échéance et rendues ; le CSV, les données et le gabarit d'un relevé |
| `tests/Unit/Infrastructure` | Les adaptateurs de logistique sur les vrais services du module de logistique — retours de clients, ajustements, réceptions contre les lignes d'un bon de commande, l'écouteur de `UnitsShipped` —, les dépôts en mémoire et sur SQLite ; deux requêtes qui écrivent en même temps le versement d'une même clé, ou qui contrepassent un même versement ; la série des relevés |
| `tests/Functional` | Un noyau avec le noyau du commerce, le module de logistique et le bundle : une réception, une commande expédiée, sa vente écrite quand le module de logistique annonce l'expédition, deux expéditions dans un seul `flush`, `RecordShipment` qui n'écrit rien deux fois, un retour de client ; un relevé, son versement et les exemplaires invendus rendus |
| `tests/Integration` | Les dépôts Doctrine sur MySQL 8.4 et PostgreSQL 17, clés comparées octet par octet, et les mêmes versements concurrents |

La suite unitaire s'exécute sans noyau, sans serveur de base de données et sans réseau. Dans le monodépôt, Infection
maintient le paquet à 100 % des mutants couverts.

Les tests d'intégration lisent l'URL de leur serveur dans `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`, et sont
ignorés sans elle ; dans le monodépôt, `composer test:services` démarre les serveurs et définit ces variables.
