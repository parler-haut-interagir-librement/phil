<!-- phil-dev source-sha256=59dfc631935dce702be54005d02befdc745f3c4f50b8579492b3930da030ef1b -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring`, Symfony 8.2 avec FrameworkBundle, SecurityBundle, Forms et
TwigBundle, et Doctrine DBAL 4 par DoctrineBundle. Avec l'ORM, les tables rejoignent le schéma qu'il génère ; avec
Messenger, l'attribution passe par le bus ; avec Symfony UX Twig Component, les composants sont enregistrés ; avec
Console, la commande d'importation ; avec phil/privacy-bundle, l'export et l'effacement.

## Installer

```sh
composer require phil/shifts-bundle
```

La recette Flex enregistre le bundle, écrit `config/packages/phil_shifts.yaml` et importe les routes sous `/shifts`.
Sans Flex, enregistrez le bundle et importez les routes vous-même :

```php
// config/bundles.php
return [
    // ...
    Phil\ShiftsBundle\PhilShiftsBundle::class => ['all' => true],
];
```

```yaml
# config/routes/phil_shifts.yaml
phil_shifts:
    resource: '@PhilShiftsBundle/config/routes.php'
    prefix: /shifts
```

## Security et CSRF

Les pages exigent un pare-feu qui connaît la personne connectée, ainsi que la protection CSRF de Symfony et Forms :

```yaml
# config/packages/framework.yaml
framework:
    csrf_protection: true
    form: true
```

Le conteneur refuse de se construire quand les pages sont activées sans eux. Les coordonnateurs détiennent
`ROLE_SHIFTS_COORDINATOR`, ou le rôle que nomme la configuration ; la hiérarchie des rôles de l'application compte.

## Les tables

Avec l'ORM, le bundle ajoute ses tables au schéma qu'il génère :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez-les avec `ShiftsTables::configure()`, décrit dans [Stockage et vie privée](storage-and-privacy.md).

## Un premier quart

Un coordonnateur ouvre `/shifts/manage/new`, ou importe un plan :

```sh
php bin/console phil:shifts:import plan.json
```

```json
{"format": "phil-shifts/1", "shifts": [{"id": "desk-sat-am", "title": "Info desk", "kind": "fixed",
  "starts_at": "2026-11-28T09:00:00-05:00", "ends_at": "2026-11-28T12:00:00-05:00", "capacity": 2, "reserve": 3}]}
```

Les bénévoles le trouvent à `/shifts/`, ou sur n'importe quelle page grâce au composant :

```twig
<twig:PhilShifts:ShiftCard id="desk-sat-am" />
```
