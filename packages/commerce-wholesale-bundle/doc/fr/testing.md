<!-- phil-dev source-sha256=b707e252b8fa6435647a3b0ae1a73af4f0661b3ea72507c4af7788229f307e0d -->
# Tests

## Comment le module est testé

| Domaine | Ce qui est couvert |
|---|---|
| `CustomerGroup`, `Membership` | Chaque règle, le prix du groupe et son arrondi, le passage d'un groupe à l'autre |
| `GroupPrices`, `CustomerGroups` | Les produits tarifés pour le groupe, les autres articles vendables et les autres groupes laissés tels quels, une liste de prix qui passe en premier par un vrai `OrderModifier`, l'adhésion, le déplacement, le départ, les commandes minimales |
| `CreditAccount`, `Receivable`, `CreditSales`, `AccountGateway` | Les limites, les modalités, la suspension, les achats à crédit, les paiements de l'acheteur, les créances en souffrance, les remboursements qui créditent la créance |
| `QuantityRule`, `QuantityRules` | Chaque borne, chaque infraction et la quantité permise la plus proche, la règle propre à un groupe avant celle de tous les groupes, une seule lecture par *SKU* |
| `AllowedQuantity`, `AllowedQuantityValidator` | Chaque violation avec son code et ses paramètres par le validateur de Symfony, les messages personnalisés, les autres contraintes et valeurs refusées |
| `QuantityGuard` | Par un vrai `OrderModifier` : les quantités permises tarifées par les résolveurs qui le suivent, la quantité entière de la ligne vérifiée, les changements de quantité et de groupe refusés, la commande laissée telle quelle |
| Correspondance | Chaque colonne des cinq tables |
| `PhilCommerceWholesaleBundle` | Les services avec DoctrineBundle, rien sans lui, la correspondance, la place du gardien parmi les résolveurs de prix, les traductions, le refus sans le noyau |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur une base SQLite en mémoire, le noyau
du commerce et le module : `WholesaleFlowTest` stocke un groupe et une appartenance et les relit, et stocke des règles
de quantité dont il lit les messages en français et en anglais. Quand le paquet est
installé seul, FrameworkBundle étant alors un prérequis de développement, la suite est sautée.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-wholesale-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `GroupPrices::priceFor()` sur chaque ligne de paniers de 5, 20
et 100 lignes, sur SQLite en mémoire, avec le groupe déjà chargé et dans une unité de travail neuve, et
`QuantityGuard::priceFor()` sur les mêmes paniers, les règles de chaque ligne lues par le validateur — environ 50 µs
par ligne sur un portable. Depuis la racine du monodépôt :

```bash
composer bench -- packages/commerce-wholesale-bundle/tests/Benchmark
```
