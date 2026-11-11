<!-- phil-dev source-sha256=45ecfd5c345a6f8bdf4ec82ee2c3777604184589e42d029f2853303637589bb8 -->
# Installation

## Prérequis

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le bundle déclare et sur lequel il est testé |
| `phil/ujo`, `phil/ujo-bundle` | ^0.1 | Le modèle, les vérifications, ainsi que le service `Ujo` et le registre de types de blocs qu'utilise le champ |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La classe du bundle et sa configuration |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `BlockEditorType` |
| `mbstring` de PHP | — | Le décompte des caractères des libellés et des requêtes de recherche |
| `symfony/http-foundation` | ^8.2 | Les points de terminaison de téléversement et de recherche, la locale de la requête |
| `symfony/security-core` | ^8.2 | Le contrôle d'accès des points de terminaison, par le vérificateur d'autorisations de l'application |
| `symfony/security-csrf` | ^8.2 | La vérification CSRF sans état des points de terminaison |
| `symfony/routing` | ^8.2, facultatif | Les routes des points de terminaison et leurs URL transmises à l'éditeur |
| `symfony/twig-bundle` | ^8.2, facultatif | Le thème de formulaire |
| `symfony/stimulus-bundle`, `symfony/asset-mapper` | facultatifs | Servir le contrôleur et sa feuille de style sans étape de construction |
| Stimulus | 3, dans le navigateur | La classe de base du contrôleur |

## Installer

```sh
composer require phil/block-editor-bundle
```

Sans Symfony Flex, activez les deux bundles dans `config/bundles.php` :

```php
return [
    // …
    Phil\UjoBundle\PhilUjoBundle::class => ['all' => true],
    Phil\BlockEditorBundle\PhilBlockEditorBundle::class => ['all' => true],
];
```

Le bundle ajoute son thème de formulaire à `form_themes` de Twig : `form_row()` dessine donc l'éditeur sans autre étape.

## Le contrôleur et la feuille de style

Avec AssetMapper, le bundle associe `assets/src` à `@phil/block-editor-bundle` et `assets/styles` à `phil-block-editor`.
Symfony Flex enregistre le contrôleur dans `assets/controllers.json` à partir du `assets/package.json` du paquet. Sans
Flex, ajoutez-le à la main :

```json
{
    "controllers": {
        "@phil/block-editor-bundle": {
            "editor": { "enabled": true, "fetch": "eager" }
        }
    },
    "entrypoints": []
}
```

Liez la feuille de style dans la mise en page des pages d'édition :

```twig
<link rel="stylesheet" href="{{ asset('phil-block-editor/block-editor.css') }}">
```

Le contrôleur n'importe que `@hotwired/stimulus`, que l'importmap de l'application contient déjà. Avec une autre chaîne
de traitement, importez `assets/src/editor_controller.js` et enregistrez-le sous le nom
`phil--block-editor-bundle--editor`.

## Les routes

Les téléversements d'images et les recherches `@` et `[[` passent par trois routes. Importez-les sous un préfixe,
dans le pare-feu qui protège les formulaires :

```yaml
# config/routes/phil_block_editor.yaml
phil_block_editor:
    resource: '@PhilBlockEditorBundle/config/routes.php'
    prefix: /_block-editor
```

Une route ne répond que si son service est configuré ([configuration.md](configuration.md)) ; le champ donne à l'éditeur
l'URL de chaque route qui répond. Le bundle ajoute le jeton `phil_block_editor` à `csrf_protection.stateless_token_ids`
de FrameworkBundle, ce qui active `framework.csrf_protection` : laissez-le actif
([integrations.md](integrations.md#protéger-les-points-de-terminaison)). Avec `csrf_protection: false`, le conteneur
s'arrête sur une `LogicException` qui le dit. Sans les routes, l'éditeur n'accepte que des adresses d'images et laisse
`@` et `[[` à l'état de texte.

Un pare-feu seul n'exige de personne qu'il se connecte : un pare-feu paresseux, ou qui laisse passer les visiteurs
anonymes, ouvre les routes à tout le monde. Chaque point de terminaison demande donc au vérificateur d'autorisations de
l'application l'attribut `access`, `IS_AUTHENTICATED` par défaut, et refuse par un 403 quiconque ne l'a pas, et tout le
monde quand SecurityBundle n'est pas installé. Nommez un attribut plus strict quand seuls certains membres rédigent :

```yaml
# config/packages/phil_block_editor.yaml
phil_block_editor:
    access: ROLE_EDITOR
```

Avec `access: ~`, le bundle ne vérifie rien et l'application doit protéger le préfixe elle-même, par une entrée
`access_control` :

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/_block-editor, roles: ROLE_EDITOR }
```

Le téléverseur et les recherches ne sont appelés que pour les utilisateurs autorisés, mais sans savoir qui ils sont :
celui qui range les images par membre, ou ne montre que ce qu'un membre peut voir, lit l'utilisateur courant dans le
service `Security` de Symfony ([integrations.md](integrations.md#protéger-les-points-de-terminaison)).
