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

| Option | Default | Role |
|---|---|---|
| `store` | `doctrine` | Where threads and comments are kept: the two tables through the entity manager, or memory, emptied between two requests |
| `entity_manager` | `default` | The entity manager whose `doctrine.orm.<name>_entity_manager` service the stores use |
| `author_provider` | none | Who the current request comments as; the endpoints answer 404 until it is set |
| `policy` | none | Who may read, comment, resolve, decide on suggestions and moderate; without it, `Comments` uses `DefaultCommentPolicy` and the endpoints answer 404 |
| `author_directory` | none | The names shown for authors; without it, the endpoints show references |
| `aria_roles` | `false` | Whether comments take the role `comment` and suggestions the role `suggestion` of the WAI-ARIA 1.3 draft, and a passage points to its comments by `aria-details`; see [JavaScript](javascript.md#the-roles-of-wai-aria-13) |
| `kit_subdirectory` | none | Where the recipes of the kit are, under the components directory, for the comments on the page; the subdirectory of the `ux_toolkit` configuration when none, to set in an environment where the Toolkit is not enabled |

## The store in memory

The store in memory serves tests and examples. The bundle tags it `kernel.reset`: it is emptied between two requests,
under PHP-FPM as in a worker such as FrankenPHP's, so that nothing a request writes reaches the next one, nor another
member. A test that reads what an earlier request wrote uses the Doctrine store on SQLite.

## Services

| Service | Public | Role |
|---|---|---|
| `Phil\CommentsBundle\Application\Comments` | yes | Opening threads on passages and on the whole page, answering, editing, deleting, resolving and reopening them, deciding on suggestions, re-anchoring; each comment written, edited or deleted told to the `event_dispatcher` service when there is one |
| `Phil\CommentsBundle\Domain\Repository\ThreadRepository`, `CommentRepository` | yes, aliases | The stores the configuration chose |
| `Phil\CommentsBundle\Application\CommentPolicy` | alias | The configured policy, or `DefaultCommentPolicy` |
| `Phil\CommentsBundle\Infrastructure\Http\CommentsController` | yes | The endpoints |
| `Phil\CommentsBundle\Infrastructure\Http\ClientConfig` | yes | The controller's configuration for one page |
| `Phil\CommentsBundle\Infrastructure\Http\PageController` | yes | The plain forms of the comments on a page, with Symfony UX Twig Component |
| `Phil\CommentsBundle\Infrastructure\Twig\Components\PageCommentsComponent` | component | `PhilComments:Page`, the comments on a page drawn by the server, with Symfony UX Twig Component |

## Written by the application

| Interface | Method | Role |
|---|---|---|
| `AuthorProvider` | `author(): ?ResourceReference` | The current author, `null` for nobody: a stable identifier, `user:42`, never an email address — an author whose identifier holds `@` is refused |
| `CommentPolicy` | `mayRead`, `mayComment`, `mayResolve`, `mayDecide`, `mayModerate` | The application's rules, described in [threads](threads.md) |
| `AuthorDirectory` | `name(ResourceReference): ?string` | The name shown for an author, `null` when unknown |
