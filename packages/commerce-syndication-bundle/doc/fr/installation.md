<!-- phil-dev source-sha256=16d8e2f6195665fe6decd6522f4e273808e23ba34fd2fdf547bfd0f1950f227b -->
<!-- phil-dev source-sha256=0 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le module |
| `phil/commerce-bundle` | ^0.2@dev ou ^0.3@dev | Le noyau sur lequel le module se branche : ses commandes, `SellableResolver` et `SellableAvailability` |
| `phil/standard-webhooks` | ^0.1@dev | La signature et la vérification des messages ; il exige `ext-sodium` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/money` | ^0.1@dev | Les prix de détail, les coûts et le registre |
| `doctrine/orm` | ^3.7 | Les attributs de correspondance des entités, et l'adaptateur du dépôt |
| `doctrine/dbal` | ^4.5 | Le type `datetime_utc_immutable` de chaque moment : stocké et comparé en UTC |
| `symfony/polyfill-php86` | ^1.37 | Le `\SortDirection` de PHP, que les requêtes passent à l'ORM, sur PHP 8.5 |
| `psr/clock` | ^1.0 | Le moment d'un message reçu, d'une exécution de livraison et d'une purge |
| `symfony/http-foundation` | ^8.2 | Le contrôleur auquel publient les partenaires |
| `symfony/uid` | ^8.2 | Les identifiants des messages et du registre |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et son arbre de configuration |
| `symfony/http-client` | suggéré | `HttpClientTransport`, qui livre les messages |
| `symfony/console` | suggéré | La commande `phil:commerce-syndication:deliver` |

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-syndication-bundle symfony/http-client
```

## Enregistrer

Avec Symfony Flex, la recette de chaque paquet enregistre son bundle ; celle du module écrit aussi
`config/packages/phil_commerce_syndication.yaml` sur les variables d'environnement `SYNDICATION_IDENTITY` et
`SYNDICATION_SIGNING_KEY`, la clé étant laissée vide, et la route des partenaires dans
`config/routes/phil_commerce_syndication.yaml`. Sans Flex :

Enregistrez le noyau et le module ; l'ordre des deux lignes n'a pas d'importance.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceSyndicationBundle\PhilCommerceSyndicationBundle::class => ['all' => true],
];
```

Enregistré sans le noyau, le module fait échouer la construction du conteneur :

```text
PhilCommerceSyndicationBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

## Configurer

```yaml
# config/packages/phil_commerce_syndication.yaml
phil_commerce_syndication:
    identity: shop                                   # this application's name at its partners
    signing_key: '%env(SYNDICATION_SIGNING_KEY)%'    # a whsk_ Ed25519 secret key
    tolerance: 300                                   # seconds a message's timestamp may stray from now
    max_attempts: 8                                  # delivery attempts before a message is given up
    retention_days: 90                               # days a message is kept once delivered, given up or received
    cost_grace_days: 7                               # on a supplier: days a replaced cost still pays a forwarded line
```

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `identity` | obligatoire | Le code sous lequel les partenaires connaissent cette application : l'audience que doit porter chaque message envoyé ici |
| `signing_key` | obligatoire | La clé secrète avec laquelle chaque message sortant est signé ; sa moitié publique, `whpk_…`, va aux partenaires |
| `tolerance` | `300` | De combien de secondes l'horodatage d'un message reçu peut s'écarter de maintenant ; 1 ou plus |
| `max_attempts` | `8` | Combien de fois un message est essayé avant d'être abandonné ; de 1 à 20 |
| `retention_days` | `90` | Combien de jours un message est gardé une fois livré, abandonné ou reçu, avant que `phil:commerce-syndication:purge` le supprime ; 1 ou plus |
| `cost_grace_days` | `7` | Chez un fournisseur : combien de jours un coût qu'il a publié à un revendeur paie encore une ligne transmise après que le prix suivant l'a remplacé ; 0 ou plus |

Générez une paire de clés une seule fois, gardez le secret dans l'environnement, et remettez la clé publique à chaque
partenaire :

```php
$secret = Phil\StandardWebhooks\Ed25519SecretKey::generate();
$secret->toString();               // whsk_… — SYNDICATION_SIGNING_KEY
$secret->publicKey()->toString();  // whpk_… — for the partners
```

## Routage

Chaque partenaire publie à sa propre URL. Routez les requêtes `POST` vers le contrôleur avec un attribut `partner` :

```yaml
# config/routes/syndication.yaml
syndication:
    path: /syndication/{partner}
    methods: [POST]
    controller: Phil\CommerceSyndicationBundle\Infrastructure\Http\SyndicationWebhookController
    requirements:
        partner: '[a-z0-9][a-z0-9_.-]{0,62}'
```

## Planifier la livraison

Les messages attendent dans la boîte d'envoi. Lancez la livraison chaque minute, par cron ou par Symfony Scheduler :

```sh
bin/console phil:commerce-syndication:deliver --limit=100
```

Deux exécutions simultanées sont sans danger : chacune réclame un message avant de l'envoyer. Purgez une fois par jour
ce dont la durée de conservation est dépassée :

```sh
bin/console phil:commerce-syndication:purge
```

## Stockage

Les services lisent des dépôts : avec DoctrineBundle, le module les enregistre avec son adaptateur Doctrine — générez
une migration pour ses neuf tables — ; sans lui, l'application les déclare avec ses propres adaptateurs.

| Table | Contient |
|---|---|
| `phil_commerce_syndication_partner` | Les partenaires : code, rôle, point de terminaison, clé, les UGS qui leur sont fournies et la part |
| `phil_commerce_syndication_fed_sku` | Une rangée par revendeur et par UGS fournie : `partner` et `sku` (clé primaire), `sku` indexée ; tenue à jour par l'écouteur `onFlush` `FedSkuIndex` à partir de la liste de chaque partenaire |
| `phil_commerce_syndication_outgoing` | La boîte d'envoi : id, partenaire, type, `data`, tentatives, `next_attempt_at`, `delivered_at`, `given_up_at`, `claimed_until`, `last_error` |
| `phil_commerce_syndication_received` | Les identifiants reçus : `partner` et `id` (clé primaire), type, `received_at` (indexée) |
| `phil_commerce_syndication_product` | Les produits syndiqués, par fournisseur et par UGS |
| `phil_commerce_syndication_ledger` | Ce que chaque partenaire doit pour chaque commande, et ses lignes dans `line_items` (JSON : UGS, quantité, coût unitaire) ; le crédit d'un retour sous son `return_number`, vide pour la commande elle-même ; unique par partenaire, commande et retour |
| `phil_commerce_syndication_shipment` | Les colis des commandes transmises : `partner` et `shipment` (clé primaire), `order_number` (indexé), `line_items` (JSON), `carrier`, `tracking`, `shipped_at` |
| `phil_commerce_syndication_return` | Les retours : `partner` et `return_number` (clé primaire), `order_number`, `currency`, `requested` et `received` (JSON), `state`, `credit`, `carrier`, `tracking`, `requested_at`, `received_at` ; indexés par partenaire et commande |
| `phil_commerce_syndication_published_price` | Chez un fournisseur, chaque prix publié à un revendeur : revendeur, UGS, devise, prix de détail, coût, `published_at` |

Chaque colonne de moment est un `datetime_utc_immutable` : écrit en UTC et relu en UTC, quel que soit le fuseau horaire
du processus.

### Migrer une base de données existante

- `phil_commerce_syndication_outgoing` reçoit `given_up_at` (indexée) et `claimed_until`, toutes deux nullables.
  Réglez `given_up_at` sur les rangées déjà abandonnées — sans `delivered_at` ni `next_attempt_at` — à leur dernière
  tentative, ou au moment de la migration, pour que la conservation puisse les purger ; effacez `ship_to` de leur
  `data` et de celui des rangées livrées.
- `phil_commerce_syndication_received` prend `(partner, id)` comme clé primaire au lieu de `id`, et un index sur
  `received_at`.
- `phil_commerce_syndication_fed_sku` est nouvelle. Remplissez-la à partir de la colonne JSON `skus` des partenaires
  dans la migration — une rangée par revendeur et par UGS — : une mise à jour de produit n'atteint que les revendeurs
  qu'elle liste. Par la suite, l'écouteur la tient à jour.
- `phil_commerce_syndication_ledger` reçoit `return_number` (vide sur chaque rangée existante) et `line_items` (`[]` sur
  chaque rangée existante, ou les lignes de la commande transmise quand la boîte d'envoi garde encore son message) ;
  sa contrainte d'unicité devient partenaire, commande et retour. Sans lignes, une commande n'est ni suivie ni retournée.
- `phil_commerce_syndication_shipment` et `phil_commerce_syndication_return` sont nouvelles.
- Les moments déjà stockés sont en UTC quand la base roulait en UTC ; convertissez-les autrement.
- `phil_commerce_syndication_published_price` est nouvelle. **Un fournisseur republie son flux tout de suite après la
  migration** — `Publisher::publish()` pour chaque UGS qu'il fournit — : tant qu'un prix n'est pas enregistré, chaque
  commande transmise est refusée.
