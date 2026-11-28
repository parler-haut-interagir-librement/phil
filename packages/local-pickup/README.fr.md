<!-- phil-dev source-sha256=105731ace86c05596722c6068f3ebf4ab55936264665d44c1d648344c56d6f3d -->
# phil/local-pickup

**Permettez aux clients de ramasser leur commande en magasin ou à un kiosque de congrès : plages avec capacité, avis de
disponibilité, codes de comptoir à usage unique avec code QR, délais de garde et expiration.**

phil/local-pickup est une bibliothèque PHP. Elle suit une commande depuis le moment où le client choisit où et quand la
ramasser jusqu'au moment où le personnel la remet, ou jusqu'à la fin du délai de garde.

**Les points de ramassage.** Un `PickupPoint` est le comptoir d'un magasin ou un kiosque de congrès, lié à
l'emplacement de stock d'où viennent les marchandises. Ses plages sont celles d'une ressource de
[phil/reservation](https://packagist.org/packages/phil/reservation) : la capacité est le nombre de commandes qu'une
plage accepte, les heures et les règles disent quand les clients peuvent venir. Une commande est prête au plus tôt après
le temps de préparation du point.

**Le point convenu demeure.** Passer une commande copie le point sur elle : nom, adresse, emplacement de stock, fuseau
horaire et délai de garde. Un point qui change, ferme ou n'est plus autorisé ensuite ne change rien aux commandes déjà
passées : seul le passage de la commande vérifie si un point accepte des commandes.

**Quatre états.** Un ramassage est en préparation, prêt, ramassé ou expiré. Le marquer prêt envoie un message qui porte
le numéro de commande et rien d'autre ; le *worker* relit le ramassage, émet son code et envoie l'avis « prêt pour le
ramassage » par le notificateur de l'application.

**Un code court, à usage unique.** Huit caractères en base 32 de Crockford, tirés au hasard, affichés sous la forme
`7KQ2-M9XD` et sous celle d'un code QR dessiné par [phil/qr-code](https://packagist.org/packages/phil/qr-code). Seule
son empreinte HMAC-SHA-256, calculée avec une clé dérivée du secret pour cet usage seul et liée à la commande, est
conservée. Le comptoir la vérifie en temps constant, compte les mauvaises tentatives et verrouille le code à la limite,
jusqu'à l'envoi d'un nouveau. Un bon code est consommé aussitôt.

**Quelqu'un d'autre peut ramasser.** Le client peut désigner une personne ; le personnel la vérifie, et le journal des
remises conserve qui a ramassé, avec le nom désigné à côté. `PickupPersonalData` exporte et efface ces noms pour une
demande d'accès ou d'effacement.

**Arrivée sans permissions.** L'application peut annoncer une arrivée d'elle-même — une position partagée — et le client
peut appuyer sur « Je suis là » sur la commande. Le premier signal compte, et le personnel est avisé une seule fois,
quelle que soit la permission que le client retire ensuite.

**Expiration par configuration.** `Expiry` termine les ramassages que personne n'a pris dans leur délai de garde, puis
annule et rembourse la commande ou remet ses marchandises en stock, par un port que l'application implémente.

**Un port pour les comptoirs et les caisses.** `Counter` implémente `PickupDesk`, le port qu'appellent à la fois un
écran de comptoir et une caisse de point de vente, de sorte que la caisse d'un kiosque marque un ramassage comme le fait
le comptoir du magasin.

La bibliothèque lit et écrit seulement par des ports — les ramassages, le journal, le bus, les notificateurs,
l'annulation et la remise en stock — et ne garde aucun état entre les appels : ses services sont sûrs dans un *worker*
de longue durée.

## État

| Partie | État |
|---|---|
| Les points de ramassage, la copie convenue gardée sur la commande, les plages avec capacité et temps de préparation | Implémenté et testé |
| Les états, le délai de garde, la ligne stockée relue | Implémenté, testé et soumis au fuzzing |
| L'avis de disponibilité envoyé par un *worker*, par un message qui porte le numéro de commande seulement | Implémenté et testé |
| Les codes : aléatoires, empreinte HMAC seulement, charge utile du code QR, limite de tentatives, usage unique | Implémenté, testé et soumis au fuzzing |
| Le comptoir et le port `PickupDesk`, le journal des remises, les tiers | Implémenté et testé |
| Les signaux d'arrivée, automatiques ou manuels, annoncés une seule fois | Implémenté et testé |
| L'expiration : annuler et rembourser, ou remettre en stock | Implémenté et testé |
| L'export et l'effacement des noms des personnes qui ramassent | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Points de ramassage et plages](doc/fr/pickup-points.md) | `PickupPoint`, la copie convenue, les plages, la capacité et le temps de préparation. |
| [La vie d'un ramassage](doc/fr/lifecycle.md) | Le passage de la commande, les états, l'avis de disponibilité, les arrivées, le délai de garde et l'expiration. |
| [Codes et comptoir](doc/fr/counter.md) | Les codes, les codes QR, le comptoir, les tentatives, les tiers et les caisses. |
| [Sécurité et vie privée](doc/fr/security.md) | Le secret, ce qui est stocké, les limites et les renseignements personnels. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, le fuzzing et les bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/local-pickup
```

```php
use Phil\LocalPickup\Counter;
use Phil\LocalPickup\HandOverRequest;
use Phil\LocalPickup\PickupCodes;
use Phil\LocalPickup\PickupPoint;
use Phil\LocalPickup\PickupPointKind;
use Phil\LocalPickup\Pickups;
use Phil\LocalPickup\PickupSlots;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;

$zone = new DateTimeZone('America/Toronto');
$point = new PickupPoint(
    'store-main', 'Main store', PickupPointKind::Store, 'main-backroom', "12 Rue Principale\nMontréal",
    new Bookable(capacity: 4, availability: new WeeklyAvailability(array_fill_keys(range(1, 7), [[600, 1080]]), $zone), timezone: $zone),
    slotMinutes: 30, preparationMinutes: 120, holdHours: 72,
);

$slots = new PickupSlots($pickupRepository);
$pickups = new Pickups($pickupRepository, $slots, $readyNoticeDispatcher);

$offered = $slots->available($point, $week, new DateTimeImmutable());
$pickups->place('WEB-1042', 'customer-7', $point, $offered[0], new DateTimeImmutable());
$pickups->ready('WEB-1042', new DateTimeImmutable());   // the worker sends the notice with the code

$counter = new Counter($pickupRepository, new PickupCodes($secret), $handOverLog);
$counter->handOver(HandOverRequest::scanned($scannedPayload, 'counter-1', 'staff-3', new DateTimeImmutable()));
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
