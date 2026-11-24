<!-- phil-dev source-sha256=9dbfd242409b84ef442e279d6e8035193e88b7473ac76e6dc0ecfcbfd835dc70 -->
<!-- phil-dev source-sha256=0 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le module déclare et sur lequel il est testé |
| `phil/commerce-bundle` | ^0.4@dev | Le noyau auquel le module se branche : ses commandes, `OrderProcessor`, `StockCommitments` et son registre des types de documents |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/money` | ^0.1@dev | Les prix des modes de livraison, les coûts des expéditions et la valeur du stock, répartie par `Money::allocate()` |
| `phil/url` | ^0.1@dev | Les liens des réponses d'un transporteur, lus comme le fait un navigateur |
| `ext-mbstring` | toute | La longueur des champs d'une adresse, en caractères |
| `doctrine/orm` | ^3.7 | Les attributs de mappage des entités de logistique, et l'adaptateur des dépôts |
| `symfony/uid` | ^8.2 | Les UUID ordonnés dans le temps des mouvements et des réservations |
| `symfony/event-dispatcher-contracts` | ^3.6 | `UnitsShipped`, envoyé quand l'application a un répartiteur d'événements |
| `symfony/http-client-contracts` | ^3.6 | Le client HTTP par lequel l'adaptateur de Postes Canada interroge |
| `symfony/clock`, `psr/clock` | ^8.2, ^1.0 | Le moment où expire le jeton d'accès du transporteur |
| `symfony/service-contracts` | ^3.6 | L'adaptateur oublie son jeton entre les requêtes d'un *worker* |
| `symfony/dependency-injection` | ^8.2 | La classe du bundle est un `AbstractBundle` |
| `symfony/config` | ^8.2 | L'arbre de configuration du module |
| `symfony/polyfill-php86` | ^1.37 | Le `\SortDirection` de PHP, que les requêtes passent à l'ORM, sur PHP 8.5 |

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-logistics-bundle
```

## Enregistrer

Avec Symfony Flex, la recette de chaque paquet enregistre son bundle ; les valeurs par défaut du module n'exigent aucun fichier de configuration.
Sans Flex :

Enregistrez le noyau et le module ; l'ordre des deux lignes n'a pas d'importance.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle::class => ['all' => true],
];
```

Enregistré sans le noyau, le module fait échouer la construction du conteneur :

```text
PhilCommerceLogisticsBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

## Configurer

Chaque clé a une valeur par défaut ; aucune n'est obligatoire.

```yaml
# config/packages/phil_commerce_logistics.yaml
phil_commerce_logistics:
    documents:
        delivery_note_prefix: DN-   # the series of delivery notes in the core's register
        picking_slip_prefix: PS-    # the series of picking slips
        digits: 8                   # 1 to 18
    conventions:
        window_days: 30             # a convention within this many days is near: 0 to 366
        online_channels: [web]      # orders of these channels, and orders without one, are placed online
    costing:
        enabled: false              # the value of the stock at cost; see Costing
        method: weighted_average    # or fifo
        currency: ~                 # the ISO 4217 code of every cost, required once costing is on
    carriers: {}                    # none by default
```

Voyez [Transporteurs](carriers.md) pour Postes Canada, dont l'adaptateur a aussi besoin du service `http_client` de
FrameworkBundle, [Conventions](conventions.md) pour le stock des articles de convention, et [Coûts](costing.md) pour la valeur du stock
au coût. Les préfixes doivent différer
de tous les autres types du registre du noyau — `R-` et `CN-` pour ses reçus et ses notes de crédit —, sinon le
registre les refuse tous les deux.

Les services lisent des dépôts : avec DoctrineBundle, le module les enregistre avec son adaptateur Doctrine — générez
une migration pour ses dix-huit tables — ; sans lui, l'application les déclare avec ses propres adaptateurs. Les ventes
différées du noyau et son registre des types de documents ont aussi besoin des adaptateurs Doctrine du noyau lui-même.
