<!-- phil-dev source-sha256=7d7193ec8f6fa8bb975b8834ace02087303ac7ed6f7d95fdaef40074b40cd95e -->
# phil/incident-mode

**Un interrupteur pour les mauvais jours : suspendre les inscriptions, les invitations, les nouveaux messages ou tout
sauf la lecture, pour une durée toujours fixée, levée à la main ou d'elle-même à la fin.**

phil/incident-mode permet à une équipe de réagir à une vague de pourriel, à un raid ou à un incident de sécurité sans
déploiement. Un incident se déclenche à partir d'un préréglage nommé — suspendre les inscriptions, suspendre les
invitations, suspendre les nouveaux messages, suspendre toute nouvelle contribution, lecture seule — pour une durée que
le préréglage borne : il n'y a pas d'incident sans fin. Tant qu'il est en vigueur, l'application demande
`isSuspended('signups')` avant de laisser quelqu'un s'inscrire, et affiche un avis ; à sa fin, l'incident cesse de
s'appliquer de lui-même, et la levée automatique l'inscrit au journal. Quelqu'un peut le lever avant, ou le prolonger
dans les limites de son préréglage. Chaque début, chaque prolongation et chaque levée envoie un événement, et le
journal garde qui a déclenché et qui a levé chaque incident, par un identifiant stable, jamais une adresse de
courriel.

Il s'adresse à toute application PHP qui a des membres : une communauté, un forum, un site avec des inscriptions ou des
invitations, un service de messagerie. L'application ajoute ses propres préréglages et ses propres restrictions —
`new-listings`, `ticket-sales` — à côté de ceux qui sont fournis ; `read-only` les couvre tous.

C'est une bibliothèque PHP simple dont les prérequis sont `psr/clock` et `psr/event-dispatcher`. Les activations
vivent dans un magasin : en mémoire pour les tests, ou dans une table d'une connexion Doctrine DBAL sur PostgreSQL,
MySQL, MariaDB ou SQLite, ses moments en UTC, où la levée et la prolongation sont des mises à jour conditionnelles, de
sorte que deux workers qui lèvent le même incident n'envoient qu'un événement. La levée à la fin est un message différé
de Symfony Messenger, avec un balayage récurrent pour les messages qu'un transport a perdus. Rien n'est conservé entre
deux appels : un worker de longue durée voit un incident se terminer à sa fin.

`phil/moderation-bundle` offre le mode d'incident dans son interface d'administration quand cette bibliothèque est
installée : il câble les services, la table et la levée, et propose un incident quand les signalements montent en
flèche.

## État

| Partie | État |
|---|---|
| Les restrictions, celles qui sont fournies et celles de l'application, `read-only` les couvrant toutes | Implémenté et testé |
| Les préréglages avec une durée proposée et une durée maximale ; cinq préréglages fournis ; ceux de l'application ajoutés, remplacés ou écartés | Implémenté et testé |
| Le déclenchement avec une durée obligatoire, la prolongation dans les limites du préréglage, la levée à la main | Implémenté et testé |
| La fin qui s'applique d'elle-même ; la levée automatique inscrite une seule fois, par un message différé ou un balayage | Implémenté et testé |
| `isSuspended()`, `suspended()`, `inForceNow()`, le journal | Implémenté et testé |
| Les événements `IncidentStarted`, `IncidentExtended` et `IncidentLifted` | Implémenté et testé |
| Le magasin en mémoire ; le magasin DBAL avec mises à jour conditionnelles, moments en UTC, schéma et écouteur de l'ORM | Implémenté et testé |
| Les acteurs retrouvés et oubliés, pour l'export et l'effacement des renseignements personnels | Implémenté et testé |
| Une page, une bannière et une interface d'administration | Implémenté dans `phil/moderation-bundle` |
| Un incident planifié pour commencer plus tard | Pas commencé : un incident commence quand on le déclenche |
| Des restrictions qui épargnent les membres établis | Pas commencé : l'application vérifie le statut du membre avant de demander |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la déclaration des services dans Symfony. |
| [Incidents](doc/fr/incidents.md) | Les restrictions, les préréglages, le déclenchement, la vérification, la prolongation, la levée, le journal et les événements. |
| [Levée automatique](doc/fr/automatic-lifting.md) | La fin d'un incident, le message différé et le balayage récurrent. |
| [Stockage](doc/fr/storage.md) | Les magasins, la table, la concurrence, l'écouteur de schéma, les renseignements personnels. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, préréglages, restrictions et événements qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, son banc d'essai, et la façon de tester du code qui vérifie une restriction. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/incident-mode
```

```php
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\Presets;
use Symfony\Component\Clock\NativeClock;

$incidents = new IncidentMode(Presets::defaults(), new InMemoryActivationStore(), new NativeClock());

$incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(2), Actor::of('8d2e…'), 'Spam wave');

if ($incidents->isSuspended('signups')) {
    // Show the notice instead of the sign-up form.
}
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-sodium` ; `psr/clock` et `psr/event-dispatcher`. `doctrine/dbal` pour le
magasin en base de données, `doctrine/orm` pour l'écouteur de schéma et `symfony/messenger` pour la levée différée,
quand ils sont utilisés.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
