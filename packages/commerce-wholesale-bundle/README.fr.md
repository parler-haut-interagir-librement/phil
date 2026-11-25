<!-- phil-dev source-sha256=61cc069e1d1c0c4cce3906718110b04ca13d8be4649de07b9b07005a7b866bf5 -->
# phil/commerce-wholesale-bundle

**Des groupes de clients avec leurs propres prix, leur commande minimale et leurs règles de quantité, et des ventes à
crédit avec modalités nettes.**

phil/commerce-wholesale-bundle vend à d'autres entreprises. Un groupe de clients a ses propres conditions : une remise
proportionnelle sur les prix de base des produits et une commande minimale. Chaque acheteur appartient à un groupe au
plus. Le module établit le prix des produits d'une commande pour ce groupe et indique ce qu'il manque encore à la
commande pour atteindre le minimum du groupe. Un *SKU* peut ne se vendre qu'à partir d'un minimum, jusqu'à un maximum et
par tranches d'un incrément, pour tous les acheteurs ou pour un groupe : une contrainte du Validator de Symfony, avec
ses messages en anglais et en français, vérifiée de nouveau chaque fois que le panier change. Un acheteur peut aussi
acheter à crédit : un compte avec une limite et des modalités comme net 30, chaque achat inscrit comme une créance
exigible après le délai, les paiements de l'acheteur qui y sont imputés, et la liste de ce qui est en souffrance. Le
module n'envoie ni relevés ni rappels, et ne facture ni intérêts ni frais de retard.

Il s'adresse à une application qui vend en gros à côté de ses prix de détail. L'acheteur est nommé comme l'application
le nomme : un compte, ou le locataire d'une application multilocataire qui achète pour ses propres clients. La liste de
prix propre à un groupe est un carnet de prix de `phil/commerce-catalog-bundle` qui nomme le groupe ; la remise du
groupe ne s'applique que là où aucun carnet de ce genre ne fixe le prix du produit. Une application qui ne vend qu'au
détail n'a besoin que de `phil/commerce-bundle`, et peut-être de ses abonnements.

C'est un bundle Symfony et un module de `phil/commerce-bundle` : il refuse de se charger sans le noyau, et enregistre
ses services avec DoctrineBundle, qui stocke les groupes, les appartenances, les règles de quantité, les comptes et les
créances. Il se branche sur deux ports du noyau. `GroupPrices` est un résolveur de prix unitaire interrogé après les
carnets de prix du catalogue, et `QuantityGuard` un résolveur interrogé avant tous les prix, qui refuse une quantité que
sa règle ne permet pas. Un achat à crédit est un paiement du noyau avec la méthode `on_account`, pris en charge par
`AccountGateway` : les remboursements passent donc par les paiements du noyau comme les autres.

## État

| Partie | État |
|---|---|
| Groupes de clients, appartenances | Implémenté et testé |
| Prix de groupe, commandes minimales | Implémenté et testé |
| Règles de quantité par *SKU* et par groupe : minimum, maximum, incrément | Implémenté et testé |
| Listes de prix par groupe | Par les carnets de prix du catalogue |
| Ventes à crédit : comptes, limites, modalités, créances, souffrances | Implémenté et testé |
| Un acheteur à crédit qui reçoit une commande avant de la payer, avec `phil/commerce-logistics-bundle` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'enregistrement du module à côté du noyau. |
| [Groupes de clients](doc/fr/groups.md) | Les groupes, les appartenances, le prix que prend un produit, le stockage. |
| [Règles de quantité](doc/fr/quantities.md) | Le minimum, le maximum et l'incrément par *SKU* et par groupe, la contrainte, le panier, le stockage. |
| [Ventes à crédit](doc/fr/credit.md) | Les comptes, les achats à crédit, les paiements de l'acheteur, les remboursements, le stockage. |
| [Portée](doc/fr/scope.md) | Ce que contient le module, ce qu'il ne contient pas encore, et le port du noyau qu'il implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le module est testé, et comment exécuter ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-wholesale-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceWholesaleBundle\PhilCommerceWholesaleBundle::class => ['all' => true],
];
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/commerce-bundle`, `phil/ddd-bundle` et `phil/money` 0.1 ; Doctrine
ORM 3.7 ; `symfony/config`, `symfony/dependency-injection`, `symfony/uid` et `symfony/validator` 8.2 ou plus
récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
