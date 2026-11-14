# Recording

`Mentions` is what the application calls when it saves a text, and when it deletes it:

```php
use Phil\MentionsBundle\Application\Mentions;
use Phil\Resource\ResourceReference;

$changes = $mentions->inText(
    ResourceReference::of('comment', $comment->id),  // the subject
    ResourceReference::of('user', $author->id),      // the author
    $comment->body,
    markdown: true,
    context: ResourceReference::of('page', $page->id),
);

$mentions->inDocument(ResourceReference::of('page', $page->id), $author, $document);
$mentions->forget(ResourceReference::of('comment', $comment->id));
```

## Subjects and contexts

The **subject** is what mentions: a comment, a proposal, a page, a message — whatever the application stores the text
in. The **context** is what decides who may see it, and is the subject itself unless the application names another:
a comment is seen by whoever sees its page, a proposal by the members of its project.

## What is recorded

Each time a text is recorded, `MentionRecorder`:

1. counts its mentions, each member once, and refuses the text over `limits.per_text`;
2. asks the directory for the members of its handles in one call, and for those of its marks in another;
3. leaves out the author, and every member for whom `maySee($member, $context)` is false;
4. compares the members left with those stored for the subject: the new ones are added, those no longer mentioned
   removed, in one change of the store;
5. dispatches `MemberMentioned` for each new member, once the change is stored;
6. returns `MentionChanges`: the members added, and the references removed.

So recording is idempotent: saving the same text again changes nothing and announces nothing, and an edit announces
only the members it adds. A member removed then mentioned again is announced again. A handle nobody has, a deleted
member and a member who may not see the content are not stored, so they are announced when a later edit mentions them
and they exist and may see it.

| Method | Returns |
|---|---|
| `inText($subject, $author, $text, $markdown = false, $context = null)` | `MentionChanges` |
| `inDocument($subject, $author, $document, $context = null)` | `MentionChanges` |
| `mentionedIn($subject)` | The members it mentions, `list<ResourceReference>`, first mentioned first |
| `mentioning($member, $limit = 50)` | Its mentions, `list<RecordedMention>`, latest first, 1 to 500 |
| `forget($subject)` | Nothing; nobody is notified |

`MentionRecorder::record($subject, $author, $mentions, $context)` takes mentions found elsewhere, as a list of
`Mention`.

## The event

`Phil\MentionsBundle\Application\Event\MemberMentioned` carries identifiers only:

| Property | Holds |
|---|---|
| `subject` | What mentions, `ResourceReference` |
| `member` | Who is mentioned |
| `author` | Who wrote it |
| `context` | What decided the visibility |
| `recipient` | The identifier the member's notifications go to, `Member::$recipient` |

It is dispatched on the PSR-14 dispatcher, Symfony's `event_dispatcher` in an application. The bundle listens to it to
notify: see [Notifications](notifications.md). An application listens to it for its own purposes — a feed, a count of
unread mentions, a webhook.

## The stores

`MentionRepository` keeps `RecordedMention` rows, each a subject, a member, an author and a second:

| Store | For |
|---|---|
| `DoctrineMentionRepository` | The table `phil_mention`: read through the entity manager, each change in one transaction |
| `InMemoryMentionRepository` | Tests and examples, emptied between two requests |

Two requests that record the same subject at once may both try to add a member. The Doctrine store adds its rows by an
upsert that keeps the row already there — `INSERT … ON CONFLICT (subject, member) DO NOTHING` on PostgreSQL and SQLite,
`INSERT … ON DUPLICATE KEY UPDATE id = id` on MySQL and MariaDB — and returns only the rows it inserted: the member is
recorded once, and only the request that inserted the row announces `MemberMentioned`, so the member is notified once.
Nothing is retried and no error reaches the application. On another platform, the statement is a plain insert, which
the unique `(subject, member)` refuses; on MySQL, the connection must count the rows changed, not the rows found, which
is the default of `pdo_mysql`.

The store in memory is tagged `kernel.reset`: it is emptied between two requests, under PHP-FPM as in a worker such as
FrankenPHP's, so that nothing a request writes reaches the next one. A test that reads what an earlier request wrote
uses the Doctrine store on SQLite.
