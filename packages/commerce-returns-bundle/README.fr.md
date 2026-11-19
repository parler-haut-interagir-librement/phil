<!-- phil-dev source-sha256=a726fd3d2aa37919e4325b457b42359b88e72f2312daf0f6b4b0e91b18bb340b -->
# phil/commerce-returns-bundle

**Les demandes de retour, de l'autorisation au remboursement, au crédit en magasin ou à l'échange, évaluées ligne par
ligne.**

phil/commerce-returns-bundle prend en charge la marchandise qui revient. Une demande de retour (une RMA) nomme les
lignes d'une commande passée et une raison pour chacune, dans le délai de retour de la boutique, compté depuis le
passage de la commande ou sa dernière expédition. Elle est autorisée, renvoyée au client pour information, refusée,
annulée, ou expirée quand sa marchandise ne vient jamais, puis reçue avec l'état de chaque ligne. Les unités
revendables retournent sur la tablette, et la valeur est remboursée aux paiements de la commande, donnée en crédit en
magasin, ou utilisée pour payer un échange. Au comptoir, les mêmes étapes se suivent d'un coup.

Chaque unité retournée vaut sa part de ce qu'a coûté sa ligne de commande, promotions et taxes de la ligne comprises.
Un ensemble — une boîte vendue comme un seul produit — revient en entier, jamais en partie. Un produit d'une promotion
spéciale — un prix pour des produits achetés ensemble — revient à sa valeur moins le rabais de la promotion, qui
disparaît. Des frais de remise en stock, si l'application en exige, sont retenus sur les lignes dont la boutique n'est
pas responsable. Les frais de livraison sont rendus une fois par commande, comme le dit la politique de la boutique ;
les pourboires ne le sont jamais, et une unité qui n'est jamais revenue ne vaut rien. Une ligne peut revenir en partie,
le reste demeurant retournable ; un retour refusé, annulé ou expiré libère ses unités.

Un retour reçoit une note de crédit pour exactement ce qu'il rend — partie de ligne, frais, promotion spéciale et
livraison compris —, numérotée dans la série de notes de crédit du noyau ; un bordereau pour le colis, numéroté dans sa
propre série et produit en PDF ; et l'étiquette d'un transporteur par un adaptateur. Chaque étape est annoncée par un
événement, avec des identifiants seulement.

Il s'adresse à une boutique qui expédie ou vend de la marchandise qu'un client peut rapporter. C'est un bundle Symfony
et un module de `phil/commerce-bundle`, et il refuse de se charger sans le noyau ; ses services lisent les données du
module ainsi que les commandes et les paiements du noyau par Doctrine : ils sont donc enregistrés avec DoctrineBundle.
Il ne garde lui-même aucun stock : chaque service de son port `Restocking` est appelé pour remettre en place les
unités revendables. Chaque module à ses côtés se branche de lui-même : `phil/commerce-stored-value-bundle` pour le
crédit en magasin, `phil/commerce-catalog-bundle` pour les ensembles, `phil/commerce-promotions-bundle` pour les
promotions spéciales, `phil/commerce-logistics-bundle` pour les dates d'expédition, et `phil/paged-media` produit les
bordereaux. Un échange est un paiement du noyau de méthode `exchange`, pris en charge par `ReturnsGateway`.

## État

| Partie | État |
|---|---|
| Les demandes, le délai depuis le passage ou la dernière expédition, les quantités restantes, une limite de débit par client | Implémenté et testé |
| L'autorisation, les questions au client, le refus, l'annulation, l'expiration, la réception et l'inspection | Implémenté et testé |
| Les ensembles retournés en entier, les promotions spéciales défaites, la livraison rendue une fois par commande | Implémenté et testé |
| La remise en stock par le port `Restocking` | Implémenté et testé |
| Le remboursement aux paiements, le crédit en magasin, l'échange | Implémenté et testé |
| Les notes de crédit partielles, les bordereaux de retour en PDF, les étiquettes par un adaptateur de transporteur | Implémenté et testé |
| Les événements de chaque étape, de chaque règlement et de chaque note de crédit | Implémenté et testé |
| Un adaptateur d'étiquettes de transporteurs sur `phil/commerce-logistics-bundle` | Pas commencé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, l'enregistrement, la configuration. |
| [Retours](doc/fr/returns.md) | Le parcours d'un retour, sa valeur — ensembles, promotions spéciales, livraison —, la remise en stock, les remboursements, le crédit en magasin, les échanges, les étiquettes et les événements. |
| [Notes de crédit et bordereaux](doc/fr/credit-notes-and-slips.md) | La note de crédit d'un retour, ses numéros et ses montants ; le bordereau de retour et son PDF. |
| [Portée](doc/fr/scope.md) | Ce que le module contient, ce qu'il ne fait pas encore, et les ports qu'il utilise et qu'il offre. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le module est testé, et comment lancer ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-returns-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceReturnsBundle\PhilCommerceReturnsBundle::class => ['all' => true],
];
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/commerce-bundle`, `phil/ddd-bundle` et `phil/money` 0.1 ; Doctrine
ORM 3.7 et DBAL 4.5 ; `symfony/config`, `symfony/dependency-injection` et `symfony/uid` 8.2 ou plus récent ;
`symfony/event-dispatcher-contracts` et `symfony/translation-contracts` 3.6 ou plus récent. Le crédit en magasin exige
`phil/commerce-stored-value-bundle` ; les ensembles, les promotions spéciales et les dates d'expédition exigent les
modules de catalogue, de promotions et de logistique ; les bordereaux en PDF exigent `phil/paged-media`, et une limite
de débit `symfony/rate-limiter`.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
