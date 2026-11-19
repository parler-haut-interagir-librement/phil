<!-- phil-dev source-sha256=9cda5bd10fb01229ec86886f8e5776b9516f2ca1f2e560e39312ed8e6dcad54d -->
# phil/commerce-stored-value-bundle

**Des cartes-cadeaux numériques et du crédit en magasin, vendus, rechargés et dépensés comme paiements d'une
commande.**

phil/commerce-stored-value-bundle garde deux sortes de solde. Une carte-cadeau se vend comme un vendable du noyau, par
exemple `gift_card:CAD:5000`, et est émise une fois la commande passée, une carte par unité, avec un code de seize
caractères. Une carte peut aussi être donnée, rechargée ou annulée. Une carte donnée peut expirer ; une carte payée
jamais, à moins d'être marquée pour un service précis ; chaque boutique vérifie la loi applicable là où elle vend avant
de fixer une expiration. Un crédit en magasin est un solde par client et par
devise, accordé lors d'un retour ou en geste commercial. L'un ou l'autre paie une partie ou la totalité d'une commande,
à côté d'une carte ou d'argent comptant pour un paiement partagé, et un remboursement lui rend la valeur. Chaque
changement d'un solde est un mouvement, conservé pour de bon.

Il s'adresse à une boutique qui vend des cartes-cadeaux ou donne du crédit en magasin.
`phil/commerce-returns-bundle` s'en sert pour régler un retour en crédit en magasin, et `phil/commerce-pos-bundle`
compte dans sa session une carte-cadeau reçue à la caisse. Le module n'envoie pas le code à l'acheteur, n'imprime pas
de cartes physiques, ne produit pas de rapport sur les soldes non réclamés et ne limite pas la fréquence à laquelle un
code peut être essayé. Tout cela reste à l'application, par exemple avec `symfony/rate-limiter` pour les essais.

C'est un bundle Symfony et un module de `phil/commerce-bundle`, et il refuse de se charger sans le noyau. Avec
DoctrineBundle, il enregistre les cartes, les crédits et leurs mouvements. Il se branche sur deux ports du noyau :
`GiftCardSellables` résout le type de vendable `gift_card`, et `StoredValueGateway` prend en charge les méthodes de
paiement `gift_card` et `store_credit`. Un code s'écrit en Base32 de Crockford, se lit avec indulgence à partir de ce
qu'une personne tape, et n'est enregistré que sous forme d'empreinte et de ses quatre derniers caractères.

## État

| Partie | État |
|---|---|
| Les cartes-cadeaux : vendues, émises, données, rechargées, annulées ; données ou payées, expiration des cartes données et des services précis | Implémenté et testé |
| Une carte imprimée en PDF de la taille d'une carte de paiement | Implémenté et testé |
| Le crédit en magasin : accordé, solde | Implémenté et testé |
| Le paiement avec l'un ou l'autre, les paiements partagés, les remboursements vers l'instrument | Implémenté et testé |
| Les mouvements, l'historique de chaque solde | Implémenté et testé |
| Les cartes physiques, l'envoi du code, la limite de débit | Relèvent de l'application |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, l'enregistrement, la configuration. |
| [Valeur stockée](doc/fr/stored-value.md) | Les cartes-cadeaux, le crédit en magasin, le paiement avec eux, les remboursements, les mouvements. |
| [Portée](doc/fr/scope.md) | Ce que le module contient, ce qu'il ne fait pas encore, et les ports du noyau qu'il implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le module est testé, et comment lancer ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-stored-value-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceStoredValueBundle\PhilCommerceStoredValueBundle::class => ['all' => true],
];
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/commerce-bundle`, `phil/ddd-bundle`, `phil/money` et
`phil/paged-media` 0.1 ; Doctrine ORM 3.7 ; `psr/clock` 1.0 ; `symfony/config`, `symfony/dependency-injection` et
`symfony/uid` 8.2 ou plus récent.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
