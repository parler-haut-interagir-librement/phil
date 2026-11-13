# Threads

## Authors and resources

Authors and the resources commented on are opaque references of `phil/resource`: `user:42`, `article:0192f3c4-…`.
The bundle stores and compares them, never resolves them; the application names its users and its resources as it
wishes. An author, and whoever resolves a thread, is named by a stable identifier, never by an email address: a
reference whose identifier holds `@` is refused with `InvalidComment`, and the endpoints answer it with a 400.

## Threads on a passage, threads on the page

A thread is about a passage — a few words selected in the text, see [anchoring](anchoring.md) — or about the whole
page. Both kinds live side by side on the same resource, under the same policy, with the same replies, resolve and
reopen, edits and deletions. A thread on the page has no anchor (`anchor()` returns `null`, `isOnPage()` is true): it
never moves when the text changes, is never orphaned, and proposes no replacement.

```php
$comments->open($subject, Anchor::of('a1B2c3', 'text', 4, 'cat'), $author, 'Why a cat?');   // on a passage
$comments->openOnPage($subject, $author, 'A clear article, thank you.');                   // on the page
```

## The life of a thread

| Call | Who | What happens |
|---|---|---|
| `open($subject, $anchor, $author, $body, $suggestion)` | `mayComment` | A thread on the passage, with its first comment; a suggestion when `$suggestion` is a text |
| `openOnPage($subject, $author, $body)` | `mayComment` | A thread on the whole page, with its first comment |
| `reply($thread, $author, $body)` | `mayComment` | A comment added at the end |
| `edit($comment, $author, $body)` | its author, or `mayModerate` | The comment's text replaced; `editedAt` set |
| `delete($comment, $author)` | its author, or `mayModerate` | The comment removed; a thread left without comments is removed with it, and `null` returned |
| `resolve($thread, $actor)` | `mayResolve` | `resolved`, with who and when; resolving again changes nothing |
| `reopen($thread, $actor)` | `mayResolve` | `open` again; a decision on its suggestion stands |
| `accept($thread, $actor)`, `reject($thread, $actor)` | `mayDecide` | The suggestion decided, the thread resolved; see [suggestions](suggestions.md) |
| `discussions($subject, $reader)` | `mayRead` | Every thread of the resource with its comments, oldest first, on passages and on the page, resolved and orphaned ones included |

Each call returns a `Discussion`: the thread and its comments, oldest first. An author edits and deletes their own
comments while they may still comment on the resource.

A comment is plain text, trimmed, 1 to 10000 characters: never HTML, and shown as text by the controller. Times are
UTC, to the second.

## Events

When `Comments` is given an event dispatcher — the bundle gives it the `event_dispatcher` service when the application
has one —, it dispatches, after saving:

| Event | When | Carries |
|---|---|---|
| `CommentWritten` | A thread opened, on a passage or on the page, a reply, an edit | `comment`, `thread`, `edited` |
| `CommentDeleted` | A comment deleted | `comment`, its former identifier, `thread`, `threadRemoved` |

They are dispatched in the request that saved the comment, for listeners of the same application — the
`phil/mentions-bundle` bridge reads the mentions of comments this way; whatever must run later is the listener's to
send on a message bus, by identifiers.

## The policy

```php
use Phil\CommentsBundle\Application\CommentPolicy;

final readonly class ReviewPolicy implements CommentPolicy
{
    public function mayRead(ResourceReference $reader, ResourceReference $subject): bool { /* … */ }
    public function mayComment(ResourceReference $author, ResourceReference $subject): bool { /* … */ }
    public function mayResolve(ResourceReference $actor, Thread $thread): bool { /* … */ }
    public function mayDecide(ResourceReference $actor, Thread $thread): bool { /* … */ }
    public function mayModerate(ResourceReference $actor, Comment $comment): bool { /* … */ }
}
```

`DefaultCommentPolicy`, used by `Comments` when the application configures none, lets anyone read and comment, the
author of a thread resolve it, and nobody decide on suggestions or moderate. The endpoints are never served on it.

## Names

The endpoints show an author by the name the application's `AuthorDirectory` gives, by the reference when it gives
none or when no directory is configured.
