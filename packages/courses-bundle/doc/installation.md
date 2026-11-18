# Installation

```sh
composer require phil/courses-bundle
```

The package needs PHP 8.5 or later with the `dom`, `libxml`, `mbstring`, `sodium` and `zip` extensions, Symfony 8.2 or
later, and Doctrine DBAL 4.5. Symfony Flex registers the bundle, its configuration and its routes from the recipe;
without Flex, register it in `config/bundles.php`:

```php
Phil\CoursesBundle\PhilCoursesBundle::class => ['all' => true],
```

## The tables

The bundle keeps its tables through DBAL, on the connection `phil_courses.connection` names. With the ORM, the schema
listener adds them to the schema Doctrine generates, so `doctrine:migrations:diff` writes the migration that creates
them. Without the ORM, create them from `CourseTables`:

```php
foreach (CourseTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

When a version of the bundle adds a table or a column, `doctrine:migrations:diff` writes the migration that adds them.
Without the ORM, `CourseTables::upgrade()` gives the statements that bring the tables of a connection up to date: the
tables it lacks created, the columns and indexes a table lacks added — nullable, so the rows already kept stay valid —,
nothing changed or dropped. Run once more, it gives none. It is tested on SQLite, MySQL 8.4 and PostgreSQL 17. An
enrollment whose cmi5 units were satisfied before the upgrade receives the `satisfied` statements of its blocks and of
its course at its next outcome, as [cmi5](cmi5.md#the-satisfied-statements) explains.

```php
foreach (CourseTables::upgrade($connection) as $sql) {
    $connection->executeStatement($sql);
}
```

## The routes

Four route files, each imported under the prefix of the application's choice:

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

| File | Routes | Firewall |
|---|---|---|
| `config/routes.php` | The pages of a course: the outline, the enrollment, the launch, the completion, the second chance, and the commit endpoint of the SCORM sessions | Behind the application's firewall: the pages need a learner, and a commit names its attempt by its token |
| `config/cmi5_routes.php` | The fetch URL and the xAPI endpoint the cmi5 units talk to | Outside it: each request carries the token of its session, not the learner's cookies |
| `config/content_routes.php` | The files of the imported packages, and the frame of the SCORM run time under `_phil/scorm/` | Outside it, and answered on the content origin only |
| `config/admin_routes.php` | The administration page of the second chances | Behind the application's firewall: the page asks for the role `phil_courses.admin.role` |

An application whose web server serves the storage directory on the content origin does not import the content routes;
it routes `<content base URL>/_phil/scorm/` to the application, for the frame of the SCORM run time, and
[Security](security.md) gives the headers to send.

## The Stimulus controller

The SCORM run time draws the Stimulus controller `phil--courses-bundle--scorm-runtime`, with AssetMapper and
symfony/stimulus-bundle: nothing is built. The bundle maps its controller under `@phil/courses-bundle`; enable it in
`assets/controllers.json`, fetched lazily, only on the pages of SCORM activities:

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

## The content origin

The files of an imported package hold scripts the application did not write. They are served from a content origin —
another host than the application's, best on another registrable domain — so that those scripts never run with the
application's cookies. Point a host of the content origin at the application, or at a web server that serves the
storage directory, and name it in `phil_courses.content.base_url`. Without it, the bundle imports packages but does not
launch their content.

## The tables of the SCORM attempts

`phil_course_scorm_attempt` and `phil_course_scorm_data` keep the attempts and their tracking data. They come with the
other tables, from the schema listener or `CourseTables::configure()`, and `CourseTables::upgrade()` adds them to the
tables of 0.1. Their keys use the binary collation `utf8mb4_bin` on MySQL and MariaDB when the schema is given the
platform — the schema listener and `upgrade()` always give it —; the tracking data is a `MEDIUMTEXT` there.

## The pages

The pages extend `@PhilCourses/layout.html.twig`, or the layout `phil_courses.layout` names, which fills the blocks
`title` and `main`. They draw the recipes `Button`, `Badge`, `Progress` and `Textarea` of the kit, which the Symfony UX
Toolkit installs in the application:

```sh
php bin/console ux:install button badge progress textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The administration page of the second chances needs Symfony Security: its authorization checker grants the role of
`phil_courses.admin.role`, and without one the page is refused to everyone.

The learner is the user Symfony Security signed in, named by their user identifier. An application whose users sign in
with their e-mail address names its own `CurrentLearner` in `phil_courses.current_learner`: the bundle refuses an
identifier that holds an `@`, which would travel in the cmi5 statements.

## The recipe

`recipe/0.1` registers the bundle, copies its configuration and its routes — the administration page under
`/admin/courses` —, and adds `PHIL_COURSES_CONTENT_URL` and `PHIL_COURSES_HOME_PAGE` to the environment.

## The remote learning record store

To send the statements to a remote xAPI store, install phil/safe-http and enable `phil_courses.cmi5.remote_lrs`, as
[cmi5](cmi5.md#a-remote-xapi-store) explains:

```sh
composer require phil/safe-http
```
