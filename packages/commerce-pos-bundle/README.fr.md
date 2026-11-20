<!-- phil-dev source-sha256=6f5bc99611627d60ffd8e27b07c075fd2a57fb5208fbbe30637e9393a109fcfe -->
# phil/commerce-pos-bundle

**Une caisse pour la vente au comptoir : tiroirs-caisses, quarts, comptages de caisse, terminaux connectés, mises de
côté et ventes enregistrées hors ligne.**

phil/commerce-pos-bundle vend au comptoir. Une caisse est un tiroir-caisse avec sa devise et l'incrément auquel son
argent comptant est arrondi : la caisse principale d'un lieu, la tablette au comptoir, ou la caisse mobile d'un
vendeur sur son téléphone. Une session est un quart de travail : elle s'ouvre sur le fonds de caisse compté dans le
tiroir et suit chaque mouvement d'argent comptant (ventes, remboursements, entrées de fonds, sorties de fonds, dépôts
au coffre) jusqu'à ce que le tiroir devrait contenir. La fermeture compte le tiroir et donne le rapport de fin de
journée : l'excédent ou le manque d'argent comptant, les paiements par méthode, les pourboires et le nombre de ventes ;
le rapport des écarts de caisse liste les comptages d'une période et signale les gros écarts. L'argent comptant est
arrondi à l'incrément de la caisse, cinq cents au Canada, dans le tiroir seulement ; la commande, ses taxes et son
paiement gardent leurs montants exacts.

Une carte sur un terminal connecté est pilotée par le serveur, par un adaptateur par processeur : la caisse n'exécute
aucun script du processeur, et les terminaux qui demandent un NIP sont à la caisse principale. Une carte sur un
terminal qui n'est pas connecté, ou un chèque, est enregistré avec son autorisation. Une vente peut être mise en
attente et terminée à la caisse principale, porter un pourboire, être vendue sous un préréglage léger par les canaux
du noyau, ou devenir une mise de côté avec un acompte, des versements et une échéance. L'afficheur client montre le
panier et rien sur la personne ; les vrais dés d'une loot box sont saisis au comptoir par le formulaire que fournit son
paquet ; un caissier prend le relais sur un appareil partagé par NIP, quand l'application l'active.

Il s'adresse à une boutique, un café ou un kiosque de marché qui reçoit des paiements en personne. Le panier est la
commande du noyau et ses paiements sont ceux du noyau : une vente à la caisse se range donc à côté des commandes Web de
`phil/commerce-bundle` ; un billet est un article vendable du noyau comme un autre. Les écrans de la caisse, le
tiroir-caisse, l'imprimante de reçus et le lecteur de codes appartiennent à l'application, tout comme le choix de qui
peut ouvrir, rembourser ou sortir des fonds, par la sécurité de Symfony.

C'est un bundle Symfony et un module de `phil/commerce-bundle`, et il refuse de se charger sans le noyau. Ses services
lisent les données du module ainsi que les commandes et les paiements du noyau par Doctrine : ils sont donc enregistrés
avec DoctrineBundle. Quand la connexion tombe, la caisse principale continue de vendre : le contrôleur Stimulus du
module garde chaque vente dans l'IndexedDB du navigateur et l'envoie une fois de retour en ligne ;
`OfflineSaleController` l'importe une seule fois, avec les prix recalculés par le serveur, et garde tout écart pour
révision.

## État

| Partie | État |
|---|---|
| Les caisses, principale et mobile, les sessions, le fonds de caisse, les entrées et sorties de fonds, les dépôts, le comptage, les rapports de fin de journée et des écarts de caisse | Implémenté et testé |
| L'argent comptant avec arrondi et monnaie rendue, les méthodes enregistrées, les paiements partagés, les remboursements | Implémenté et testé |
| Les terminaux connectés pilotés par le serveur, le port et l'adaptateur factice | Implémenté et testé |
| Les ventes en attente terminées à la caisse principale, les pourboires, les préréglages de vente, l'afficheur client | Implémenté et testé |
| Les mises de côté : acompte, versements, achèvement, annulation | Implémenté et testé |
| Les tirages saisis au comptoir, les billets, le changement de caissier par NIP | Implémenté et testé |
| Les ventes hors ligne : la file d'attente IndexedDB, l'importation, le recalcul des prix sur le serveur | Implémenté et testé |
| Les périphériques : tiroir-caisse, imprimante de reçus, lecteur de codes | Pas commencé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, l'enregistrement, la configuration, le routage, le contrôleur Stimulus. |
| [La caisse](doc/fr/register.md) | Les caisses, les sessions, le panier, les paiements, l'arrondi, le rapport de fin de journée. |
| [Caisses principale et mobile](doc/fr/devices.md) | Une caisse principale par lieu, la vente d'un vendeur terminée au comptoir, le changement de caissier par NIP. |
| [Terminaux connectés](doc/fr/terminals.md) | Les débits pilotés par le serveur, les notifications, les remboursements, l'écriture d'un adaptateur, le terminal factice. |
| [Au comptoir](doc/fr/counter.md) | Les préréglages de vente, l'afficheur client, les tirages saisis à la main, les billets, le rapport des écarts de caisse. |
| [Mises de côté](doc/fr/layaways.md) | Les acomptes, les versements, l'achèvement et l'annulation. |
| [Ventes hors ligne](doc/fr/offline.md) | La file d'attente dans le navigateur, l'importation, le recalcul des prix et les écarts. |
| [Portée](doc/fr/scope.md) | Ce que le module contient, ce qu'il ne fait pas encore, et les ports du noyau qu'il implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le module est testé, et comment lancer ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-pos-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommercePosBundle\PhilCommercePosBundle::class => ['all' => true],
];
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/commerce-bundle`, `phil/ddd-bundle` et `phil/money` 0.1 ; Doctrine
ORM 3.7 ; `psr/clock` 1.0 ; `symfony/config`, `symfony/dependency-injection`, `symfony/http-foundation` et
`symfony/uid` 8.2 ou plus récent ; `symfony/polyfill-php86` 1.37. Le contrôleur Stimulus exige `@hotwired/stimulus` 3,
que fournit l'application, servi par AssetMapper et StimulusBundle ; le changement de caissier par NIP exige
`symfony/password-hasher` et `symfony/rate-limiter`.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
