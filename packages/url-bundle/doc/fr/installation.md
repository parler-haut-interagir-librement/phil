<!-- phil-dev source-sha256=adc179c487edbfb03d96f831e827d9930d5f24cf823ce73c6d06bfce1c0a5d82 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `phil/url`, `phil/enums` | ^0.1@dev, ^0.3@dev | Les types valeurs et les ensembles d'enums |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `TypedUrlType` |
| `symfony/validator` | ^8.2 | `UrlOfType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle |
| `doctrine/dbal` | facultatif | Le type de colonne `phil_url` |
| `twig/twig` | facultatif | Les filtres et la fonction Twig |

```sh
composer require phil/url-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\UrlBundle\PhilUrlBundle::class => ['all' => true],
];
```

Avec DoctrineBundle, le bundle enregistre le type de colonne `phil_url` ; avec Twig, son extension ; les messages sont
fournis en anglais et en français.

## Configuration

Une seule clé : `allow_localhost`, `false` par défaut. `localhost` et les noms sous `.localhost` nomment l'adresse de
bouclage, de sorte que le type de formulaire, la contrainte `UrlOfType`, le type de colonne et l'extension Twig les
refusent, comme le fait `phil/url`. Une application les permet en développement et dans ses tests seulement :

```yaml
# config/packages/phil_url.yaml
when@dev:
    phil_url:
        allow_localhost: true

when@test:
    phil_url:
        allow_localhost: true
```

Une fois permis, `http://localhost:8000/` et `https://app.localhost/` sont des adresses ; `http://127.0.0.1/`, tout
autre nom seul et un nom d'utilisateur restent refusés. La valeur est aussi le paramètre de conteneur
`phil_url.allow_localhost`.
