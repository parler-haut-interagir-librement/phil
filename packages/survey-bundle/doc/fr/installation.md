<!-- phil-dev source-sha256=69d37493379bd1f1adf30b34e3c68ffce95d3dc20fc88d7f5a0a9b92a170c279 -->
# Installation

```sh
composer require phil/survey-bundle
```

Enregistrez ensuite le bundle dans `config/bundles.php` :

```php
Phil\SurveyBundle\PhilSurveyBundle::class => ['all' => true],
```

Le paquet exige PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente.

## Les tables

Avec l'ORM de Doctrine, le bundle ajoute ses tables au schéma que génère l'ORM : `doctrine:migrations:diff` écrit donc
leur migration. Sans l'ORM, `Infrastructure\Doctrine\SurveyTables::configure()` les ajoute à un `Schema` de DBAL. Les
tables ont le préfixe `phil_survey_`, sur la connexion `phil_survey.connection`.

## Les pages

Activez la protection CSRF de Symfony, dont les formulaires ont besoin, et importez les routes :

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

Les gabarits emploient la fonction `html_attr` de `twig/html-extra` : installez `twig/extra-bundle` pour l'enregistrer.
Avec AssetMapper et Symfony UX, le contrôleur Stimulus `progress` de `assets/` est enregistré par Symfony Flex ; les
pages fonctionnent sans lui.

## La purge

Le calendrier `phil_survey` exige `symfony/scheduler` et `symfony/messenger`, et s'exécute avec
`bin/console messenger:consume scheduler_phil_survey` ; `symfony/lock` le limite à un seul *worker*.
