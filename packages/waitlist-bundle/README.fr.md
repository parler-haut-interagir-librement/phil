<!-- phil-dev source-sha256=bdbd486f22060118514895a463d57dcc14a2efbf4c8563df1f1b319bde85f74e -->
# phil/waitlist-bundle

**Les séances, ateliers et événements complets perdent les places libérées par les annulations : une liste d'attente
les offre tour à tour, avec une échéance, et passe aux suivants les offres expirées.**

phil/waitlist-bundle donne une liste d'attente à tout ce qui a une capacité : une séance, un atelier, un événement, un
produit en rupture de stock. L'application nomme ce à quoi sert la liste — un genre et un identifiant, comme `session`
et `S-42` — et continue de compter sa propre capacité. Les participants s'inscrivent sur la liste et voient leur
position. Quand des places se libèrent, l'application les remet à la liste, qui les offre aux entrées suivantes ; chaque
offre est conservée jusqu'à une échéance. Une entrée l'accepte — l'application donne alors les places —, la refuse ou la
laisse expirer, et les places vont aussitôt aux entrées suivantes. Les places qui ne conviennent encore à personne sont
gardées jusqu'à ce que quelqu'un leur convienne.

Chaque requête s'exécute sous le verrou de sa liste, de sorte que deux requêtes n'offrent jamais la même place ;
s'inscrire deux fois redonne la même entrée, son rang inchangé. Par défaut, les places sont offertes aux entrées
strictement dans l'ordre, ou en passant par-dessus un groupe qui en demande davantage, selon une politique que
l'application peut remplacer. Une liste peut aussi être un tirage : les entrées faites avant le tirage sont ordonnées
par un germe publié, de sorte que chacun peut vérifier l'ordre, et celles qui s'ajoutent ensuite attendent derrière
elles. Chaque changement est un événement qui ne porte que des identifiants ; avec phil/notifications-bundle, le
participant est informé qu'une place lui est offerte, ou que son offre a expiré.

Il stocke des identifiants de participants, jamais leurs noms ni leurs adresses.

## État

| Partie | État |
|---|---|
| `Waitlist` et `Entry` dans Doctrine, mappées par attributs, instants en UTC | Implémenté et testé sur SQLite |
| `Waitlists` : ouvrir, s'inscrire, position, remettre, offrir avec une échéance, accepter, refuser, quitter, faire expirer, reprendre, fermer | Implémenté et testé |
| Le tirage avec un germe publié | Implémenté et testé |
| `FirstInLine`, `FirstThatFits` et le port `OfferPolicy` | Implémenté et testé |
| La commande `phil:waitlist:lapse` | Implémenté et testé |
| Les notifications par phil/notifications-bundle, une intégration facultative | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, le schéma, la commande planifiée. |
| [Configuration](doc/fr/configuration.md) | Les options de `phil_waitlist`. |
| [Listes d'attente](doc/fr/waitlists.md) | Ouvrir une liste, s'inscrire, la position, remettre des places, les offres et leur échéance, le tirage, le verrou, les événements. |
| [Notifications](doc/fr/notifications.md) | Informer les participants de leurs offres par phil/notifications-bundle. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, événements et options qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/waitlist-bundle
```

```php
use Phil\WaitlistBundle\Application\Exception\WaitlistRefused;
use Phil\WaitlistBundle\Application\Waitlists;

$waitlist = $waitlists->open('session', $session->id);

try {
    $entry = $waitlists->join($waitlist->id, $accountId, places: 2);
    $waitlists->standing($entry->id)->position; // 1 for the next in line
} catch (WaitlistRefused $refused) {
    $refused->reason; // RefusalReason::Closed, or TooManyPlaces
}

// A registration was cancelled in the application: two places are free.
$waitlists->release($waitlist->id, 2);  // offered to the next entries, each until its deadline

$waitlists->accept($entry->id);         // OfferAccepted: the application gives the places
```

```sh
# Every minute: the offers whose deadline has come lapse, their places go to the next entries.
php bin/console phil:waitlist:lapse
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Doctrine ORM 3.8 ou une version plus
récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
