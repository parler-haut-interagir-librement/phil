<!-- phil-dev source-sha256=07eeb1c31430b80c46f9efc580bf8e26d80909e1d3de2ac9eaaaf755e73d9e37 -->
# phil/scheduled-transitions-bundle

**Publiez une page lundi à neuf heures, retirez-la un mois plus tard, sans personne en ligne.**

phil/scheduled-transitions-bundle planifie des transitions à un moment donné dans une application Symfony. Une
transition est un nom, comme `publish`, `unpublish` ou `archive`, appliqué à une ressource à une heure donnée. Elle est
gardée dans Doctrine jusqu'à son échéance, puis appliquée par un `TransitionHandler` qu'écrit l'application, ou par
`symfony/workflow` avec ses gardes et ses événements. Un gestionnaire qui lance une exception est une tentative échouée :
la transition reste en attente et est retentée à l'exécution suivante, jusqu'à épuisement de ses tentatives. Planifier
deux fois la même transition pour une ressource la déplace au lieu de la doubler.

Il s'adresse à une application où des rédacteurs préparent du contenu à l'avance : une annonce mise en ligne à une
heure fixe, une promotion qui prend fin d'elle-même, une page archivée après un événement. Le bundle ne sait rien de ce
que publier veut dire pour une page ; le gestionnaire ou le workflow le sait.

Le bundle s'exécute depuis Symfony Scheduler, qui consomme un message chaque minute par défaut, ou depuis
`phil:scheduled-transitions:run` dans une tâche *cron* quand il n'y a pas de *worker*. Les ressources sont nommées par
`phil/resource`. La conception garde dans le bundle la planification, les tentatives et l'exécution, et laisse l'effet
de chaque transition à un port : un même calendrier sert ainsi toutes les sortes de ressources.

## État

| Partie | État |
|---|---|
| `ScheduledTransition`, `TransitionStatus`, le port du dépôt | Implémenté et testé |
| `Scheduler` : planifier, déplacer, annuler, exécuter ce qui est échu ; `TransitionHandler`, `RunReport` | Implémenté et testé |
| `DoctrineScheduledTransitionRepository` | Implémenté et testé |
| `WorkflowTransitionHandler`, `SubjectResolver` | Implémenté et testé |
| Symfony Scheduler : le calendrier `phil_transitions` ; `phil:scheduled-transitions:run` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, la configuration, la table. |
| [Planification](doc/fr/scheduling.md) | Planifier, déplacer, annuler, les exécutions et les nouvelles tentatives. |
| [Gestionnaires](doc/fr/handlers.md) | Un gestionnaire propre à l'application ; le gestionnaire par workflow. |
| [Exécution](doc/fr/running.md) | Symfony Scheduler, ou la commande depuis *cron* ; les exécutions qui se chevauchent ; un gestionnaire qui brise le gestionnaire d'entités. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```php
$scheduler->schedule(ResourceReference::of('page', $page->id), 'publish', new DateTimeImmutable('2026-11-09 09:00'));
```

```bash
bin/console messenger:consume scheduler_phil_transitions
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; Doctrine ORM 3.7 ; PSR-20 ; l'injection de dépendances, la configuration et
UID de Symfony 8.2 ; `phil/resource` 0.1 pour les références de ressources. Symfony Scheduler et Messenger, Workflow et
Console sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
