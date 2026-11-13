<!-- phil-dev source-sha256=32d08d005aef37ee72431923b35e46427866c6875a18a5ba461729c43288616d -->
# phil/automations

**Des automatisations que les éditeurs d'un site construisent sans code : quand un événement survient, exécuter ces étapes, attendre, vérifier, embrancher, et défaire ce qui a été fait si une étape suivante échoue.**

phil/automations exécute des automatisations définies comme des données. Une définition nomme un déclencheur — un
abonnement confirmé, une commande payée — et une liste d'étapes : des actions, des délais, des filtres et des
embranchements. Les paquets enregistrent les déclencheurs et les actions qu'ils offrent, chacun décrivant ses options,
de sorte qu'un constructeur sans code puisse les lister et que le validateur puisse vérifier une définition avant sa
publication. Quand le site distribue un événement, chaque définition activée dont le déclencheur correspond démarre une
exécution, une seule par occurrence de l'événement. Les exécutions se poursuivent par Symfony Messenger : un délai
envoie un message différé, et une planification de Symfony Scheduler reprend ce qui est dû, si bien qu'un délai de
trois semaines survit aux déploiements et aux redémarrages. Une étape qui échoue d'une façon qui vaut une nouvelle
tentative attend et réessaie ; passé ses tentatives, les étapes déjà faites sont défaites, en ordre inverse, par les
actions qui savent comment.

Elle convient à toute application PHP dont le personnel veut réagir à ce qui se passe sur le site sans demander à un
développeur : une séquence de bienvenue par courriel, un suivi après une commande, un webhook vers un autre service
quand un membre s'inscrit, une étiquette ajoutée quand un formulaire est rempli. Les séquences de courriel en sont un
usage : le paquet de l'infolettre offre les actions qui envoient un message ou modifient un abonnement, et aucun
déclencheur ne part à l'ouverture d'un courriel.

C'est une bibliothèque PHP ordinaire, construite sur des composants de Symfony — Messenger, Scheduler,
ExpressionLanguage, le client HTTP — avec `phil/standard-webhooks` pour l'action de webhook et `phil/url` pour lire les
adresses. Les exécutions ne portent que des identifiants, jamais de renseignements personnels : ce qui est lourd ou
personnel est lu par l'action dans son propre magasin, à partir de son identifiant. Plusieurs *workers* peuvent traiter
les mêmes exécutions : une exécution est créée une seule fois par clé d'événement, et un *worker* ne sauvegarde une
exécution que sur la révision qu'il a lue, de sorte qu'un seul *worker* à la fois la détient. Les définitions et les
exécutions vivent en mémoire ou dans deux tables d'une connexion Doctrine DBAL sur PostgreSQL, MySQL, MariaDB ou SQLite.

## État

| Partie | État |
|---|---|
| Les définitions comme données : étapes d'action, de délai, de filtre et d'embranchement, lues et écrites en JSON, bornées | Implémenté et testé |
| Le catalogue des déclencheurs, des actions et des langages de condition, avec les descriptions d'options pour un constructeur, et le validateur | Implémenté et testé |
| Les conditions en ExpressionLanguage de Symfony, sans fonctions, sur les identifiants de l'exécution | Implémenté et testé |
| Le moteur : exécutions démarrées une fois par événement, baux, nouvelles tentatives avec attente croissante, compensation en ordre inverse | Implémenté et testé |
| Les messages et les *handlers* de Messenger, la planification de Scheduler qui reprend les exécutions dues | Implémenté et testé |
| L'action de webhook, signée selon Standard Webhooks, protégée contre les réseaux privés | Implémenté et testé |
| Les magasins en mémoire et Doctrine DBAL, testés sur MySQL 8.4 et PostgreSQL 17 | Implémenté et testé |
| Une cible de fuzzing pour le lecteur de définitions | Implémenté |
| Les pages d'un constructeur et le câblage d'un bundle Symfony | Hors de cette bibliothèque |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la déclaration des services dans Symfony. |
| [Définitions](doc/fr/definitions.md) | Le JSON d'une définition, chaque sorte d'étape, les conditions, les limites, les versions, la validation. |
| [Déclencheurs et actions](doc/fr/components.md) | Écrire un déclencheur et une action, les options, la compensation, l'action de webhook. |
| [Exécutions](doc/fr/runs.md) | Le déroulement d'une exécution : événements, messages, baux, délais, nouvelles tentatives, compensation, états. |
| [Stockage](doc/fr/storage.md) | Les magasins, les tables de la base de données, la purge. |
| [Séquences de courriel](doc/fr/email-sequences.md) | Une séquence de courriel comme automatisation, et ce qu'elle ne fait jamais. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et comment tester une automatisation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/automations
```

```php
use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Definition\DefinitionReader;
use Phil\Automations\Definition\DefinitionValidator;
use Phil\Automations\Definition\InMemoryDefinitions;
use Phil\Automations\Engine\AutomationEngine;
use Phil\Automations\Expression\ExpressionConditions;
use Phil\Automations\Run\InMemoryRunStore;

$catalogue = new Catalogue([$orderPaidTrigger], [$webhookAction, $sendAction], [new ExpressionConditions()]);
$definition = new DefinitionReader()->read($jsonFromTheBuilder);
new DefinitionValidator($catalogue)->validate($definition);

$definitions = new InMemoryDefinitions();
$definitions->publish($definition);

$engine = new AutomationEngine($catalogue, $definitions, new InMemoryRunStore(), $messageBus, $clock);
$engine->handle(new OrderPaid($order));   // starts the runs, sends an AdvanceRun for each
```

Un *worker* traite `AdvanceRun` avec `AdvanceRunHandler`, et la planification `automations` envoie `ResumeDueRuns`
chaque minute.

## Prérequis

PHP 8.5 ou une version plus récente, et les composants de Symfony 8.2. Doctrine DBAL 4.5 ou une version plus récente
pour les magasins de base de données.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
