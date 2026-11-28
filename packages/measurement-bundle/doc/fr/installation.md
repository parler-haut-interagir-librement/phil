<!-- phil-dev source-sha256=be3cd7ff17dd93ae687b55d8c7ac230e575cd874dd3410f77c23fcc41e08a371 -->
# Installation

```sh
composer require phil/measurement-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\MeasurementBundle\PhilMeasurementBundle::class => ['all' => true],
];
```

Le bundle exige PHP 8.5 ou une version plus récente avec l'extension `intl`, Symfony 8.2 ou une version plus récente,
et `phil/measurement`, que Composer installe avec lui. Sans configuration, il enregistre les unités que déclare la
bibliothèque et aucun canal.

Le type de formulaire et les contraintes ont besoin de `symfony/form` et de `symfony/validator`, que le bundle exige.
Les filtres Twig sont enregistrés quand `twig/twig` est installé, et le type de colonne quand `doctrine/dbal` l'est.
