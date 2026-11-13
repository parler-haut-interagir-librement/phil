<!-- phil-dev source-sha256=94f5e004e30fb2a32395f7f8eac497bcafc0b1d7184df21855db07a43ea3f7de -->
# Installation

## Prérequis

PHP 8.5 ou plus récent avec `mbstring`, Symfony 8.2 avec FrameworkBundle et sa protection CSRF
(`symfony/security-csrf`), DoctrineBundle avec ORM 3, un service `clock` (`symfony/clock`), `phil/resource` et
`phil/ujo`. Dans le navigateur, Stimulus 3 servi par `symfony/stimulus-bundle` et AssetMapper, et l'éditeur par blocs
de `phil/block-editor-bundle`. Le surlignage des passages dans l'éditeur utilise la CSS Custom Highlight API, que les
navigateurs actuels prennent en charge ; sans elle, les fils sont listés mais pas surlignés. Pour les commentaires sur
une page dessinés par le serveur, Symfony UX Twig Component 3.7 avec TwigBundle et le traducteur, et les recettes du kit
PHIL UX.

## Activer le bundle

```sh
composer require phil/comments-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\CommentsBundle\PhilCommentsBundle::class => ['all' => true],
];
```

## Les tables

Le bundle associe `Phil\CommentsBundle\Domain\Model\Thread` et `Comment` pour Doctrine aux tables
`phil_comment_thread` et `phil_comment` : créez-les avec les migrations de l'application.

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Table | Colonne | Type | Rôle |
|---|---|---|---|
| `phil_comment_thread` | `id` | `VARCHAR(36)`, clé primaire | Un UUID v7 |
| | `subject` | `VARCHAR(320)`, indexé | La ressource commentée, `kind:identifier` |
| | `block`, `field` | `VARCHAR(64)`, `VARCHAR(1039)`, nullable | Où se trouve le passage ; les deux nuls dans un texte simple et sur la page entière |
| | `start_offset` | `INTEGER`, nullable | Le premier caractère du passage dans le texte du champ ; nul sur la page entière |
| | `quote` | `TEXT`, nullable | Le passage ; nul pour un fil sur la page entière |
| | `prefix`, `suffix` | `VARCHAR(255)` | Jusqu'à 32 caractères de chaque côté du passage, vides sur la page entière |
| | `detached` | `BOOLEAN` | Si le passage a disparu : le fil est orphelin |
| | `status` | `VARCHAR(16)` | `open` ou `resolved` |
| | `author` | `VARCHAR(320)` | Qui l'a ouvert, `kind:identifier` |
| | `suggestion`, `suggestion_state` | `TEXT`, `VARCHAR(16)`, nullable | Le remplacement proposé et `pending`, `accepted` ou `rejected` |
| | `resolved_by`, `resolved_at`, `created_at` | `VARCHAR(320)`, `DATETIME` UTC | Qui l'a résolu, quand, et quand il a été ouvert |
| `phil_comment` | `id` | `VARCHAR(36)`, clé primaire | Un UUID v7 |
| | `thread` | `VARCHAR(36)`, indexé | L'identifiant du fil |
| | `author`, `body` | `VARCHAR(320)`, `TEXT` | Qui l'a écrit, et son texte simple |
| | `created_at`, `edited_at` | `DATETIME` UTC | Quand il a été écrit et modifié la dernière fois |

## Les services de l'application

Les points de terminaison ne répondent qu'une fois que l'application a dit qui commente et ce qu'il peut faire :

```yaml
# config/packages/phil_comments.yaml
phil_comments:
    author_provider: App\Review\CurrentAuthor
    policy: App\Review\ReviewPolicy
    author_directory: App\Review\AuthorNames   # optional
```

```php
use Phil\CommentsBundle\Application\AuthorProvider;
use Phil\Resource\ResourceReference;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CurrentAuthor implements AuthorProvider
{
    public function __construct(private Security $security) {}

    public function author(): ?ResourceReference
    {
        $user = $this->security->getUser();

        return null === $user ? null : ResourceReference::of('user', $user->getUserIdentifier());
    }
}
```

La politique et l'annuaire sont décrits dans [les fils](threads.md).

## Les routes

```yaml
# config/routes/phil_comments.yaml
phil_comments:
    resource: '@PhilCommentsBundle/config/routes.php'
    prefix: /_comments
```

Placez le préfixe derrière le pare-feu de l'application. Les routes sont listées dans
[les points de terminaison](endpoints.md). Le bundle ajoute le jeton `phil_comments` à
`csrf_protection.stateless_token_ids` de FrameworkBundle, ce qui active `framework.csrf_protection` : laissez-le actif.
Avec `csrf_protection: false`, le conteneur s'arrête avec une `LogicException` qui le dit.

Les formulaires simples des commentaires sur une page ont une route à eux, importée à côté des points de terminaison
quand la page les dessine :

```yaml
phil_comments_page:
    resource: '@PhilCommentsBundle/config/page_routes.php'
    prefix: /_comments
```

## Le contrôleur et la feuille de style

Avec AssetMapper, le bundle associe son contrôleur à `@phil/comments-bundle` et sa feuille de style à
`phil-comments`, dans la configuration d'AssetMapperBundle ; sans ce bundle, rien n'est associé et le conteneur se compile
quand même. Activez le contrôleur dans `assets/controllers.json` ; il est chargé à la demande, seulement sur les pages
dont le balisage l'utilise :

```json
{
    "controllers": {
        "@phil/comments-bundle": {
            "comments": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

```twig
<link rel="stylesheet" href="{{ asset('phil-comments/comments.css') }}">
```

[JavaScript](javascript.md) montre le balisage et la configuration.

## Les recettes du kit

Les commentaires sur une page dessinés par le serveur utilisent des recettes du kit PHIL UX, installées dans
l'application par le Symfony UX Toolkit ; rien n'est rendu depuis `vendor/` :

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Elles ont les noms, les props et les blocs du kit Shadcn du Toolkit : un site sur Tailwind les installe donc à partir de
ce kit. Voyez [la page](page.md).
