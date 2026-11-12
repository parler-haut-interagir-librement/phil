# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `clone()` with properties, the `Dom\HTMLDocument` parser |
| `ext-dom`, `ext-libxml` | HTML parsed as a browser parses it, by the HTML parser of PHP, its parse errors left to the rules |
| `ext-mbstring` | Texts cut and compared on characters, UTF-8 checked |
| `ext-uri` | The file name of an image read from its address as a browser reads it, by the WHATWG URL parser of PHP |
| `symfony/translation` 8.2 or later | The messages of the findings in six languages |
| `phil/ujo`, optional | UJO (Universal JSON Output) documents checked block by block |
| `symfony/validator` 8.2 or later, optional | The `AccessibleContent` constraint |
| `symfony/workflow` 8.2 or later, optional | The `PublicationGuard` |
| `symfony/console` 8.2 or later, optional | The `phil:accessibility:check` command |

## Installing

```sh
composer require phil/accessibility-check
```

It is a library, not a bundle: there is nothing to register. Build an `AccessibilityChecker` with the application's
policy, once, and keep it; it holds no state between two checks, and is safe in a worker that serves many requests.

```php
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\HeadingPolicy;
use Phil\AccessibilityCheck\Policy;

$checker = new AccessibilityChecker();                                            // the default policy and rules
$article = new AccessibilityChecker(new Policy(headings: HeadingPolicy::None));   // a content under its page title
```

## In a Symfony application

Declare the policy, the rules and the checker as services. The rules are tagged, so that a rule of the application
joins them by implementing `Contract\Rule`; the order of the tagged services is the order the rules run in.

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

The publication guard is declared with the application's `SubjectContent`: see
[Publication guard](publication-guard.md).
