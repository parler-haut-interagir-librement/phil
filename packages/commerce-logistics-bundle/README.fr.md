<!-- phil-dev source-sha256=6e0b7981f3cd8b53616b1d6f1ff3a29d375e12afbbb9ea473f91e46758bc320f -->
<!-- phil-dev source-sha256=0 -->
# phil/commerce-logistics-bundle

**Le stock par emplacement, sa valeur au coût, les réservations des commandes, les commandes en attente, les achats, les numéros de série,
les colis et leurs documents, la livraison facturée par zone, et les tarifs et étiquettes des transporteurs.**

phil/commerce-logistics-bundle tient le côté physique d'une commande. Il garde le stock par *SKU* (unité de gestion de
stock, ou *UGS*) et par emplacement, avec un journal de chaque mouvement : unités reçues, comptées et transférées,
marchandises reçues contre des bons de commande, numéro de série de chaque unité qui en a un, coût unitaire et
propriétaire que donne une ligne de bon de commande, retours de clients remis sur une tablette, et ajustements par une
différence, avec le motif d'un écart. Avec le calcul des coûts activé, il évalue au coût le stock de la boutique à mesure
qu'il bouge — coût moyen pondéré ou premier entré, premier sorti —, répartit le coût d'approche d'une réception sur ses
lignes à l'unité mineure près, remet les retours de clients au coût auquel ils sont partis, et garde les unités laissées
en consignation hors de la valeur de la boutique. Il réserve les lignes de produits d'une commande passée dans les
emplacements ouverts, par priorité, vend une UGS sans stock quand son
indicateur le permet, les libère, et les expédie en un colis ou en plusieurs, chacun avec son transporteur, son numéro
de suivi et son bordereau de livraison, après une liste de prélèvement, seuls ou dans une vague d'expédition planifiée ;
les unités qui partent sont annoncées par un événement qu'écoute un registre ou un rapport.
Il garde le stock préparé pour les conventions, taille par taille, et envoie à l'impression les chandails d'une
commande passée en ligne quand une convention approche. Il offre des modes de livraison par zone (expédition,
livraison locale, ramassage), chacun avec un prix, un seuil de gratuité et un nombre de jours estimé, et facture le mode
que la commande a choisi. Il demande aux transporteurs leurs tarifs et leurs étiquettes, et note ce que chaque
expédition a vraiment coûté à côté du prix de livraison payé par l'acheteur ; Postes Canada est son premier adaptateur
de transporteur.

Il sert à une boutique qui vend des biens qu'elle tient sur une tablette. Une application qui vend des accès, des
téléchargements ou des services n'a besoin que de `phil/commerce-bundle`, et peut-être de ses abonnements. Un produit
jamais reçu nulle part n'est pas suivi : une boutique peut donc mélanger les deux. Le module ne gère pas les retours aux
fournisseurs, le réapprovisionnement ni les prévisions, il ne fixe jamais le prix de livraison d'après le tarif d'un
transporteur, et il ne taxe pas les frais de livraison.

C'est un bundle Symfony et un module de `phil/commerce-bundle` : il refuse de se charger sans le noyau, et enregistre
ses services avec DoctrineBundle, qui stocke le tout. Il se branche au noyau par ses ports : `ShippingProcessor` est
un processeur de commande placé après les promotions et avant les taxes, qui ajoute les frais de livraison à la
commande ; `DeferredStock` retient et libère le stock des ventes du noyau payées plus tard ; le bordereau de livraison
et la liste de prélèvement sont numérotés par le registre des types de documents du noyau. Chaque niveau de stock porte
une colonne de version, qui refuse deux mouvements faits sur la même copie.

## État

| Partie | État |
|---|---|
| Les emplacements, les niveaux de stock, le journal, la réception, les comptages, les ajustements, les transferts, les retours de clients | Implémenté et testé |
| Les réservations des commandes passées, libérées ou expédiées ; les commandes en attente ; le stock des ventes payées plus tard | Implémenté et testé |
| Le stock des conventions, l'impression à la demande des commandes passées en ligne | Implémenté et testé |
| Les bons de commande, le coût unitaire et le propriétaire de leurs lignes, leurs réceptions ; les numéros de série | Implémenté et testé |
| Le calcul des coûts : coût moyen pondéré ou premier entré, premier sorti, coûts d'approche, écarts par motif, retours à leur coût, unités en consignation exclues, `InventoryValuation` | Implémenté et testé |
| Les expéditions : une commande en un colis ou en plusieurs, le transporteur et le suivi ; `UnitsShipped` | Implémenté et testé |
| Une expédition autorisée une fois les paiements à hauteur de la commande, d'autres autorisations par un port, une expédition forcée par permission, gardée avec qui et pourquoi, `ShipmentForced` | Implémenté et testé |
| Les bordereaux de livraison, les listes de prélèvement, les vagues d'expédition | Implémenté et testé |
| Les modes de livraison par zone, le seuil de gratuité, les frais de livraison | Implémenté et testé |
| Les tarifs et les étiquettes des transporteurs, le coût de chaque expédition, Postes Canada | Implémenté et testé ; les noms de champs de Postes Canada ne sont pas encore comparés à son document OpenAPI |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'enregistrement du module à côté du noyau. |
| [Stock](doc/fr/stock.md) | Les emplacements, les niveaux, le journal, la réception, les comptages, les ajustements, les transferts, les retours de clients, les réservations, les commandes en attente, les ventes payées plus tard, le stockage. |
| [Conventions](doc/fr/conventions.md) | Le stock préparé par convention, ce qui reste à imprimer, les commandes passées en ligne envoyées à l'impression. |
| [Achats](doc/fr/purchasing.md) | Les bons de commande et leurs réceptions ; le numéro de série de chaque unité. |
| [Coûts](doc/fr/costing.md) | La valeur du stock au coût : les méthodes, les coûts d'approche, les écarts, les retours, les propriétaires, la lecture de la valeur, le stockage. |
| [Livraison](doc/fr/delivery.md) | Les modes de livraison, ce que paie une commande, le stockage. |
| [Expéditions](doc/fr/shipments.md) | Expédier une commande en un colis ou en plusieurs une fois autorisée, forcer une expédition, ce qui reste, les bordereaux de livraison, les listes de prélèvement, les vagues, le stockage. |
| [Transporteurs](doc/fr/carriers.md) | Les tarifs et les étiquettes, le coût de chaque expédition contre la livraison facturée, Postes Canada. |
| [Portée](doc/fr/scope.md) | Ce que le module tient, ce qui reste hors de lui, et les ports du noyau qu'il implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le module est testé, et comment lancer ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-logistics-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle::class => ['all' => true],
];
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/commerce-bundle`, `phil/ddd-bundle`, `phil/money` et `phil/url` 0.1 ;
Doctrine ORM 3.7 ; `symfony/uid`, `symfony/clock`, `symfony/config` et `symfony/dependency-injection` 8.2 ou plus
récent ; `symfony/event-dispatcher-contracts`, `symfony/http-client-contracts` et `symfony/service-contracts` 3.6 ;
`symfony/polyfill-php86` 1.37 ; `psr/clock` 1.0. L'adaptateur de Postes Canada a besoin d'un client HTTP,
`symfony/http-client` par FrameworkBundle.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
