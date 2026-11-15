# Installation

## Requirements

- PHP 8.5 or later and Symfony 8.2 or later, with FrameworkBundle and TwigBundle.
- `symfony/translation`, for the page in English and French.
- `doctrine/orm` and DoctrineBundle, for the `doctrine` source.
- `symfony/ux-twig-component`, and the recipes of the PHIL UX kit the templates draw, installed by the Symfony UX
  Toolkit ([The page](page.md#the-recipes-of-the-kit)).
- `symfony/console`, for the command that regenerates the statement.
- Symfony Security with `symfony/security-csrf`, for the administration page ([Administration](administration.md)).

## Installing

```sh
composer require phil/accessibility-statement-bundle
```

The Flex recipe of the bundle registers it, writes `config/packages/phil_accessibility_statement.yaml` with the first
version commented out, and imports the page in `config/routes/phil_accessibility_statement.yaml`. Until a statement is
written there, none is published: the page answers 404 and the footer does not link to it, so that the site claims
nothing no one has evaluated. The recipe lives in the recipes repository of PHIL, read once the application lists that
repository in `extra.symfony.endpoint`.

Register the bundle, if Flex did not:

```php
// config/bundles.php
return [
    // …
    Phil\AccessibilityStatementBundle\PhilAccessibilityStatementBundle::class => ['all' => true],
];
```

Write the first version of the statement in the configuration ([Configuration](configuration.md)):

```yaml
# config/packages/phil_accessibility_statement.yaml
phil_accessibility_statement:
    statement:
        version: 1
        published_at: '2026-11-08'
        entity: Example Org
        scope: { en: the site example.com, fr: le site example.com }
        feedback: { email: accessibility@example.com, response_days: 10 }
        evaluation: { evaluated_at: '2026-11-01', method: self-assessment }
```

Import the routes of the page:

```yaml
# config/routes/phil_accessibility_statement.yaml
phil_accessibility_statement:
    resource: '@PhilAccessibilityStatementBundle/config/routes.php'
```

Install the recipes of the kit the page draws:

```sh
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

A site on Tailwind installs the same recipes from the Shadcn kit of the Toolkit instead, with `label` and `separator`
([The page](page.md#the-recipes-of-the-kit)).

The page is then served at `/accessibility` and `/accessibilite` ([The page](page.md)). Link it from the footer of the
site with `{{ phil_accessibility_statement_link() }}`.
