<!-- phil-dev source-sha256=2b668dba16e7847ea20fc8b669746234634917b588bcf625551263c6912829f7 -->
# phil/commerce-catalog-bundle

**Les produits, les variantes, les produits composés et les grilles de prix d'une boutique construite sur phil/commerce-bundle.**

phil/commerce-catalog-bundle est le module de catalogue de `phil/commerce-bundle`. Il tient les produits, physiques,
numériques ou services, avec leurs attributs, et les canaux qui vendent chacun d'eux. Une famille nomme les axes selon
lesquels ses variantes diffèrent, et chaque variante est un produit à part entière ; un produit composé est fait
d'autres produits et vendu comme une seule ligne, taxée en bloc. Les noms des produits sont traduits, et les produits
sont classés dans les catégories et les étiquettes de `phil/taxonomy-bundle`. Il donne un prix aux produits par des
grilles de prix par groupe de clients, canal, période et palier de quantité, chaque grille étant essayée après celles
que nomme sa liste `after`. Un acheteur peut choisir le prix, à partir d'un minimum, et les commandes payées financent
des copies communautaires pour les gens qui ne peuvent pas payer. Les produits physiques peuvent porter des numéros de
série, tenus avec le stock du module de logistique. Chaque produit est listé, joint par son lien direct seulement, ou
caché, dans chaque canal qui le vend, commandable ou non — annoncé, épuisé pour de bon —, son prix montré ou non ; les
pages et le panier lisent la même réponse. Les longues listes de produits se lisent page par page sur DBAL, et avec
`phil/product-feeds` le catalogue est publié comme fichiers de flux pour les comparateurs et les places de marché. Avec
DoctrineBundle, tout cela est stocké. Les promotions relèvent
de `phil/commerce-promotions-bundle` ; les avis et les produits qu'un acheteur assemble à partir de choix ne sont pas
tenus ici.

Il sert à une application qui vend des biens ou des services à partir d'un catalogue. Le noyau tient les commandes,
leurs ajustements et leurs taxes ; une application qui vend des accès a besoin du noyau et peut-être de
`phil/commerce-subscription-bundle`, mais jamais de ce module.

C'est un bundle Symfony qui refuse de se charger sans le noyau. L'installer, c'est l'activer : il n'y a aucune liste
de modules à tenir.

Il se branche aux ports et aux événements du noyau, et à rien d'autre. Les produits répondent à `SellableResolver` sous
le type de référence `product`, les règles de prix sous `pay_what_you_want` — avec l'option de montant du noyau, `SellableOptions` — et
`community_copy`, les grilles de prix répondent à `UnitPriceResolver`, les sélections et les offres répondent à `SellableAvailability`, et les copies communautaires
écoutent `OrderPurchased`.

## État

| Partie | État |
|---|---|
| Les produits et les attributs | Implémenté et testé |
| Les familles et les variantes, ce qu'une variante prend de sa famille | Implémenté et testé |
| Les produits composés | Implémenté et testé |
| Les noms traduits | Implémenté et testé |
| Les catégories et les étiquettes, par `phil/taxonomy-bundle` | Implémenté et testé |
| Les grilles de prix classées par listes `after`, et la migration depuis les grilles classées par numéro | Implémenté et testé |
| Les règles de prix : payez ce que vous voulez, copies communautaires | Implémenté et testé |
| Les numéros de série | Implémenté et testé |
| Le stockage sur Doctrine | Implémenté et testé |
| Les offres — listé, par lien direct seulement ou caché ; commandable ou non ; avec prix ou non — dans le catalogue, les pages et le panier | Implémenté et testé |
| Les listes des produits qu'un canal montre, page par page sur DBAL | Implémenté et testé |
| Les flux de produits par `phil/product-feeds` : le catalogue comme fichiers RSS ou TSV, par une commande ou un message planifié, servis par une route avec des en-têtes de cache | Implémenté et testé |
| Les avis | Pas commencé : ils viennent de `phil/reviews-bundle`, qui ne tient que son squelette |
| Les produits qu'un acheteur assemble à partir de choix | Pas commencé : prévu après cette version |
| Une fourchette de prix en mots | Pas commencé : il attend un filtre de fourchette de `phil/money-bundle` |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'enregistrement du module à côté du noyau. |
| [Configuration](doc/fr/configuration.md) | Les locales dans lesquelles les noms des produits sont traduits, et les flux de produits. |
| [Produits](doc/fr/products.md) | Les produits, leurs natures, les attributs, les offres, les listes de produits, la vente par le noyau, le stockage. |
| [Flux de produits](doc/fr/product-feeds.md) | Le catalogue publié comme fichiers de flux, les champs lus, la commande, le message, le calendrier et la route qui les sert. |
| [Variantes et produits composés](doc/fr/variants-and-composites.md) | Les familles et leurs variantes, les produits composés et leurs parties. |
| [Noms et classement](doc/fr/names-and-classification.md) | Les noms traduits, les catégories et les étiquettes. |
| [Grilles de prix](doc/fr/price-books.md) | Les conditions, les paliers, l'ordre des grilles, le prix que prend une ligne, le stockage, la migration depuis les grilles classées par numéro. |
| [Règles de prix](doc/fr/price-rules.md) | Payez ce que vous voulez, les copies communautaires, leur stockage. |
| [Portée](doc/fr/scope.md) | Ce que le module tient, ce qu'il ne fait pas encore, et les ports du noyau qu'il implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le module est testé, et comment lancer ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
];
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/commerce-bundle`, `phil/ddd-bundle`, `phil/doctrine-behaviours`,
`phil/locale-dimensions` et `phil/money` 0.1 ; Doctrine ORM 3.7 et DBAL 4.5 ; `symfony/config` et
`symfony/dependency-injection` 8.2 ou plus récent ; `symfony/translation-contracts` 3.6 ou plus récent ; `psr/clock`, `psr/log` et
`symfony/polyfill-php86`. `phil/taxonomy-bundle`, `phil/product-feeds`, `symfony/messenger` et
`symfony/http-foundation` sont suggérés.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
