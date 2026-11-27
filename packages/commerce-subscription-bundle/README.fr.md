<!-- phil-dev source-sha256=3f312b3678c877ca5b37a8dcc15a3aa70efbf1ca00bf1159731ece8b86b0e443 -->
# phil/commerce-subscription-bundle

**Des offres récurrentes en paliers, avec essais, renouvellement, relances, calcul au prorata, cadeaux et les
fonctionnalités que débloque chaque palier.**

phil/commerce-subscription-bundle est le module d'abonnement de `phil/commerce-bundle`. Il garde des offres classées
comme les paliers d'une famille : des paliers gratuits et payants ou des paliers de soutien, dans un seul modèle. Il
démarre des abonnements avec ou sans essai, commande chaque période de facturation par le noyau, renouvelle, retente
les paiements échoués et annule à la fin de la période payée. Il change de palier au renouvellement suivant, ou
sur-le-champ avec la partie inutilisée de la période créditée. Un acheteur offre un abonnement à quelqu'un d'autre
avec un code. Il dit quelles fonctionnalités un abonné peut utiliser, et ouvre l'abonnement d'une organisation à ses
membres, avec des places facultatives. Un abonnement qui expédie quelque chose garde une adresse de livraison, figée
dans chacune de ses commandes et effacée quelques jours après le dernier envoi. Avec DoctrineBundle, les offres, les
abonnements, les cadeaux, les places et les adresses de livraison sont enregistrés.

Tant qu'un renouvellement est en souffrance, les étapes de relance de chaque offre disent ce que l'abonné garde après un
certain nombre de jours : toutes les fonctionnalités par défaut, quelques-unes, ou aucune.

Il s'adresse à une application qui vend un accès à la période, une adhésion ou un forfait. La plupart des applications
qui vendent un accès n'ont besoin que du noyau et de ce module ; un catalogue de produits relève de
`phil/commerce-catalog-bundle`.

C'est un bundle Symfony qui refuse de se charger sans le noyau. Chaque période est commandée au prix actuel de l'offre,
jamais à un prix venu d'un formulaire ; chaque montant est un `Money` dans la devise de la commande, et le noyau taxe
chaque ligne.

Les offres répondent au `SellableResolver` du noyau sous le type de référence `offer` : chaque période est donc une
commande ordinaire du noyau ; un changement sur-le-champ est calculé au prorata par un `OrderProcessor` avant que le
noyau le taxe. L'application lit `FeatureAccess` pour ouvrir ou fermer une fonctionnalité à un abonné.

## État

| Partie | État |
|---|---|
| Les offres et les paliers, les périodes comptées à partir d'un point d'ancrage | Implémenté et testé |
| Les abonnements : essais, renouvellement par une commande du noyau, relances, annulation, changements de palier | Implémenté et testé |
| Les changements immédiats avec calcul au prorata | Implémenté et testé |
| Les cadeaux échangés par un code | Implémenté et testé |
| Les organisations, avec des places facultatives | Implémenté et testé |
| L'accès aux fonctionnalités par palier | Implémenté et testé |
| Les étapes de relance par offre : toutes les fonctionnalités, quelques-unes ou aucune après un certain nombre de jours de retard | Implémenté et testé |
| Les adresses de livraison : sur l'abonnement, figées dans chaque commande, effacées après le dernier envoi, dans les demandes d'accès et d'effacement | Implémenté et testé |
| Le stockage sur Doctrine | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'enregistrement du module à côté du noyau. |
| [Configuration](doc/fr/configuration.md) | Les clés, et les services que le module enregistre. |
| [Offres](doc/fr/offers.md) | Les offres et les paliers, les périodes, la vente par le noyau, le stockage. |
| [Abonnements](doc/fr/subscriptions.md) | Les états, la boucle que tient l'application, les périodes, l'annulation, le changement de palier, le stockage. |
| [Changer de palier sur-le-champ](doc/fr/proration.md) | Ce qui est facturé et crédité, l'arrondi, quels changements sont calculés au prorata. |
| [Cadeaux](doc/fr/gifts.md) | L'achat, le code, le bénéficiaire, ce qui se passe après les périodes payées. |
| [Organisations](doc/fr/organizations.md) | L'appartenance par un port, ce qu'un membre peut utiliser, les places. |
| [Fonctionnalités](doc/fr/features.md) | Ce qu'un abonné ou un membre peut utiliser. |
| [Relance](doc/fr/dunning.md) | Ce qu'un abonné garde tant qu'un renouvellement est en souffrance, étape par étape. |
| [Adresses de livraison](doc/fr/delivery-addresses.md) | Où vont les envois d'un abonnement, la copie figée dans chaque commande, le numéro de téléphone, l'effacement. |
| [Portée](doc/fr/scope.md) | Ce que le module contient, ce qu'il laisse à d'autres paquets et la couture avec eux, et les ports du noyau qu'il implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le module est testé, et comment lancer ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-subscription-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceSubscriptionBundle\PhilCommerceSubscriptionBundle::class => ['all' => true],
];
```

## Prérequis

PHP 8.5 ou plus récent ; `phil/commerce-bundle` 0.4 ; `phil/address`, `phil/ddd-bundle` et `phil/money` 0.1 ; Doctrine
ORM 3.7 et DBAL 4.5 ; `psr/clock` 1.0 ; `symfony/uid`, `symfony/config` et `symfony/dependency-injection` 8.2 ou plus
récent. Facultatifs : `symfony/console`, `symfony/scheduler` et `symfony/lock` pour la purge des adresses de livraison,
et `phil/privacy-bundle` 0.3 pour leurs demandes d'accès et d'effacement.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
