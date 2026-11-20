<!-- phil-dev source-sha256=0668cbd64227b6e40dde2449c1d2d7b957a9ae203de7b8e8408cefe048d371bf -->
# phil/commerce-promotions-bundle

**Les promotions d'une boutique construite sur phil/commerce-bundle et son catalogue : un pourcentage de rabais, un
montant de rabais, N pour X, des prix de lots et la livraison gratuite.**

phil/commerce-promotions-bundle est le module de promotions de `phil/commerce-bundle`, sur les produits de
`phil/commerce-catalog-bundle`. Une promotion donne un pourcentage de rabais sur chaque ligne admissible, un montant de
rabais réparti sur elles, N unités pour le prix de X, un prix pour chaque lot complet de groupes de produits, ou les
frais de livraison annulés. Elle s'applique par canal, groupe de clients, période, produits et produits qu'elle exclut,
code, utilisations au total et par client, et produits que le client possède déjà. Les promotions s'appliquent dans
l'ordre que fixent leurs listes `after`, et ne se combinent jamais avec celles avec lesquelles elles sont
incompatibles. Les libellés sont traduits ; avec DoctrineBundle, tout cela est stocké.

Il sert à une boutique qui vend à partir d'un catalogue et tient des soldes, des lots et des coupons. Les codes utilisés
sans le catalogue — dons, billets, adhésions — relèvent d'un autre paquet.

C'est un bundle Symfony qui refuse de se charger sans le noyau et le catalogue. L'installer, c'est l'activer.

Il se branche aux ports du noyau, et à rien d'autre : les promotions s'exécutent comme un `OrderProcessor` avant les
taxes du noyau, et la livraison gratuite comme un second, après le processeur qui calcule les frais de livraison.

## État

| Partie | État |
|---|---|
| Pourcentage de rabais, montant de rabais, N pour X | Implémenté et testé |
| Prix de lots, une ligne par produit | Implémenté et testé |
| Livraison gratuite, par mode de livraison | Implémenté et testé |
| Exclusions, incompatibilités, ordre par `after` | Implémenté et testé |
| Utilisations par client, produits possédés | Implémenté et testé |
| Codes par lots, utilisations comptées une seule fois par commande | Implémenté et testé |
| Libellés traduits | Implémenté et testé |
| Le stockage sur Doctrine | Implémenté et testé |
| Achetez X, obtenez Y entre deux listes, campagnes avec un budget, conditions d'un paquet de règles | Pas commencé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'enregistrement du module à côté du noyau et du catalogue. |
| [Configuration](doc/fr/configuration.md) | Les locales dans lesquelles les libellés sont traduits. |
| [Promotions](doc/fr/promotions.md) | Ce que donne une promotion, quand elle s'applique, comment les promotions s'additionnent, les codes et les utilisations, le stockage. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le module est testé, et comment lancer ses tests et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle phil/commerce-promotions-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
    Phil\CommercePromotionsBundle\PhilCommercePromotionsBundle::class => ['all' => true],
];
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/commerce-bundle`, `phil/commerce-catalog-bundle`,
`phil/ddd-bundle`, `phil/doctrine-behaviours`, `phil/locale-dimensions` et `phil/money` 0.1 ; Doctrine ORM 3.7 ;
`psr/clock` 1.0, dont l'application fournit l'horloge ; `symfony/config` et `symfony/dependency-injection` 8.2 ou plus
récent ; `symfony/translation-contracts` 3.6 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
