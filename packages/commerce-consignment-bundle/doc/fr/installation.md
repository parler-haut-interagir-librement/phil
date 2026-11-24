<!-- phil-dev source-sha256=eba59cba3445c074640e8981278cdee9860cca66a07b9225a25902922cd1025b -->
# Installation

```sh
composer require phil/commerce-consignment-bundle
```

Le bundle est un module du noyau du commerce, construit sur le stock de son module de logistique : enregistrez les
trois bundles, avec DoctrineBundle, dans `config/bundles.php`. Sans le noyau ou le module de logistique, le conteneur
refuse de se construire.

```php
Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle::class => ['all' => true],
Phil\CommerceConsignmentBundle\PhilCommerceConsignmentBundle::class => ['all' => true],
```

La recette Flex enregistre le bundle et écrit `config/packages/phil_commerce_consignment.yaml` avec l'ordre de sortie
par défaut.

Le paquet exige PHP 8.5 ou une version plus récente et Symfony 8.2 ou une version plus récente, avec
`phil/commerce-bundle` 0.4 et `phil/commerce-logistics-bundle` 0.1.1.

## Les tables

Le bundle associe cinq tables, enregistrées auprès de Doctrine par le bundle lui-même ; écrivez leur migration comme
d'habitude :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Table | Ce qu'elle contient |
|---|---|
| `phil_commerce_consignment_layer` | Une ligne par couche : `id`, `origin` (la couche de la réception), `sku`, `location`, `consignor` (vide pour la boutique), `currency`, `unit_cost`, `share`, `commission_ppm`, `received_at`, `reference`, `received`, `remaining` et `version`. |
| `phil_commerce_consignment_entry` | Une ligne par mouvement, jamais modifiée : `id`, `layer`, `sku`, `location`, `consignor`, `kind`, `quantity`, `reference`, `moved_at` et, pour une vente, un retour ou un rachat, `currency`, `unit_price`, `revenue` et `cost`. |
| `phil_commerce_consignment_agreement` | Une ligne par version d'une entente, jamais modifiée : `id`, `consignor`, `effective_from` (unique avec le consignataire), `term_days`, `unsold_policy`, `extension_days`, `extensions`, `frequency` et `recorded_at`. |
| `phil_commerce_consignment_statement` | Une ligne par relevé, jamais modifiée : `id`, `number` (unique), `consignor`, `currency`, `period_start`, `period_end`, `issued_at`, les unités vendues, retournées, rachetées et rendues, `revenue`, `share`, `commission`, ainsi que les lignes et les unités en main figées, dans `entry_lines` et `held_units`, en JSON. |
| `phil_commerce_consignment_payout` | Une ligne par versement ou contrepassation, jamais modifiée : `id`, `idempotency_key` (unique), `consignor`, `currency`, `amount`, `paid_at`, `method`, `reference`, `reverses` (unique : un versement n'est contrepassé qu'une fois) et `recorded_at`. |

Les montants sont des entiers dans la plus petite unité de la devise. Sur MySQL et MariaDB, les clés — identifiants,
SKU, emplacement, consignataire, référence — prennent la collation binaire `utf8mb4_bin`, si bien que le SKU `bk-1`
n'est jamais `BK-1` ; PostgreSQL et SQLite les comparent déjà octet par octet. Chaque instant est de type
`datetime_utc_immutable` : il est écrit et lu en UTC quel que soit le fuseau horaire du processus, et revient en UTC.

## Twig

Le gabarit du relevé, `@PhilCommerceConsignment/statement.html.twig`, est trouvé par TwigBundle dans le répertoire
`templates/` du bundle ; sans Twig, le bundle fonctionne de la même façon et l'application dessine les relevés à partir
de `StatementView::data()` comme elle l'entend.

## Messenger

Avec `symfony/messenger` installé, le bundle enregistre le gestionnaire de `RecordShipment`, qui écrit au grand livre
ce qu'une commande a expédié : voir [Avec le module de logistique](logistics.md#commandes-expediees).
