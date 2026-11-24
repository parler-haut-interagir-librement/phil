# Following

## Subjects

A subject is any record of the application, named by a `SubjectReference` of phil/notifications-bundle: a type — a
key such as `thread` or `member` — and an identifier. The type `member_subject_type`, `member` by default, names a
member: following it follows a person.

```php
use Phil\FollowBundle\Application\Follows;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

$follows->follow($member, new SubjectReference('thread', $thread->id));
```

Members, actors and activities are identifiers — letters, digits, `_`, `.`, `:` and `-`, 190 characters at most —,
never a name nor an address: an `InvalidFollow` says so, without repeating the value.

## Levels

| Level | What reaches the member |
|---|---|
| `watching` | Every activity |
| `mentions` | The activities that mention them |
| `ignoring` | Nothing, even what a followed parent would bring |
| `custom` | For each kind of event, the level the member chose — `watching`, `mentions` or `ignoring` —; `mentions` for an event they did not name |

```php
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;

$follows->follow($member, $forum, FollowLevel::Mentions);
$follows->follow($member, $thread, FollowLevel::Custom, new EventPreferences([
    'reply.created' => FollowLevel::Watching,
    'edit.made' => FollowLevel::Ignoring,
]));
```

Calling `follow()` again changes the level; a level unchanged is not written. The preferences by event, 64 at most,
are kept at the custom level only. Ignoring a subject is not following it: an ignoring member is not among its
followers.

`unfollow()` removes the member's follow, and withdraws their request to follow the subject when one waits. A member
never follows themselves: `FollowRefused`.

## Inheritance

The application names the parents of a subject, nearest first, with a `SubjectParents` service — the thread of a
reply, the forum of a thread, the category of a forum:

```php
final readonly class ForumParents implements SubjectParents
{
    public function parentsOf(SubjectReference $subject): iterable
    {
        if ('thread' === $subject->type) {
            yield new SubjectReference('forum', $this->threads->forumOf($subject->id));
        }
    }
}
```

For each member, the follow of the nearest subject of the chain decides: ignoring a thread wins over watching its
forum, and watching a thread wins over ignoring its forum. The chain stops at `inheritance.max_depth` parents and at the
first parent seen twice. `Follows::state()` gives the member's own follow, else the follow of the nearest parent it
inherits from, and whether a request of theirs waits.

## Access

A `FollowAccess` service says who may follow a subject and hear of its activity. It is asked when a member follows a
subject at a level other than ignoring, and again for each page of followers an activity is sent to, so that a member
who lost access to a private subject hears nothing more of it:

```php
final readonly class SubjectAccess implements FollowAccess
{
    public function filter(SubjectReference $subject, array $members): array
    {
        return $this->groups->membersAmong($subject, $members); // one query for the page
    }
}
```

With Symfony Security, the application names it in `access`. `OpenAccess` lets every member follow every subject.

## Requests

A `FollowApproval` service names the approver of a subject — the member of a locked profile, the owner of a private
group —, or null for an open subject. Following a subject that has an approver makes a request instead, at the level
asked, and tells the approver with `follow.requested`; asking again waits on the same request. Ignoring needs no
approval; a member who ignored a locked subject still asks to follow it.

```php
$outcome = $follows->follow($member, new SubjectReference('member', $locked->id)); // FollowOutcome::Requested

$requests->accept($approver, $requestId);   // the follow is made, the requester told with follow.accepted
$requests->reject($approver, $requestId);   // the request is deleted, without a word
$requests->withdraw($member, $requestId);   // the requester withdraws it
```

`FollowRequests::pendingFor()` lists the requests a member has to answer, oldest first; `sentBy()` those they sent.
A request no one waits on, or that another member approves, is an `UnknownFollowRequest` alike, so that no one learns of
another member's requests. Of two answers at once, the second finds the request gone.

## Mutual relationships

With `mutual.enabled`, two members may join in a mutual relationship beside one-way following: one asks, the other
accepts through the same requests; two members who ask each other are joined at once.

```php
$mutuals->request($member, $other);   // FollowOutcome::Requested, or Done when $other had asked
$mutuals->areMutual($member, $other);
$mutuals->of($member);                // the other members, in byte order
$mutuals->end($member, $other);       // ends it, and withdraws the requests between them
```

An accepted relationship makes each member follow the other at the watching level, unless they chose a level already
— an ignoring member stays so — or `mutual.follow_each_other` is false. Stopping to follow the other, or ignoring them,
leaves the relationship as it is: only `end()` ends it.

## Automatic follows

With `auto_follow.created` or `auto_follow.replied`, the application calls `AutoFollow` where a member creates or
replies:

```php
$autoFollow->created($author, new SubjectReference('thread', $thread->id));
$autoFollow->replied($author, new SubjectReference('thread', $thread->id));
```

The member then follows the subject at `auto_follow.level`, unless they chose a level already — ignoring included —,
the subject has an approver, or they may not follow it.

## A followed member

Following a member subject tells that member with `follow.followed`. `Followers::followersOf()` lists the followers of
a subject a page at a time, with their count; a member who hides their followers shows them to themselves alone. See
[Privacy](privacy.md).
