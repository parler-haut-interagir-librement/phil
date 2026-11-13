<!-- phil-dev source-sha256=6d09ae546f217f10248206b549da846a4c6edbe6b9f8dd96b11bcb489df04c08 -->
# Configuration

```yaml
# config/packages/phil_comments.yaml
phil_comments:
    store: doctrine            # or "memory", for tests
    entity_manager: default    # the Doctrine entity manager of the tables
    author_provider: ~         # a service implementing AuthorProvider
    policy: ~                  # a service implementing CommentPolicy
    author_directory: ~        # a service implementing AuthorDirectory
    aria_roles: false          # the roles "comment" and "suggestion" of WAI-ARIA 1.3
    kit_subdirectory: ~        # where the kit's recipes are, when the Toolkit is not enabled
```

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `store` | `doctrine` | Où sont gardés les fils et les commentaires : les deux tables par le gestionnaire d'entités, ou la mémoire, vidée entre deux requêtes |
| `entity_manager` | `default` | Le gestionnaire d'entités dont les stockages utilisent le service `doctrine.orm.<name>_entity_manager` |
| `author_provider` | aucune | Au nom de qui la requête courante commente ; les points de terminaison répondent 404 tant qu'il n'est pas défini |
| `policy` | aucune | Qui peut lire, commenter, résoudre, décider des suggestions et modérer ; sans elle, `Comments` utilise `DefaultCommentPolicy` et les points de terminaison répondent 404 |
| `author_directory` | aucune | Les noms affichés pour les auteurs ; sans lui, les points de terminaison affichent des références |
| `aria_roles` | `false` | Indique si les commentaires prennent le rôle `comment` et les suggestions le rôle `suggestion` du brouillon de WAI-ARIA 1.3, et si un passage pointe vers ses commentaires par `aria-details` ; voir [JavaScript](javascript.md#les-rôles-de-wai-aria-13) |
| `kit_subdirectory` | aucun | L'endroit où se trouvent les recettes du kit, sous le répertoire des composants, pour les commentaires sur la page ; le sous-répertoire de la configuration `ux_toolkit` à défaut, à définir dans un environnement où le Toolkit n'est pas activé |

## Le stockage en mémoire

Le stockage en mémoire sert aux tests et aux exemples. Le bundle lui donne l'étiquette `kernel.reset` : il est vidé entre
deux requêtes, sous PHP-FPM comme dans un *worker* tel que celui de FrankenPHP, de sorte que rien de ce qu'écrit une
requête n'atteint la suivante, ni un autre membre. Un test qui lit ce qu'une requête antérieure a écrit utilise le
stockage Doctrine sur SQLite.

## Services

| Service | Public | Rôle |
|---|---|---|
| `Phil\CommentsBundle\Application\Comments` | oui | Ouvrir des fils sur des passages et sur la page entière, répondre, modifier, supprimer, résoudre et rouvrir, décider des suggestions, réancrer ; chaque commentaire écrit, modifié ou supprimé est signalé au service `event_dispatcher` quand il existe |
| `Phil\CommentsBundle\Domain\Repository\ThreadRepository`, `CommentRepository` | oui, alias | Les stockages que la configuration a choisis |
| `Phil\CommentsBundle\Application\CommentPolicy` | alias | La politique configurée, ou `DefaultCommentPolicy` |
| `Phil\CommentsBundle\Infrastructure\Http\CommentsController` | oui | Les points de terminaison |
| `Phil\CommentsBundle\Infrastructure\Http\ClientConfig` | oui | La configuration du contrôleur pour une page |
| `Phil\CommentsBundle\Infrastructure\Http\PageController` | oui | Les formulaires simples des commentaires sur une page, avec Symfony UX Twig Component |
| `Phil\CommentsBundle\Infrastructure\Twig\Components\PageCommentsComponent` | composant | `PhilComments:Page`, les commentaires sur une page dessinés par le serveur, avec Symfony UX Twig Component |

## Écrit par l'application

| Interface | Méthode | Rôle |
|---|---|---|
| `AuthorProvider` | `author(): ?ResourceReference` | L'auteur courant, `null` pour personne : un identifiant stable, `user:42`, jamais une adresse de courriel — un auteur dont l'identifiant contient `@` est refusé |
| `CommentPolicy` | `mayRead`, `mayComment`, `mayResolve`, `mayDecide`, `mayModerate` | Les règles de l'application, décrites dans [les fils](threads.md) |
| `AuthorDirectory` | `name(ResourceReference): ?string` | Le nom affiché pour un auteur, `null` quand il est inconnu |
