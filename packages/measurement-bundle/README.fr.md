<!-- phil-dev source-sha256=e3976cbb4bc953e144868fa4434e849edffbd3f74b295e1d087b8be0d001a294 -->
# phil/measurement-bundle

**Poids et dimensions dans les formulaires, la validation, Twig et Doctrine de Symfony, sous forme de nombres décimaux
exacts avec leurs unités, par canal de vente, écrits dans chaque locale.**

phil/measurement-bundle intègre `phil/measurement` à Symfony : le registre des unités avec celles de l'application,
l'unité de chaque canal de vente, un type de formulaire qui lit une valeur et une unité dans la locale de la requête,
des contraintes qui bornent une mesure dans n'importe quelle unité de sa famille, des filtres Twig qui l'écrivent dans
la locale ou dans l'unité qu'une région préfère, et un type de colonne Doctrine qui la stocke sans nombre à virgule
flottante. La bibliothèque seule convient à une application sans Symfony.

```php
#[ORM\Column(type: 'phil_measurement')]
#[MeasurementRange(min: '0 kg', max: '30 kg')]
#[MeasurementUnit(family: 'mass')]
public ?Measurement $weight = null;

$builder->add('weight', MeasurementType::class, ['family' => 'mass', 'channel' => 'storefront']);
```

```twig
{{ product.weight|format_measurement('long') }}                     {# 12,5 kilogrammes #}
{{ person.height|preferred_units('person-height')|format_measurement }}
```

## État

| Partie | État |
|---|---|
| `PhilMeasurementBundle` et sa configuration `phil_measurement` : unités ajoutées, unité de chaque canal | Implémenté et testé |
| `MeasurementType`, le type de formulaire d'une valeur et d'une unité | Implémenté et testé |
| `MeasurementRange` et `MeasurementUnit`, les contraintes | Implémenté et testé |
| `format_measurement`, `measurement_in_channel` et `preferred_units`, les filtres Twig | Implémenté et testé |
| `phil_measurement`, le type de colonne Doctrine | Implémenté et testé sur SQLite ; les tests pour MySQL et PostgreSQL s'exécutent quand leurs serveurs sont fournis |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Formulaires](doc/fr/forms.md) | `MeasurementType`, ses options et la façon dont il lit une valeur. |
| [Validation](doc/fr/validation.md) | `MeasurementRange` et `MeasurementUnit`. |
| [Twig](doc/fr/twig.md) | Les filtres qui écrivent et convertissent une mesure. |
| [Doctrine](doc/fr/doctrine.md) | Le type de colonne `phil_measurement`, et deux colonnes à trier ou à additionner en SQL. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les étiquettes à compléter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et les serveurs que lisent ses tests d'intégration. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\MeasurementBundle\PhilMeasurementBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_measurement.yaml
phil_measurement:
    channels:
        storefront: { mass: kg, length: cm }
        marketplace: { mass: '[lb_av]', length: '[in_i]' }
```

## Prérequis

PHP 8.5 ou une version plus récente avec l'extension `intl`, et Symfony 8.2 ou une version plus récente. Doctrine
DBAL 4 pour le type de colonne, Twig 3 pour les filtres.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
