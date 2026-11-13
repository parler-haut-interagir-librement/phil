# Reference

## Domain

| Class | Role |
|---|---|
| `Domain\ValueObject\Anchor` | Where a passage is — block, field, character offsets — and what it says, with its context |
| `Domain\ValueObject\Suggestion` | The replacement a thread proposes, and where the decision on it stands |
| `Domain\ValueObject\Member` | The reference of an author or an actor as kept: a stable identifier, never an email address |
| `Domain\Anchoring\Passage` | One text where passages are searched: a field of a block, or a plain text |
| `Domain\Anchoring\Reanchoring` | Finds a passage again in texts that have changed |
| `Domain\Anchoring\InlineHtml` | The text of inline HTML, and a passage of it replaced with its tags kept |
| `Domain\Model\Thread` | A discussion about a passage or the whole page: its place, its state, its suggestion; table `phil_comment_thread` |
| `Domain\Model\Comment` | One message of a thread; table `phil_comment` |
| `Domain\Status\ThreadStatus` | `open`, `resolved` |
| `Domain\Status\SuggestionState` | `pending`, `accepted`, `rejected` |
| `Domain\Repository\ThreadRepository`, `CommentRepository` | The stores' contracts |
| `Domain\Exception\CommentsException`, `InvalidComment` | The bundle's exceptions; see [exceptions](exceptions.md) |

## Application

| Class | Role |
|---|---|
| `Application\Comments` | Threads opened on passages and on the page, answered, edited, deleted, resolved, reopened, decided, applied and re-anchored |
| `Application\Event\CommentWritten`, `CommentDeleted` | A comment written or edited, a comment deleted, told to the event dispatcher |
| `Application\Discussion` | A thread with its comments |
| `Application\UjoPassages` | The texts of a UJO document, and a passage replaced in it |
| `Application\CommentPolicy`, `DefaultCommentPolicy` | Who may do what |
| `Application\AuthorProvider` | Who the current request comments as |
| `Application\AuthorDirectory` | The names shown for authors |
| `Application\Exception\NotFound`, `CommentRefused` | A missing thread or comment; a refusal of the policy |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Doctrine\DoctrineThreadRepository`, `DoctrineCommentRepository` | The stores on the entity manager |
| `Infrastructure\Memory\InMemoryThreadRepository`, `InMemoryCommentRepository` | The stores in memory, for tests, emptied between requests (`kernel.reset`) |
| `Infrastructure\Http\CommentsController` | The endpoints |
| `Infrastructure\Http\CommentsRequest` | Whether a request comes from the controller of this site: its header, the browser's `Sec-Fetch-Site` and the stateless CSRF token `phil_comments` unless the method is safe; the name of the token's cookie |
| `Infrastructure\Http\DiscussionJson` | A thread as the endpoints answer it, for one viewer |
| `Infrastructure\Http\ClientConfig` | The controller's configuration for one page; whether the roles of WAI-ARIA 1.3 are written; the address of the member search |
| `Infrastructure\Http\PageForm` | The signed address of the plain forms of a page, and their stateless CSRF check |
| `Infrastructure\Http\PageController` | The plain forms of the comments on a page: the action run, back to the page or a page that says what stopped it |
| `Infrastructure\Twig\Components\PageCommentsComponent` | `PhilComments:Page`, the comments on a whole page drawn by the server |
| `Infrastructure\Twig\KitComponents` | The names of the kit's recipes under the application's subdirectory |
| `Infrastructure\Http\Routes` | The names of the routes, and their parameters |
| `Infrastructure\DependencyInjection\CommentsServices` | The services the configuration registers |
| `Infrastructure\DependencyInjection\CsrfProtectionPass` | Stops the container with a message that says what to change when `framework.csrf_protection` is false |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Names the kit's recipes after the Toolkit's subdirectory, unless `kit_subdirectory` says it |
| `PhilCommentsBundle` | The bundle: configuration, Doctrine mapping, AssetMapper paths in AssetMapperBundle's extension |

## Assets

| File | Role |
|---|---|
| `assets/src/comments_controller.js` | The Stimulus controller `phil--comments-bundle--comments` |
| `assets/src/lib/passage.js`, `api.js`, `view.js`, `i18n.js` | Its modules; see [JavaScript](javascript.md) |
| `assets/styles/comments.css` | The panel, the toolbar and the highlights |
| `config/routes.php` | The routes of the endpoints |
| `config/page_routes.php` | The route of the plain forms of a page |
| `templates/components/Page.html.twig` | The comments on a page, drawn with the kit's recipes |
| `templates/stopped.html.twig` | What stopped a plain form, with the text sent |
| `translations/phil_comments.en.php`, `phil_comments.fr.php` | The labels of the page |
