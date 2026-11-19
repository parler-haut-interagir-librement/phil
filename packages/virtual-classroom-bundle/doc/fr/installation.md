<!-- phil-dev source-sha256=c64453466b5f65cfcd6c19c612dccc01dc7093944da8cd5beb961132bb249373 -->
# Installation

```sh
composer require phil/virtual-classroom-bundle
```

Avec Symfony Flex, la recette active le bundle, écrit `config/packages/phil_virtual_classroom.yaml` avec un serveur
BigBlueButton lu dans `BBB_URL` et `BBB_SECRET`, et `config/routes/phil_virtual_classroom.yaml`, qui importe les routes
sous `/virtual-classroom`. En développement, `allow_localhost` est activé ; dans les tests, les salles sont gardées en
mémoire.

Sans Flex, activez le bundle et importez ses routes :

```php
// config/bundles.php
Phil\VirtualClassroomBundle\PhilVirtualClassroomBundle::class => ['all' => true],
```

```yaml
# config/routes/phil_virtual_classroom.yaml
phil_virtual_classroom:
    resource: '@PhilVirtualClassroomBundle/config/routes.php'
    prefix: /virtual-classroom
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Doctrine DBAL 4.5 ou une version plus
récente par DoctrineBundle, et le client HTTP de Symfony. La console, Messenger, Security, phil/courses-bundle et
phil/privacy-bundle sont facultatifs : chaque partie est enregistrée quand son paquet est installé et, pour les deux
bundles PHIL, activé.

## Les tables

Le bundle garde trois tables, `phil_virtual_classroom_room`, `_stay` et `_recording`. Avec l'ORM,
`ClassroomSchemaListener` les ajoute au schéma qu'il génère, et `doctrine:migrations:diff` écrit la migration. Sans
l'ORM, ajoutez-les dans une migration :

```php
$schema = ClassroomTables::for($connection->getDatabasePlatform())->configure($schema);
```

## Le webhook

Les serveurs rappellent l'application : les routes du webhook doivent leur être accessibles, en https, sous l'hôte
public de l'application. Réglez l'URI par défaut du routeur, `framework.router.default_uri`, pour que les adresses
écrites depuis la console ou un *worker* nomment cet hôte.
