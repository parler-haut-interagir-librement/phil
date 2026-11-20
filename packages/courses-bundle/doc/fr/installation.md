<!-- phil-dev source-sha256=d532df0e29b8ce1eb6907dcb313c12a4d539a32a834bb70086fb72de23fc77ec -->
# Installation

```sh
composer require phil/courses-bundle
```

Le paquet exige PHP 8.5 ou une version plus récente avec les extensions `dom`, `libxml`, `mbstring`, `sodium` et `zip`,
Symfony 8.2 ou une version plus récente, et Doctrine DBAL 4.5. Symfony Flex enregistre le bundle, sa configuration et
ses routes à partir de la recette ; sans Flex, enregistrez le bundle dans `config/bundles.php` :

```php
Phil\CoursesBundle\PhilCoursesBundle::class => ['all' => true],
```

## Les tables

Le bundle conserve ses tables par DBAL, sur la connexion que nomme `phil_courses.connection`. Avec l'ORM, l'écouteur de
schéma les ajoute au schéma que Doctrine génère : `doctrine:migrations:diff` écrit donc la migration qui les crée. Sans
l'ORM, créez-les à partir de `CourseTables` :

```php
foreach (CourseTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

Quand une version du bundle ajoute une table ou une colonne, `doctrine:migrations:diff` écrit la migration qui les
ajoute. Sans l'ORM, `CourseTables::upgrade()` donne les instructions qui mettent à jour les tables d'une connexion : les
tables manquantes sont créées, les colonnes et les index qui manquent à une table sont ajoutés — nullables, de sorte que
les lignes déjà conservées restent valides —, rien n'est modifié ni supprimé. Exécutée une autre fois, elle n'en donne
aucune. Elle est testée sur SQLite, MySQL 8.4 et PostgreSQL 17. Une
inscription dont les unités cmi5 étaient satisfaites avant la mise à niveau reçoit les énoncés `satisfied` de ses blocs
et de son cours à son prochain résultat, comme l'explique [cmi5](cmi5.md#les-énoncés-satisfied).

```php
foreach (CourseTables::upgrade($connection) as $sql) {
    $connection->executeStatement($sql);
}
```

## Les routes

Quatre fichiers de routes, chacun importé sous le préfixe que choisit l'application :

```yaml
# config/routes/phil_courses.yaml
phil_courses:
    resource: '@PhilCoursesBundle/config/routes.php'
    prefix: /courses

phil_courses_cmi5:
    resource: '@PhilCoursesBundle/config/cmi5_routes.php'
    prefix: /cmi5

phil_courses_content:
    resource: '@PhilCoursesBundle/config/content_routes.php'
    prefix: /course-content

phil_courses_admin:
    resource: '@PhilCoursesBundle/config/admin_routes.php'
    prefix: /admin/courses
```

| Fichier | Routes | Pare-feu |
|---|---|---|
| `config/routes.php` | Les pages d'un cours : le plan, l'inscription, le lancement, l'achèvement, la seconde chance, et le point de terminaison de validation des sessions SCORM, la formation et le relevé de l'apprenant | Derrière le pare-feu de l'application : les pages exigent un apprenant, et une validation nomme sa tentative par son jeton |
| `config/cmi5_routes.php` | L'URL de récupération et le point de terminaison xAPI auxquels parlent les unités cmi5 et les activités xAPI | En dehors : chaque requête porte le jeton de sa session, non les témoins de connexion de l'apprenant |
| `config/content_routes.php` | Les fichiers des paquets importés, et le cadre de l'environnement d'exécution SCORM sous `_phil/scorm/` | En dehors, et servis sur l'origine du contenu seulement |
| `config/admin_routes.php` | Les pages d'administration : les secondes chances, la formation en retard ou bientôt due, les relevés des apprenants | Derrière le pare-feu de l'application : les pages demandent les rôles `phil_courses.admin.role` et `phil_courses.training.manager_role` |

Une application dont le serveur Web sert le répertoire de stockage sur l'origine du contenu n'importe pas les routes du
contenu ; il achemine `<content base URL>/_phil/scorm/` vers l'application, pour le cadre de l'environnement d'exécution
SCORM, et [Sécurité](security.md) donne les en-têtes à envoyer.

## Le contrôleur Stimulus

L'environnement d'exécution SCORM fait appel au contrôleur Stimulus `phil--courses-bundle--scorm-runtime`, avec
AssetMapper et symfony/stimulus-bundle : rien n'est compilé. Le bundle associe son contrôleur sous `@phil/courses-bundle` ;
activez-le dans `assets/controllers.json`, chargé paresseusement, sur les seules pages des activités SCORM :

```json
{
    "controllers": {
        "@phil/courses-bundle": {
            "scorm-runtime": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

```sh
composer require symfony/asset-mapper symfony/stimulus-bundle
```

## L'origine du contenu

Les fichiers d'un paquet importé contiennent des scripts que l'application n'a pas écrits. Ils sont servis depuis une
origine de contenu — un autre hôte que celui de l'application, idéalement sur un autre domaine enregistrable — afin que
ces scripts ne s'exécutent jamais avec les témoins de connexion de l'application. Faites pointer un hôte de l'origine du
contenu vers l'application, ou vers un serveur Web qui sert le répertoire de stockage, et nommez-le dans
`phil_courses.content.base_url`. Sans lui, le bundle importe les paquets mais ne lance pas leur contenu.

## Les tables des tentatives SCORM

`phil_course_scorm_attempt` et `phil_course_scorm_data` conservent les tentatives et leurs données de suivi. Elles
viennent avec les autres tables, par l'écouteur de schéma ou `CourseTables::configure()`, et `CourseTables::upgrade()` les
ajoute aux tables de 0.1. Leurs clés utilisent la collation binaire `utf8mb4_bin` sur MySQL et MariaDB quand le schéma
reçoit la plateforme — l'écouteur de schéma et `upgrade()` la donnent toujours — ; les données de suivi y sont un
`MEDIUMTEXT`.

## Les tables de la formation obligatoire

`phil_course_assignment` et `phil_course_obligation` conservent les assignations et l'obligation de chaque apprenant
sous elles ; `phil_course_transcript` et `phil_course_credits`, les relevés et les crédits que chaque cours accorde.
Elles viennent avec les autres tables, par l'écouteur de schéma ou `CourseTables::configure()`, et
`CourseTables::upgrade()` les ajoute aux tables d'une version antérieure ; avec l'ORM, `doctrine:migrations:diff` écrit
la même migration. Le bundle ne fournit aucune entité Doctrine ; sa recette ne porte donc aucune migration.

## Le calendrier de la formation obligatoire

Avec symfony/scheduler et symfony/messenger installés, le calendrier `phil_courses_training` exécute les rappels et les
recertifications chaque jour ; un worker le consomme :

```sh
composer require symfony/scheduler symfony/messenger
php bin/console messenger:consume scheduler_phil_courses_training
```

Sans eux, `phil:courses:training` s'exécute depuis cron ; voir [formation obligatoire](mandatory-training.md#lexécution).
Les rappels passent par phil/notifications-bundle quand il est enregistré.

## Les pages

Les pages étendent `@PhilCourses/layout.html.twig`, ou la mise en page que nomme `phil_courses.layout`, qui remplit les
blocs `title` et `main`. Elles utilisent les recettes `Button`, `Badge`, `Progress` et `Textarea` du kit, que le Symfony
UX Toolkit installe dans l'application :

```sh
php bin/console ux:install button badge progress textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Les pages d'administration demandent Symfony Security : son vérificateur d'autorisation accorde le rôle de
`phil_courses.admin.role` pour les secondes chances et de `phil_courses.training.manager_role` pour la formation des
apprenants, et sans lui les pages sont refusées à tout le monde.

L'apprenant est l'utilisateur que Symfony Security a connecté, nommé par son identifiant d'utilisateur. Une application
dont les utilisateurs se connectent avec leur adresse courriel nomme son propre `CurrentLearner` dans
`phil_courses.current_learner` : le bundle refuse un identifiant qui contient un `@`, qui voyagerait dans les énoncés
cmi5.

## La recette

`recipe/0.2` enregistre le bundle, copie sa configuration et ses routes — la page d'administration sous
`/admin/courses` —, et ajoute `PHIL_COURSES_CONTENT_URL` et `PHIL_COURSES_HOME_PAGE` à l'environnement. Sa
configuration nomme le rôle des gestionnaires qui voient la formation de chaque apprenant. La recette de `recipe/0.1`
sert les versions 0.1.

## L'entrepôt de traces d'apprentissage distant

Pour envoyer les énoncés à un entrepôt xAPI distant, installez phil/safe-http et activez
`phil_courses.cmi5.remote_lrs`, comme l'explique [cmi5](cmi5.md#un-entrepôt-xapi-distant) :

```sh
composer require phil/safe-http
```
