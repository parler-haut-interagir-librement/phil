# Installation

```sh
composer require phil/survey-bundle
```

Then register the bundle in `config/bundles.php`:

```php
Phil\SurveyBundle\PhilSurveyBundle::class => ['all' => true],
```

The package needs PHP 8.5 or later and Symfony 8.2 or later.

## The tables

With the Doctrine ORM, the bundle adds its tables to the schema the ORM generates, so `doctrine:migrations:diff`
writes their migration. Without it, `Infrastructure\Doctrine\SurveyTables::configure()` adds them to a DBAL `Schema`.
The tables are prefixed `phil_survey_`, on the connection `phil_survey.connection`.

## The pages

Turn on Symfony's CSRF protection, which the forms need, and import the routes:

```yaml
# config/packages/framework.yaml
framework:
    csrf_protection: true
```

```yaml
# config/routes/phil_survey.yaml
phil_survey:
    resource: '@PhilSurveyBundle/config/routes.php'
    prefix: /survey
```

The templates use the `html_attr` function of `twig/html-extra`: install `twig/extra-bundle` to register it. With
AssetMapper and Symfony UX, the `progress` Stimulus controller of `assets/` is registered by Symfony Flex; the
pages work without it.

## The purge

The schedule `phil_survey` needs `symfony/scheduler` and `symfony/messenger`, and runs with
`bin/console messenger:consume scheduler_phil_survey`; `symfony/lock` keeps it to one worker.
