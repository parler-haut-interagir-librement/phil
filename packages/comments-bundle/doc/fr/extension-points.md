<!-- phil-dev source-sha256=162df3c3575af4ed0bf261616958e682556dde3b6ecdb00110aa8cef0b7ec6d3 -->
# Points d'extension

Ce qu'une application implémente, configure ou utilise. Les auteurs et les sujets sont des références de
`phil/resource`, et les ancres sont lues dans des documents UJO.

## Les ports écrits par l'application

Chacun est nommé par son identifiant de service dans la configuration — voyez [Configuration](configuration.md).

### `AuthorProvider`

`Phil\CommentsBundle\Application\AuthorProvider` — au nom de qui la requête courante commente (`author_provider`). Les
points de terminaison répondent 404 tant qu'il n'est pas défini.

| Méthode | Contrat |
|---|---|
| `author(): ?ResourceReference` | L'auteur courant, ou `null` pour personne ; un identifiant stable, jamais une adresse de courriel |

### `CommentPolicy`

`Phil\CommentsBundle\Application\CommentPolicy` — les règles de l'application (`policy`). Sans elle, `Comments`
utilise `DefaultCommentPolicy` et les points de terminaison répondent 404. Voyez [Fils](threads.md).

| Méthode | Contrat |
|---|---|
| `mayRead(ResourceReference $reader, ResourceReference $subject): bool` | Si le lecteur voit les fils du sujet |
| `mayComment(ResourceReference $author, ResourceReference $subject): bool` | Si l'auteur y ouvre des fils et y répond |
| `mayResolve(ResourceReference $actor, Thread $thread): bool` | Si l'acteur résout et rouvre le fil |
| `mayDecide(ResourceReference $actor, Thread $thread): bool` | Si l'acteur accepte ou rejette sa suggestion |
| `mayModerate(ResourceReference $actor, Comment $comment): bool` | Si l'acteur modifie ou supprime le commentaire d'un autre |

### `AuthorDirectory`

`Phil\CommentsBundle\Application\AuthorDirectory` — les noms affichés pour les auteurs (`author_directory`). Sans lui,
les points de terminaison affichent des références.

| Méthode | Contrat |
|---|---|
| `name(ResourceReference $author): ?string` | Le nom affiché, ou `null` quand il est inconnu |

## Les dépôts

`ThreadRepository` et `CommentRepository` sont des alias publics des stockages que choisit `store` — Doctrine, ou la
mémoire pour les tests, vidée entre deux requêtes ; une application les fait pointer vers ses propres services sous les mêmes contrats.

| Port | Méthodes |
|---|---|
| `ThreadRepository` | `get()`, `ofSubject()` — les fils du sujet —, `save()`, `remove()` |
| `CommentRepository` | `get()`, `ofThreads()` — les commentaires d'une liste de fils, lus d'un coup —, `save()`, `remove()` |

## Les événements

`CommentWritten` et `CommentDeleted` atteignent les écouteurs d'événements de l'application après l'enregistrement ou la
suppression de chaque commentaire, quand le service `event_dispatcher` existe — voyez [Fils](threads.md#les-événements).

```php
#[AsEventListener]
public function __invoke(CommentWritten $event): void
{
    if (!$event->edited) {
        $this->bus->dispatch(new CommentToReview($event->comment->id));
    }
}
```

## Les services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\CommentsBundle\Application\Comments` | Injecté pour ouvrir, répondre, résoudre, décider et réancrer dans le code — à l'enregistrement d'un document, par exemple ; public |
| `Phil\CommentsBundle\Infrastructure\Http\ClientConfig` | `for()` donne la configuration du contrôleur pour une page ; public |
| `PhilComments:Page` | Le composant qui dessine côté serveur les commentaires sur une page entière — voyez [la page](page.md) |

## La page dessinée par le serveur

| Point | Comment |
|---|---|
| Les recettes | Installées par le Symfony UX Toolkit à partir du kit PHIL UX ou du kit Shadcn ; leur sous-répertoire est lu dans `ux_toolkit`, ou dans `kit_subdirectory` |
| La page d'un formulaire arrêté | `templates/bundles/PhilCommentsBundle/stopped.html.twig`, qui reçoit `message`, `back` et `body` |
| Les libellés | Le domaine de traduction `phil_comments`, clés `page.*` |
| La section | Son `id`, `phil-comments-page`, le fragment où revient un formulaire ; les attributs du composant sont ajoutés à la `<section>` |

## Le navigateur

| Point | Comment |
|---|---|
| Libellés | `locale` et `messages` ajoutés à la configuration du contrôleur : des libellés propres à l'application, par clé |
| Rafraîchissement | `refresh`, les secondes entre deux chargements des fils |
| L'apparence | `comments.css`, sur des propriétés personnalisées, et le surlignage `::highlight(phil-comments)` et `::highlight(phil-comments-active)` |
| Une suggestion acceptée | Appliquée à l'éditeur par son événement `phil-block-editor:apply`, que l'éditeur par blocs écoute |
| Les rôles de WAI-ARIA 1.3 | `aria_roles` dans la configuration, donné au contrôleur sous le nom `ariaRoles` |

Voyez [JavaScript](javascript.md).
