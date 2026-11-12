<!-- phil-dev source-sha256=c0fdb9e8ddf434ceb9030f0f39eea06344569cda1c582f5166ac040086e1427d -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `new` sans parenthèses |
| `phil/translation-memory` | Segments, segmentation, mémoire, glossaire et XLIFF |
| `phil/ujo` | Les documents traduits |
| `doctrine/orm` 3.7 | Les tables des segments, de la mémoire, des termes et des commentaires |
| `twig/twig` 3.21 | L'écran de traduction |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` 8.2 | Le contrôleur et les routes de l'écran |
| `symfony/security-csrf` 8.2 | La vérification CSRF sans état des formulaires de l'écran |
| `symfony/stimulus-bundle`, `symfony/asset-mapper`, facultatifs | Le contrôleur `csrf` de l'écran, sans étape de construction |
| `easycorp/easyadmin-bundle`, facultatif | Les liens du menu vers les pages de glossaire dans une interface d'administration EasyAdmin |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La configuration et les services |
| `psr/clock` | Les moments où les lignes sont écrites |

L'application fournit les services `clock` (le `ClockInterface` de Symfony), `twig` et `router`, qu'enregistre le
cadriciel complet, ainsi que DoctrineBundle pour le gestionnaire d'entités.

## Installer

```sh
composer require phil/translation-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\TranslationBundle\PhilTranslationBundle::class => ['all' => true],
];
```

## Les tables

Le bundle associe lui-même ses lignes pour Doctrine : aucune entrée `mappings` à écrire. Créez les tables avec les
migrations de l'application :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

[Stockage](storage.md) décrit les quatre tables.

## L'écran

Importez les routes derrière le pare-feu de l'application, sous le préfixe de votre choix :

```yaml
# config/routes/phil_translation.yaml
phil_translation:
    resource: '@PhilTranslationBundle/config/routes.php'
    prefix: /admin/translate
```

Nommez ensuite les documents de l'application et qui peut les traduire : [l'écran de traduction](screen.md) montre ces
deux services. Sans eux, l'écran et les pages de glossaire répondent 404 ; les services de `Translations` fonctionnent
tout de même. Un service `Translators` signe les cibles et les commentaires et crédite les traducteurs en public
([Commentaires et crédits](collaboration.md)) ; la traduction automatique reste désactivée tant qu'aucun traducteur
n'est nommé ([Traduction automatique](machine-translation.md)).
