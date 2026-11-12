<!-- phil-dev source-sha256=ef197a01df183e2f06f44f8efde7c94b89c9e070c7c1a45a972ed12abb7d35a4 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `clone()` avec propriétés, l'analyseur `Dom\HTMLDocument` |
| `ext-dom`, `ext-libxml` | HTML analysé comme le fait un navigateur, par l'analyseur HTML de PHP, ses erreurs d'analyse étant laissées aux règles |
| `ext-mbstring` | Textes coupés et comparés par caractères, UTF-8 vérifié |
| `ext-uri` | Le nom de fichier d'une image lue à son adresse comme le fait un navigateur, par l'analyseur d'URL WHATWG de PHP |
| `symfony/translation` 8.2 ou plus récent | Les messages des constats en six langues |
| `phil/ujo`, facultatif | Documents UJO (Universal JSON Output) vérifiés bloc par bloc |
| `symfony/validator` 8.2 ou plus récent, facultatif | La contrainte `AccessibleContent` |
| `symfony/workflow` 8.2 ou plus récent, facultatif | La `PublicationGuard` |
| `symfony/console` 8.2 ou plus récent, facultatif | La commande `phil:accessibility:check` |

## Installer

```sh
composer require phil/accessibility-check
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez une seule fois un
`AccessibilityChecker` avec la politique de l'application, et conservez-le. Il ne garde aucun état entre deux
vérifications et s'utilise sans risque dans un *worker* qui sert de nombreuses requêtes.

```php
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\HeadingPolicy;
use Phil\AccessibilityCheck\Policy;

$checker = new AccessibilityChecker();                                            // the default policy and rules
$article = new AccessibilityChecker(new Policy(headings: HeadingPolicy::None));   // a content under its page title
```

## Dans une application Symfony

Déclarez la politique, les règles et le vérificateur comme services. Les règles sont étiquetées : une règle de
l'application les rejoint en implémentant `Contract\Rule`. L'ordre des services étiquetés est l'ordre d'exécution des
règles.

```yaml
services:
    _instanceof:
        Phil\AccessibilityCheck\Contract\Rule:
            tags: ['phil_accessibility_check.rule']

    Phil\AccessibilityCheck\Rule\:
        resource: '%kernel.project_dir%/vendor/phil/accessibility-check/src/Rule/*'
        exclude:
            - '%kernel.project_dir%/vendor/phil/accessibility-check/src/Rule/{DefaultRules,FileName,FragmentRule,GenericLinkText,Heading}.php'

    app.accessibility_policy:
        class: Phil\AccessibilityCheck\Policy
        arguments: { $headings: !php/enum Phil\AccessibilityCheck\HeadingPolicy::None, $locale: 'fr' }

    Phil\AccessibilityCheck\AccessibilityChecker:
        arguments:
            $policy: '@app.accessibility_policy'
            $rules: !tagged_iterator phil_accessibility_check.rule

    Phil\AccessibilityCheck\Validator\AccessibleContentValidator:
        arguments: ['@Phil\AccessibilityCheck\AccessibilityChecker', 'fr']
        tags: ['validator.constraint_validator']

    Phil\AccessibilityCheck\Console\CheckCommand:
        arguments: ['@Phil\AccessibilityCheck\AccessibilityChecker']
        tags: ['console.command']
```

La garde de publication se déclare avec le `SubjectContent` de l'application : voir [Garde de
publication](publication-guard.md).
