<!-- phil-dev source-sha256=0c4c677a67118a9c488827ed87bc7b9df378778037be69ba4999d6a344457e99 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `clone()` avec propriétés, `array_all()` |
| `ext-mbstring` | La source rendue en UTF-8 valide, les échappements transformés en caractères, les sujets des retraits coupés |
| `symfony/validator` 8.2 ou plus récent, facultatif | La contrainte `SafeCss` |

## Installer

```sh
composer require phil/css-sanitizer
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez un `CssSanitizer` avec la politique
de l'application, une seule fois, et gardez-le. Il ne garde aucun état entre deux appels.

```php
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\Policy;

$sanitizer = new CssSanitizer();                                     // the default policy, every URL refused
$print = new CssSanitizer(new Policy()->withProperties('position'));  // a policy of its own
```

Dans une application Symfony, déclarez la politique et l'assainisseur comme services, avec le validateur, pour que la
contrainte `SafeCss` utilise la même politique :

```yaml
services:
    app.css_policy:
        class: Phil\CssSanitizer\Policy
        factory: ['@App\Css\PolicyFactory', 'create']

    Phil\CssSanitizer\CssSanitizer:
        arguments: ['@app.css_policy']

    Phil\CssSanitizer\Validator\SafeCssValidator:
        arguments: ['@Phil\CssSanitizer\CssSanitizer']
        tags: ['validator.constraint_validator']
```

Sans cette déclaration, le validateur est construit par la fabrique du composant Validator, avec la politique par
défaut.
