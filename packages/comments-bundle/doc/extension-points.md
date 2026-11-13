# Extension points

What an application implements, configures or relies on. Authors and subjects are `phil/resource` references, and
anchors are read in UJO documents.

## Ports written by the application

Each is named by its service id in the configuration — see [Configuration](configuration.md).

### `AuthorProvider`

`Phil\CommentsBundle\Application\AuthorProvider` — who the current request comments as (`author_provider`). The
endpoints answer 404 until it is set.

| Method | Contract |
|---|---|
| `author(): ?ResourceReference` | The current author, or `null` for nobody; a stable identifier, never an email address |

### `CommentPolicy`

`Phil\CommentsBundle\Application\CommentPolicy` — the application's rules (`policy`). Without one, `Comments` uses
`DefaultCommentPolicy` and the endpoints answer 404. See [Threads](threads.md).

| Method | Contract |
|---|---|
| `mayRead(ResourceReference $reader, ResourceReference $subject): bool` | Whether the reader sees the subject's threads |
| `mayComment(ResourceReference $author, ResourceReference $subject): bool` | Whether the author opens threads and answers on it |
| `mayResolve(ResourceReference $actor, Thread $thread): bool` | Whether the actor resolves and reopens the thread |
| `mayDecide(ResourceReference $actor, Thread $thread): bool` | Whether the actor accepts or rejects its suggestion |
| `mayModerate(ResourceReference $actor, Comment $comment): bool` | Whether the actor edits or deletes another's comment |

### `AuthorDirectory`

`Phil\CommentsBundle\Application\AuthorDirectory` — the names shown for authors (`author_directory`). Without one,
the endpoints show references.

| Method | Contract |
|---|---|
| `name(ResourceReference $author): ?string` | The name shown, or `null` when unknown |

## Repositories

`ThreadRepository` and `CommentRepository` are public aliases of the stores `store` chooses — Doctrine, or memory for
tests, emptied between requests; an application points them at its own services under the same contracts.

| Port | Methods |
|---|---|
| `ThreadRepository` | `get()`, `ofSubject()` — the subject's threads —, `save()`, `remove()` |
| `CommentRepository` | `get()`, `ofThreads()` — the comments of a list of threads, read at once —, `save()`, `remove()` |

## Events

`CommentWritten` and `CommentDeleted` reach the application's event listeners after each comment is saved or deleted,
when the `event_dispatcher` service exists — see [Threads](threads.md#events).

```php
#[AsEventListener]
public function __invoke(CommentWritten $event): void
{
    if (!$event->edited) {
        $this->bus->dispatch(new CommentToReview($event->comment->id));
    }
}
```

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\CommentsBundle\Application\Comments` | Injected to open, answer, resolve, decide and re-anchor in code — when a document is saved, say; public |
| `Phil\CommentsBundle\Infrastructure\Http\ClientConfig` | `for()` gives the controller's configuration for one page; public |
| `PhilComments:Page` | The component that draws the comments on a whole page on the server — see [the page](page.md) |

## The page drawn by the server

| Point | How |
|---|---|
| The recipes | Installed by the Symfony UX Toolkit from the PHIL UX kit or the Shadcn kit; their subdirectory read from `ux_toolkit`, or `kit_subdirectory` |
| The page of a stopped form | `templates/bundles/PhilCommentsBundle/stopped.html.twig`, given `message`, `back` and `body` |
| The labels | The translation domain `phil_comments`, keys `page.*` |
| The section | Its `id`, `phil-comments-page`, the fragment a form comes back to; the component's attributes are added to the `<section>` |

## The browser

| Point | How |
|---|---|
| Labels | `locale` and `messages` added to the controller's configuration: labels of the application's own, by key |
| Refreshing | `refresh`, the seconds between two loads of the threads |
| The look | `comments.css`, on custom properties, and the highlights `::highlight(phil-comments)` and `::highlight(phil-comments-active)` |
| An accepted suggestion | Applied to the editor by its `phil-block-editor:apply` event, which the block editor listens to |
| The roles of WAI-ARIA 1.3 | `aria_roles` in the configuration, given to the controller as `ariaRoles` |

See [JavaScript](javascript.md).
