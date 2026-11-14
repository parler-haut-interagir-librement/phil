<!-- phil-dev source-sha256=382988e002b9476e47006d631f4c719fd64e57af52d3eb6257ae556e002402f0 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent avec FrameworkBundle (ses formulaires, leur protection CSRF et le
traducteur), TwigBundle et Doctrine DBAL. Facultatifs, chacun utilisé quand il est installé :

| Paquet | Pour |
|---|---|
| `symfony/security-bundle` | Nommer l'utilisateur courant et vérifier les rôles des réglages du site |
| `doctrine/orm` | Ajouter les tables au schéma que produit l'ORM |
| `phil/notifications-bundle` | Fournir les préférences de ses destinataires à partir des préférences |
| `phil/privacy-bundle` | Exporter et effacer les réglages d'une personne avec son compte |
| `phil/ux-css` | Habiller les pages et appliquer les préférences d'affichage |
| `symfony/stimulus-bundle`, `symfony/asset-mapper` | Appliquer une préférence d'affichage dès qu'elle est choisie |

## Activer le bundle

```sh
composer require phil/settings-bundle
```

```php
// config/bundles.php
Phil\SettingsBundle\PhilSettingsBundle::class => ['all' => true],
```

## La recette

La recette Flex du bundle l'enregistre dans `config/bundles.php`, écrit `config/packages/phil_settings.yaml`, qui donne les
réglages du site à `ROLE_ADMIN`, et `config/routes/phil_settings.yaml`, qui importe les pages des préférences et du site
comme ci-dessous. La recette se trouve dans le dépôt de recettes de PHIL, lu une fois que l'application inscrit ce dépôt
dans `extra.symfony.endpoint` ; sans cela, enregistrez le bundle et importez les routes à la main.

## Les tables

Les valeurs sont conservées dans `phil_settings`, et les versions des migrations des réglages dans
`phil_settings_migrations`, que le bundle ajoute au schéma que l'ORM produit pour sa connexion :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez-les à partir de `Infrastructure\Doctrine\SettingTables::configure()` ; voir [Stockage](storage.md).

À chaque déploiement, après les migrations de la base de données, appliquez les migrations des valeurs conservées ; voir
[Migrations](migrations.md) :

```sh
php bin/console phil:settings:migrate
```

## Les pages

Importez les préférences, que les visiteurs atteignent aussi, et les réglages du site, derrière le pare-feu de
l'administration :

```yaml
# config/routes/phil_settings.yaml
phil_settings:
    resource: '@PhilSettingsBundle/config/routes.php'
    prefix: /preferences

phil_settings_site:
    resource: '@PhilSettingsBundle/config/routes/site.php'
    prefix: /admin/settings
```

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
```

## L'élément racine de la page

Écrivez les préférences d'affichage sur l'élément racine de la mise en page, pour que la feuille de style les
applique :

```twig
<html lang="{{ app.request.locale }}" {{ phil_document_attributes() }}>
```

Voir [Préférences d'affichage](display.md).
