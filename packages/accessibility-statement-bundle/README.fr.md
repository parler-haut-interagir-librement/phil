<!-- phil-dev source-sha256=b4254918867f5cca253b4527784fa0b770b2516bf188e623c682903b6b22ff4c -->
# phil/accessibility-statement-bundle

**La déclaration d'accessibilité d'un site Symfony : une page en anglais et en français, ses versions, et une commande
qui la garde fidèle au dernier audit.**

phil/accessibility-statement-bundle publie la déclaration d'accessibilité d'une application à `/accessibility` et à
`/accessibilite`. Elle contient l'engagement, l'état de conformité à une version des WCAG comme WCAG 2.2 AA, le contenu
non accessible regroupé par motif avec les solutions de rechange offertes et les dates de correction, les moyens de
rétroaction, la procédure de recours, la façon dont la déclaration a été préparée, et ses versions, chacune à une
adresse qui ne change jamais. Le libellé suit les WCAG seules, la norme européenne EN 301 549 ou la Loi canadienne sur
l'accessibilité.

Le bundle s'adresse à tout site Symfony qui doit une déclaration à ses lecteurs, ou à son organisme de réglementation.
Écrivez la première version dans la configuration, liez-la depuis le pied de page avec
`{{ phil_accessibility_statement_link() }}`, puis laissez `phil:accessibility-statement:generate` écrire la version
suivante à partir des rapports de `phil/accessibility-check`. La commande garde ce que les gens ont écrit de chaque
critère. Les versions restent dans la configuration, ou dans une table avec Doctrine.

La déclaration elle-même est `phil/accessibility-statement`, une bibliothèque PHP simple. Le bundle ajoute la page, les
stockages, la commande, une page d'administration qui publie la version suivante, et les fonctions Twig. Les gabarits
dessinent leurs parties avec des recettes du kit PHIL UX, ou du kit Shadcn, installées par le Symfony UX Toolkit. Le
moyen de rétroaction peut être un formulaire de `phil/forms-bundle` ou le formulaire de contact de
`phil/contact-form-bundle`.

## État

| Partie | État |
|---|---|
| La déclaration de la configuration, vérifiée à la construction du conteneur | Implémenté et testé |
| Les versions dans la table `phil_accessibility_statement`, la configuration comme version | Implémenté et testé |
| La page à `/accessibility` et à `/accessibilite`, chaque version à sa propre adresse, mise en cache | Implémenté et testé |
| L'anglais et le français, les libellés des WCAG, de la EN 301 549 et de la Loi canadienne sur l'accessibilité | Implémenté et testé |
| Le lien de pied de page, le chemin et le filtre de texte de Twig | Implémenté et testé |
| La déclaration parmi les liens juridiques de `phil/legal-notices-bundle` | Implémenté et testé |
| L'audit lu dans un répertoire de rapports JSON, ou dans un `AuditSource` de l'application | Implémenté et testé |
| `phil:accessibility-statement:generate` | Implémenté et testé |
| Les gabarits dessinés avec les recettes du kit PHIL UX ou du kit Shadcn, chacun rendu avec les deux dans les tests ; le formulaire de rétroaction de `phil/forms-bundle` | Implémenté et testé |
| La page d'administration qui publie la version suivante, derrière un rôle et un jeton CSRF | Implémenté et testé |
| Les moments de publication gardés en UTC | Implémenté et testé |
| La recette Flex : le bundle, une configuration qui ne publie rien tant que la déclaration n'est pas écrite, et la route | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, la recette, l'enregistrement, la première version, les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, une déclaration complète, les services. |
| [La page](doc/fr/page.md) | Les routes, ce que montre la page, les langues, le libellé, les gabarits, le lien de pied de page. |
| [Doctrine](doc/fr/doctrine.md) | La table, ses versions et la version de la configuration. |
| [Administration](doc/fr/administration.md) | La page qui publie la version suivante, qui l'ouvre, ce qu'elle refuse. |
| [Console](doc/fr/console.md) | La commande, ses codes de sortie et le répertoire d'audit. |
| [Points d'extension](doc/fr/extension-points.md) | Le dernier audit, les versions, la régénération, la page. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/accessibility-statement-bundle
```

```yaml
# config/packages/phil_accessibility_statement.yaml
phil_accessibility_statement:
    feedback_route: phil_contact_form
    statement:
        version: 1
        published_at: '2026-11-08'
        entity: Example Org
        feedback: { email: accessibility@example.com, response_days: 10 }
    audit: { enabled: true }

# config/routes/phil_accessibility_statement.yaml
phil_accessibility_statement:
    resource: '@PhilAccessibilityStatementBundle/config/routes.php'
```

```sh
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit   # and badge, table, button
```

```twig
<footer>{{ phil_accessibility_statement_link() }}</footer>
```

```sh
bin/console phil:accessibility:check templates/pages/home.html --format=json > var/accessibility/home.json
bin/console phil:accessibility-statement:generate --format=json   # the next version, to write in the configuration
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent avec FrameworkBundle, TwigBundle et `symfony/ux-twig-component`,
`phil/accessibility-statement` et `phil/accessibility-check`, et les recettes du kit. Ajoutez `symfony/translation` pour
les langues, `doctrine/orm` pour la table, `symfony/console` pour la commande, Symfony Security et
`symfony/security-csrf` pour la page d'administration.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
