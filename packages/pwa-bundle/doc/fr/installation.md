<!-- phil-dev source-sha256=3cd99ce01614b30a258912703ced8e55d2f020d036a1002d5ab252d52acea7d6 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-mbstring` et `ext-uri`.
- Symfony 8.2 ou une version plus récente : FrameworkBundle, TwigBundle et le composant Routing ; Twig 3.21 ou une
  version plus récente.
- AssetMapper, qui sert le service worker et les contrôleurs, et versionne les ressources préchargées.
- Le bundle Stimulus, pour les contrôleurs.
- Une origine HTTPS, ou `localhost` en développement : les navigateurs n'enregistrent de service workers nulle part
  ailleurs.

## Installer

```sh
composer require phil/pwa-bundle
```

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_pwa.yaml` avec un manifeste à compléter
et importe les routes dans `config/routes/phil_pwa.yaml`. Sans Flex :

```php
// config/bundles.php
Phil\PwaBundle\PhilPwaBundle::class => ['all' => true],
```

```yaml
# config/routes/phil_pwa.yaml
phil_pwa:
    resource: '@PhilPwaBundle/config/routes.php'
```

Importez les routes **sans préfixe** : un worker servi à `/sw.js` contrôle tout le site. Servi sous un préfixe, il
répond avec `Service-Worker-Allowed` pour que sa portée puisse rester celle qui est configurée
([Le service worker](service-worker.md#headers-scope-and-csp)).

## La mise en page

```twig
<head>
    {{ phil_pwa_head() }}
</head>
<body {{ stimulus_controller('phil--pwa-bundle--pwa', {url: phil_pwa_service_worker_path(), scope: phil_pwa_scope()}) }}>
```

`phil_pwa_head()` écrit le `<link rel="manifest">` et, avec un `theme_color`, le `<meta name="theme-color">`. Le
contrôleur enregistre le worker ([JavaScript](javascript.md)). Avec AssetMapper, le bundle ajoute son répertoire
`assets/src` sous l'espace de noms `@phil/pwa-bundle` ; le bundle Stimulus trouve les contrôleurs à partir de
`assets/package.json` une fois que `assets/controllers.json` de l'application les liste, comme le font
`importmap:require` et la recette d'un paquet Symfony UX.
