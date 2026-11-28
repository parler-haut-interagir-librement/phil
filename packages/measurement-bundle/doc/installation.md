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

The bundle needs PHP 8.5 or later with the `intl` extension, Symfony 8.2 or later, and `phil/measurement`, which
Composer installs with it. Without a configuration, it registers the units the library declares and no channel.

The form type and the constraints need `symfony/form` and `symfony/validator`, which the bundle requires. The Twig
filters are registered when `twig/twig` is installed, and the column type when `doctrine/dbal` is.
