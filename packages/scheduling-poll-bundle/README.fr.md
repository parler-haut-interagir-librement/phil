<!-- phil-dev source-sha256=4ef29d7b122eab59378db25149e7ba1d78473db74736e8a9667e5a317120f1d2 -->
# phil/scheduling-poll-bundle

**Trouver une date qui convient à tout le monde : l'organisateur propose des dates, chaque personne répond oui, au
besoin ou non par son propre lien, sans compte, et la meilleure date est choisie puis envoyée à tous dans un fichier
iCalendar.**

phil/scheduling-poll-bundle est un bundle Symfony pour les sondages de disponibilités. L'organisateur propose de deux à
cent dates, chacune avec un début et une fin, affichées dans un fuseau horaire du sondage, et fixe une échéance. Chaque
répondant se joint depuis la page du sondage avec un nom seulement — une adresse seulement s'il veut recevoir la date
choisie, un compte seulement s'il en a un — ou est invité par l'organisateur, et reçoit un lien personnel de
`phil/access-links` : un jeton de 256 bits conservé seulement sous forme d'empreinte à clé. Avec ce lien, il répond oui,
au besoin ou non à chaque date, lit les dates dans son propre fuseau horaire et change ses réponses jusqu'à la clôture
du sondage.

L'organisateur clôt le sondage sur la date de son choix, ou une tâche planifiée le clôt à l'échéance sur la meilleure
date : le plus de réponses oui, puis le plus de réponses au besoin, puis la plus hâtive. Les réponses sont comptées par
le moteur de vote de `phil/poll`. La clôture est une seule écriture conditionnelle : une clôture manuelle qui course
l'échéance clôt donc le sondage une seule fois. La clôture gagnante met en file un seul message de Messenger ne portant
que des identifiants, et le *worker* envoie à chaque répondant la date avec son fichier iCalendar, écrit par
`phil/calendar-sync`, chaque avis étant réclamé une seule fois. Que l'organisateur voie les réponses avant la clôture,
et qui d'autre les voit, est un choix du sondage. Les noms et les adresses sont effacés un délai après la clôture ;
l'export et l'effacement d'une personne passent par les contrats de `phil/privacy-bundle`.

Les pages d'un répondant — la page du sondage et la sienne — sont fournies avec le bundle : un tableau des dates avec
trois boutons radio par ligne, accessible, utilisable au clavier et sans JavaScript, dessiné avec les recettes du kit
PHIL UX. Deux contrôleurs Stimulus s'y ajoutent : les dates écrites dans le fuseau horaire que le répondant choisit, et
l'événement DOM `scheduling-poll:chosen`, qui joue la célébration du kit une fois la date choisie.

## État

| Partie | État |
|---|---|
| Sondages de deux à cent dates, en UTC avec un fuseau horaire pour les afficher, une échéance, une description et un lieu | Implémenté et testé |
| Répondants avec un nom seulement, adresse et compte facultatifs, invités ou se joignant d'eux-mêmes, nom de cent caractères | Implémenté et testé |
| Liens personnels de `phil/access-links` : ouverts sans effet de bord, émis de nouveau, révoqués | Implémenté et testé |
| Réponses oui, au besoin ou non, écrites tant que le sondage est ouvert, fuseau horaire de chaque répondant | Implémenté et testé |
| Le décompte sur `phil/poll`, son bris d'égalité et ses places ; la date choisie par l'organisateur ou à l'échéance | Implémenté et testé |
| Une clôture, une annonce : une écriture conditionnelle, puis des messages de Messenger portant des identifiants, chaque avis réclamé une fois | Implémenté et testé |
| Le courriel de la date choisie avec son fichier iCalendar, dans la langue et le fuseau horaire du répondant | Implémenté et testé |
| Qui voit les réponses avant la clôture : l'organisateur si le sondage le dit, les autres selon `ResultsVisibility` | Implémenté et testé |
| Le calendrier `phil_scheduling_poll`, avec état et verrou : échéances clôturées, noms et adresses effacés | Implémenté et testé |
| Export et effacement par `phil/privacy-bundle` | Implémenté et testé |
| Correspondance Doctrine et dépôts, un dépôt en mémoire ; les tables des liens ajoutées au schéma généré | Implémenté et testé |
| Les pages d'un répondant et le tableau des réponses, avec le kit PHIL UX ; deux contrôleurs Stimulus | Implémenté et testé |
| Une grille de temps libre peinte par chaque répondant, au lieu de dates fixes | Non commencé |
| Une recette Flex | Non commencé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les tables, les routes, les recettes du kit. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Sondages et réponses](doc/fr/polls-and-answers.md) | Créer un sondage, répondants, liens, réponses, fuseaux horaires, qui voit quoi. |
| [Clôture et date choisie](doc/fr/closing.md) | Le choix de l'organisateur, l'échéance, le bris d'égalité, l'annonce, le courriel et son fichier iCalendar. |
| [Pages et gabarits](doc/fr/pages.md) | Les routes, les gabarits, leurs blocs, les contrôleurs Stimulus, la célébration. |
| [Vie privée](doc/fr/privacy.md) | Ce qui est conservé, l'effacement après la clôture, l'export et l'effacement d'une personne. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les événements à écouter, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester du code qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type public du bundle, avec son rôle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\SchedulingPollBundle\PhilSchedulingPollBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_scheduling_poll.yaml
phil_scheduling_poll:
    links:
        keys: ['%env(SCHEDULING_POLL_LINK_KEY)%']
        page: 'https://example.org/scheduling-poll/answer'
```

```yaml
# config/routes/phil_scheduling_poll.yaml
phil_scheduling_poll:
    resource: '@PhilSchedulingPollBundle/config/routes.php'
    prefix: /scheduling-poll
```

```php
use Phil\SchedulingPollBundle\Application\ProposedSlot;
use Phil\SchedulingPollBundle\Application\SchedulingPollDraft;

$zone = new DateTimeZone('America/Toronto');
$poll = $polls->create(new SchedulingPollDraft('Game night', [
    new ProposedSlot(new DateTimeImmutable('2026-12-04 19:00', $zone), new DateTimeImmutable('2026-12-04 23:00', $zone)),
    new ProposedSlot(new DateTimeImmutable('2026-12-05 19:00', $zone), new DateTimeImmutable('2026-12-05 23:00', $zone)),
], 'America/Toronto', new DateTimeImmutable('2026-11-28 12:00', $zone)), $organizer);

// Share https://example.org/scheduling-poll/{poll}/join, or invite by name:
$link = $polls->invite($poll->id, $organizer, 'Bob', 'bob@example.org');
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-intl` et `ext-mbstring`, Symfony 8.2 ou une version plus récente,
Doctrine ORM 3.7 et DBAL 4.5, et Messenger, Mailer et Scheduler de Symfony ; TwigBundle, `twig/intl-extra` et les
recettes du kit PHIL UX pour les pages.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
