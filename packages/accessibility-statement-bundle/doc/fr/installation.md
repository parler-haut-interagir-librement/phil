<!-- phil-dev source-sha256=fc71c4f886e4872307e8a6ee1575b05f09decd8464867730e859463954c214af -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent et Symfony 8.2 ou plus récent, avec FrameworkBundle et TwigBundle.
- `symfony/translation`, pour la page en anglais et en français.
- `doctrine/orm` et DoctrineBundle, pour la source `doctrine`.
- `symfony/ux-twig-component`, et les recettes du kit PHIL UX que dessinent les gabarits, installées par le Symfony UX
  Toolkit ([La page](page.md#les-recettes-du-kit)).
- `symfony/console`, pour la commande qui régénère la déclaration.
- Symfony Security avec `symfony/security-csrf`, pour la page d'administration ([Administration](administration.md)).

## Installer

```sh
composer require phil/accessibility-statement-bundle
```

La recette Flex du bundle l'enregistre, écrit `config/packages/phil_accessibility_statement.yaml` avec la première
version en commentaire, et importe la page dans `config/routes/phil_accessibility_statement.yaml`. Tant qu'une
déclaration n'y est pas écrite, aucune n'est publiée : la page répond 404 et le pied de page ne la lie pas, de sorte que
le site n'affirme rien que personne n'a évalué. La recette se trouve dans le dépôt de recettes de PHIL, lu dès que
l'application liste ce dépôt dans `extra.symfony.endpoint`.

Enregistrez le bundle, si Flex ne l'a pas fait :

```php
// config/bundles.php
return [
    // …
    Phil\AccessibilityStatementBundle\PhilAccessibilityStatementBundle::class => ['all' => true],
];
```

Écrivez la première version de la déclaration dans la configuration ([Configuration](configuration.md)) :

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

Importez les routes de la page :

```yaml
# config/routes/phil_accessibility_statement.yaml
phil_accessibility_statement:
    resource: '@PhilAccessibilityStatementBundle/config/routes.php'
```

Installez les recettes du kit que la page dessine :

```sh
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Un site sous Tailwind installe plutôt les mêmes recettes depuis le kit Shadcn du Toolkit, avec `label` et `separator`
([La page](page.md#les-recettes-du-kit)).

La page est alors servie à `/accessibility` et à `/accessibilite` ([La page](page.md)). Liez-la depuis le pied de page
du site avec `{{ phil_accessibility_statement_link() }}`.
