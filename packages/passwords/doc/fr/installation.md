<!-- phil-dev source-sha256=1fbad882729d1546cdc00641365903e7115736d71271a00757a17ebcfccf85f3 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `clone()` avec des propriétés, `Random\Randomizer` |
| `ext-mbstring` | Les mots de passe sont lus par caractères, pas par octets |
| `ext-ctype` | Les chiffres des dates et des réponses de la source des fuites |
| `symfony/http-client-contracts` 3.6 et un client HTTP, facultatifs | `PwnedPasswordsSource` |
| `symfony/validator` 8.2 ou plus récent, facultatif | Les contraintes `MinimumStrength` et `NotCompromised` |

## Installer

```sh
composer require phil/passwords
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez l'estimateur, le vérificateur et les
générateurs une seule fois et gardez-les. Aucun ne garde d'état entre deux appels : ils conviennent donc à un *worker*
qui sert de nombreuses requêtes.

```php
use Phil\Passwords\Breach\Adapter\PwnedPasswordsSource;
use Phil\Passwords\Breach\BreachChecker;
use Phil\Passwords\Strength\StrengthEstimator;
use Symfony\Component\HttpClient\HttpClient;

$estimator = new StrengthEstimator();                       // the bundled dictionaries and layouts
$checker = new BreachChecker(new PwnedPasswordsSource(HttpClient::create()));
```

Construire l'estimateur indexe ses dictionnaires, ce qui prend quelques millisecondes : construisez-le une fois par
processus, pas une fois par appel.

## Dans une application Symfony

Déclarez les services, les validateurs qui les utilisent et les traductions de la bibliothèque :

```yaml
services:
    Phil\Passwords\Strength\StrengthEstimator: ~

    Phil\Passwords\Breach\Adapter\PwnedPasswordsSource:
        arguments: ['@http_client']

    Phil\Passwords\Breach\BreachChecker:
        arguments: ['@Phil\Passwords\Breach\Adapter\PwnedPasswordsSource']

    Phil\Passwords\Validator\MinimumStrengthValidator:
        arguments: ['@Phil\Passwords\Strength\StrengthEstimator']
        tags: ['validator.constraint_validator']

    Phil\Passwords\Validator\NotCompromisedValidator:
        arguments: ['@Phil\Passwords\Breach\BreachChecker']
        tags: ['validator.constraint_validator']
```

```yaml
framework:
    translator:
        paths:
            - '%kernel.project_dir%/vendor/phil/passwords/translations'
```

Les fichiers de traduction sont des tableaux PHP : `phil_passwords.<locale>.php` pour la rétroaction,
`validators.<locale>.php` pour les contraintes.
