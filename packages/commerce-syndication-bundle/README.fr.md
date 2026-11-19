<!-- phil-dev source-sha256=6b57884edf80ac608005360ae8b757a146d7bd7c84e73e4bd7f0293df223670f -->
<!-- phil-dev source-sha256=0 -->
# phil/commerce-syndication-bundle

**Revendez les produits d'une autre boutique comme les vôtres, avec les commandes transmises au fournisseur qui
expédie.**

phil/commerce-syndication-bundle relie des applications de commerce distinctes. Un fournisseur détient le stock et
expédie ; il alimente chaque revendeur d'une liste d'UGS (unités de gestion de stock, *SKU*) avec leur prix, leur coût,
leur stock et leur retrait. Un revendeur vend ces produits comme les siens et transmet les lignes syndiquées de chaque
commande à leur fournisseur, une seule fois. Le fournisseur dit au revendeur chaque colis qu'il expédie, avec son
transporteur et son numéro de suivi ; le revendeur transmet les retours au fournisseur, qui crédite ce qu'il accepte. Un
registre garde ce que chaque partenaire doit à l'autre par commande et par retour, son solde et son règlement. Le module
ne déplace pas d'argent : les partenaires règlent de la façon convenue entre eux. Il n'envoie ni description ni image
au-delà d'un nom, et n'envoie pas le flux complet à un revendeur lors de sa première alimentation.

Il s'adresse à des applications qui commercent entre elles. Une application peut être le fournisseur de certains
partenaires et le revendeur d'autres ; une boutique seule qui ne vend que son propre catalogue n'a besoin que de
`phil/commerce-bundle`. Chaque partenaire fonctionne de son côté, avec sa propre base de données, et ils ne se parlent
que par des [Standard Webhooks](https://www.standardwebhooks.com/) signés avec Ed25519, par `phil/standard-webhooks`.
Chaque partenaire est connu par son code, son point de terminaison https et sa clé publique. Un message est vérifié avec
la clé du partenaire dont il prétend venir, doit être adressé à cette application, et n'est appliqué qu'une fois. Les
messages sortants attendent dans une boîte d'envoi, envoyés par la commande `phil:commerce-syndication:deliver`, et une
tentative échouée attend deux fois plus longtemps que la précédente. Un fournisseur vérifie chaque ligne transmise par
rapport aux coûts qu'il a publiés à ce revendeur, et les deux côtés calculent le crédit d'un retour à partir de leur
propre registre. Les messages des colis et des retours portent des UGS, des quantités et des montants, jamais une
personne ; l'adresse d'une commande transmise quitte son message une fois le travail fait, et
`phil:commerce-syndication:purge` supprime les messages dont la durée de conservation est dépassée.

C'est un bundle Symfony et un module de `phil/commerce-bundle`, et il refuse de se charger sans le noyau. Avec
DoctrineBundle, il enregistre les partenaires, les messages, les produits syndiqués et le registre ; sans lui,
l'application déclare les services avec ses propres adaptateurs. Les produits syndiqués sont des vendables du noyau.
Des ports qui lui sont propres gardent les bords ouverts : `MessageTransport` transporte un message signé, par le
client HTTP de Symfony par défaut ; `ForwardedOrderHandler` est ce que fait le fournisseur d'une commande qu'un
revendeur lui a transmise ; `PartnerShipmentHandler` et `PartnerReturnHandler` sont ce que fait chaque côté quand les
colis et les retours circulent.

## État

| Partie | État |
|---|---|
| Les partenaires, les clés, les rôles | Implémenté et testé |
| Le flux de produits (`product.updated`) | Implémenté et testé |
| Les produits syndiqués vendus comme vendables du noyau | Implémenté et testé |
| La transmission des commandes (`order.forwarded`) | Implémenté et testé |
| Le registre et le règlement | Implémenté et testé |
| La boîte d'envoi, la signature, la livraison avec nouvelles tentatives | Implémenté et testé |
| L'historique des prix chez le fournisseur, la conservation des messages | Implémenté et testé |
| Le suivi des expéditions (`shipment.sent`) | Implémenté et testé |
| Les retours et leur crédit (`return.requested`, `return.received`) | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, l'enregistrement, la configuration, le routage, la planification. |
| [Syndication](doc/fr/syndication.md) | Les partenaires, le flux, la transmission, les colis, les retours, le registre, les messages et leur livraison. |
| [Messages](doc/fr/messages.md) | L'enveloppe, sa signature et les données de chaque type de message. |
| [Portée](doc/fr/scope.md) | Ce que le module contient, ce qu'il ne fait pas encore, et les ports du noyau qu'il implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le module est testé, et comment lancer ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-syndication-bundle symfony/http-client
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceSyndicationBundle\PhilCommerceSyndicationBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_commerce_syndication.yaml
phil_commerce_syndication:
    identity: shop
    signing_key: '%env(SYNDICATION_SIGNING_KEY)%'
```

## Prérequis

PHP 8.5 ou plus récent ; `phil/commerce-bundle`, `phil/standard-webhooks`, `phil/ddd-bundle` et `phil/money` 0.1 ;
Doctrine ORM 3.7 et DBAL 4.5 ; `psr/clock` 1.0 ; `symfony/config`, `symfony/dependency-injection`,
`symfony/http-foundation` et `symfony/uid` 8.2 ou plus récent ; `symfony/polyfill-php86` 1.37. `ext-sodium` vient avec `phil/standard-webhooks` ; `symfony/http-client` et
`symfony/console` sont suggérés.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
