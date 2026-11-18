<!-- phil-dev source-sha256=1c18e477fc07e62bc34b732fe52998e5204467596b9ae71744b193ebd05b658e -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `phil/url`, `phil/enums` | ^0.1@dev, ^0.3@dev | Les types valeurs et les ensembles d'enums |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `TypedUrlType` |
| `symfony/validator` | ^8.2 | `UrlOfType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle |
| `doctrine/dbal`, `doctrine/doctrine-bundle` | facultatif | Le type de colonne `phil_url`, rien d'autre |
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

Le type de formulaire, la contrainte, l'extension Twig et les messages n'ont pas besoin de base de données : une
application sans Doctrine installe le bundle seul. Avec `doctrine/dbal` et DoctrineBundle, le bundle enregistre aussi le
type de colonne `phil_url` ; avec Twig, son extension ; les messages sont fournis en anglais et en français.

## Configuration

| Clé | Défaut | Exige |
|---|---|---|
| `allow_localhost` | `false` | Rien |
| `column_type` | `null` | `doctrine/dbal` et DoctrineBundle quand elle vaut `true` |

### `allow_localhost`

`allow_localhost`, `false` par défaut. `localhost` et les noms sous `.localhost` nomment l'adresse de
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

### `column_type`

La seule fonctionnalité qui a besoin de `doctrine/dbal` est le type de colonne `phil_url`. La clé `column_type` dit si le
bundle l'enregistre auprès de DoctrineBundle :

| Valeur | Le type de colonne |
|---|---|
| `null` (par défaut) | Enregistré quand `doctrine/dbal` et DoctrineBundle sont installés, absent sinon |
| `true` | Exigé : sans `doctrine/dbal`, ou sans DoctrineBundle, le conteneur ne se construit pas et le message nomme le paquet à installer |
| `false` | Jamais enregistré, pour une application qui mappe les adresses à sa façon |

```yaml
# config/packages/phil_url.yaml
phil_url:
    column_type: true # the entities map phil_url columns: fail at build rather than at the first query
```

La clé est lue avant le chargement des extensions, puisque DoctrineBundle apprend l'existence du type de colonne par une
configuration préfixée : elle accepte `true`, `false` ou `null`, pas une variable d'environnement.
