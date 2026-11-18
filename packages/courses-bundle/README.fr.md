<!-- phil-dev source-sha256=48c32f1f6b222bd68ae82645fa8c2b921f718833908d62d36efbc773685a7e45 -->
# phil/courses-bundle

**Cours en ligne pour Symfony : modules, activités, progression et achèvement, avec des cours SCORM et cmi5 exécutés dans la plateforme.**

phil/courses-bundle donne à une application des cours en ligne. Un cours contient des modules dans l'ordre, et chaque
module contient des activités : une page de texte, un lien, le contenu d'un paquet SCORM importé, ou une unité
assignable cmi5. Les types d'activité sont un port muni de quelques adaptateurs, ce qui permet à une application
d'ajouter les siens. Un apprenant s'inscrit une seule fois à un cours ; chaque activité enregistre ce qu'il a fait —
terminé, réussi, échoué, dispensé, meilleur score — et est satisfaite par l'exigence qu'elle porte, les valeurs du
`moveOn` de cmi5. Le cours est terminé quand chaque activité obligatoire est satisfaite.

Un module ou une activité s'ouvre tout de suite, à une date, ou un nombre de jours après l'inscription de l'apprenant,
comptés à partir de minuit dans le fuseau horaire du site. Un cours peut imposer l'ordre de ses activités. Aucun état
n'est une impasse : un apprenant qui a échoué à tous les essais d'une activité demande une seconde chance, qu'un
enseignant accorde, refuse, ou à laquelle il répond en dispensant l'activité, avec une note, sur une page
d'administration ou depuis la console ; avec phil/audit-bundle, chaque étape est écrite dans la piste d'audit.

Le bundle importe des paquets SCORM 1.2, SCORM 2004 et cmi5, pour les exécuter ou pour migrer des cours d'une autre
plateforme, lus par phil/elearning-packages. L'archive ZIP est lue dans des limites — sa taille, son nombre d'entrées,
la taille décompressée de chaque fichier, son taux de compression —, et une entrée qui sortirait du paquet, un lien
symbolique ou une entrée corrompue est refusé. Le manifeste est analysé sans type de document ni entité. Les fichiers
sont stockés à part, et servis depuis une origine de contenu qui n'est pas celle de l'application, avec une politique de
sécurité du contenu.
Le contenu SCORM s'exécute dans la plateforme : un contrôleur Stimulus l'encadre sous l'API d'exécution de SCORM 1.2
(`API`) ou de SCORM 2004 (`API_1484_11`), qui répond tout de suite à chaque appel selon le modèle de données de
phil/elearning-packages et envoie les validations à un point de terminaison qui les vérifie de nouveau. Les tentatives
sont conservées avec leurs données de suivi — suspendues et reprises, recommencées —, et alimentent la progression de
l'apprenant, la note de maîtrise du manifeste comprise.
L'arbre des blocs d'une structure de cours cmi5 est conservé : blocs imbriqués, leur ordre et leurs identifiants.
Les unités cmi5 sont lancées avec les paramètres de cmi5, récupèrent leur jeton une seule fois et envoient leurs énoncés
à un point de terminaison xAPI du bundle, qui les vérifie selon les règles de cmi5 avant qu'un entrepôt de traces
d'apprentissage les conserve. La plateforme émet l'énoncé `satisfied` de chaque bloc une fois ses unités obligatoires
satisfaites, puis celui du cours, une seule fois pour chaque inscription. L'entrepôt intégré au bundle conserve les
énoncés par DBAL ; le bundle les envoie aussi à un entrepôt xAPI distant par le client protégé de phil/safe-http, les
documents d'état des unités restant dans la base de données de l'application, et l'entrepôt propre à une application se
branche sur le même port.

Une autre partie de l'application — certificats, insignes, rapport — demande à `Completions` si un apprenant a terminé un
cours, et garde son propre modèle ; avec phil/certificates-bundle, le bundle répond à son port `CourseCompletions`.

Les services ne gardent aucun état entre les requêtes et sont sûrs dans un *worker* de longue durée. Les entrepôts
utilisent DBAL, et sont testés sur SQLite, MySQL 8.4 et PostgreSQL 17.

## État

| Partie | État |
|---|---|
| Cours, modules et activités par DBAL ; types d'activité en port avec les adaptateurs page, lien, SCORM et cmi5 | Implémenté et testé |
| Inscription, progression par activité, exigence de chaque activité, achèvement par cours, événements PSR-14 | Implémenté et testé |
| `Completions`, et l'adaptateur du port `CourseCompletions` de phil/certificates-bundle 0.2 | Implémenté et testé |
| Ouverture à une date ou après un nombre de jours suivant l'inscription dans le fuseau horaire du site ; ordre imposé | Implémenté et testé |
| Secondes chances demandées, accordées, refusées ou réglées par une dispense avec une note, piste de phil/audit-bundle | Implémenté et testé |
| La page d'administration des secondes chances, derrière un rôle configurable, avec les recettes du kit, en anglais et en français | Implémenté et testé |
| Import de paquets SCORM 1.2 et 2004 par phil/elearning-packages, séquencement conservé à titre d'information, résultats importés d'une exportation CSV | Implémenté et testé |
| L'environnement d'exécution SCORM : l'API de SCORM 1.2 et 2004 dans le navigateur, chaque élément de leurs modèles de données vérifié dans le navigateur et sur le serveur, tentatives suspendues, reprises et recommencées, données de suivi conservées, progression enregistrée, demandes de navigation suivies | Implémenté, testé sur SQLite, MySQL et PostgreSQL, et soumis au fuzzing |
| Les données de suivi des tentatives SCORM dans les demandes d'accès et d'effacement de phil/privacy-bundle | Implémenté et testé |
| Le séquencement et la navigation de SCORM 2004 au-delà du flux et du choix du plan : règles, cumul, objectifs partagés | Pas encore : voir [Environnement d'exécution SCORM](doc/fr/scorm-runtime.md#ce-que-le-bundle-ne-fait-pas-encore) |
| Import de paquets et de structures de cours cmi5, par phil/elearning-packages, l'arbre des blocs conservé, une unité dont le `moveOn` est `NotApplicable` non obligatoire | Implémenté et testé |
| Lancements cmi5, URL de récupération, point de terminaison xAPI avec les règles de cmi5, entrepôt de traces d'apprentissage en port | Implémenté et testé |
| Les énoncés `satisfied` des blocs, les plus profonds d'abord, et du cours, une seule fois pour chaque inscription, nommé par la structure de cours, avec l'événement `BlockSatisfied` | Implémenté et testé |
| L'entrepôt de traces d'apprentissage xAPI distant par phil/safe-http, pour les énoncés | Implémenté et testé ; son test d'intégration s'exécute contre un entrepôt que nomme l'environnement |
| Les documents d'état des unités avec un entrepôt distant | Conservés dans l'entrepôt intégré au bundle, jamais envoyés : le port ne nomme aucun agent, que la ressource State de xAPI exige |
| Un entrepôt distant sur un réseau privé | Refusé par phil/safe-http : l'adaptateur du port propre à une application l'atteint |
| La mise à niveau des tables d'un schéma antérieur, sur SQLite, MySQL et PostgreSQL | Implémenté et testé |
| Contenu servi depuis sa propre origine, avec sa politique de sécurité du contenu | Implémenté et testé |
| `phil:courses:import`, `phil:courses:import-results`, `phil:courses:retries` | Implémenté et testé |
| Les pages — plan, activité, inscription, seconde chance — avec les recettes du kit, en anglais et en français | Implémenté et testé |
| La recette Flex : le bundle, sa configuration et ses routes | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |
| Leçons à embranchements par phil/story-graph, questionnaires par phil/assessment, accès par phil/content-access | Prévu, comme types d'activité et port d'accès, une fois ces bibliothèques écrites |
| Activités LTI 1.3, vidéo hébergée avec suivi du visionnement consenti | Prévu, comme types d'activité qu'ajoutent les bundles LTI et vidéo |
| Cohortes avec rencontres en direct, cours vendus par le catalogue, dépendances entre cours, parcours d'apprentissage | Prévu |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, les tables et leur mise à niveau, les routes, l'origine du contenu, les recettes du kit, la recette. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Le modèle de cours](doc/fr/course-model.md) | Cours, modules, blocs, activités, inscriptions, progression, ouvertures, ordre et secondes chances, avec leur page d'administration. |
| [Import SCORM](doc/fr/scorm-import.md) | Ce que devient un paquet SCORM, ce qui est conservé à titre d'information, ce qui est laissé de côté, et les résultats importés. |
| [Environnement d'exécution SCORM](doc/fr/scorm-runtime.md) | Comment un SCO s'exécute : l'API dans le navigateur, le point de terminaison de validation, les tentatives, la progression, les paquets de plusieurs SCO, ce qui est pris en charge et ce qui ne l'est pas encore, la vie privée. |
| [cmi5](doc/fr/cmi5.md) | L'import de paquets cmi5 et de leurs blocs, le lancement, l'URL de récupération, le point de terminaison xAPI, les énoncés `satisfied` et les entrepôts de traces d'apprentissage, intégré ou distant. |
| [Sécurité](doc/fr/security.md) | Les limites de l'archive et du manifeste, l'origine du contenu et ses en-têtes, l'environnement d'exécution SCORM, l'entrepôt distant, la page d'administration. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les événements, les services à remplacer, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs et l'entrepôt de traces d'apprentissage que lisent ses tests d'intégration, et la cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque fichier du bundle. |

## Démarrage rapide

```sh
composer require phil/courses-bundle
php bin/console ux:install button badge progress textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_courses.yaml
phil_courses:
    timezone: America/Toronto
    content:
        base_url: 'https://content.example.org'
        application_origin: 'https://www.example.org'
    cmi5:
        actor_home_page: 'https://www.example.org'
```

```sh
php bin/console phil:courses:import var/import/safety-course.zip
```

## Prérequis

PHP 8.5 ou une version plus récente avec les extensions `mbstring` et `sodium`, et celles avec lesquelles
phil/elearning-packages lit les paquets — `dom`, `libxml` et `zip` —, Symfony 8.2 ou une version plus récente, et
Doctrine DBAL 4.5. L'entrepôt de traces d'apprentissage distant demande phil/safe-http, la page d'administration
Symfony Security, et l'environnement d'exécution SCORM symfony/stimulus-bundle avec AssetMapper.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
