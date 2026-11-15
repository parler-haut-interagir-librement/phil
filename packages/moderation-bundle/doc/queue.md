# The queue

## Targets

A target is what can be reported: a forum post, a comment, a reaction, a listing, a message, a profile. Each is
designated by a `TargetReference` — a type such as `forum.post`, and the identifier of the content in it. The package
that holds the content implements `ModerationTarget`:

```php
use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Status\DecisionAction;

final readonly class ListingTarget implements ModerationTarget
{
    public function type(): string
    {
        return 'listing';
    }

    public function describe(string $id): ?TargetView
    {
        $listing = $this->listings->find($id);

        return null === $listing ? null : new TargetView($listing->title, $listing->summary, '/listings/'.$id, $listing->owner);
    }

    public function apply(string $id, DecisionAction $action): void
    {
        match ($action) {
            DecisionAction::Approve, DecisionAction::Restore => $this->listings->publish($id),
            DecisionAction::Hide => $this->listings->hide($id),
            DecisionAction::Remove => $this->listings->remove($id),
            default => null,
        };
    }
}
```

Autoconfiguration registers the service. The package keeps its content and its visibility; the bundle never reads
its tables. `apply()` must be idempotent: a decision may be replayed.

## Reports

`Reports::report($target, $reason, $comment)` files the report of the signed-in member. The reason is one of the
configured codes, the comment up to 1,000 characters. **A member reports a target once while it is open**: a second
report gives back the first. A report on a type no package registered is refused with `UnknownTarget`; a visitor's,
or one with a reason out of the catalogue, with `ModerationRefused`.

Each report keeps its source: `member`, `watched_words`, `sandbox` or `trusted_hide` ([Hooks](hooks.md)).

## The single queue

`ModerationQueue::items($limit, $offset)` gives the targets with open reports, the earliest report first, with how
many, their first and last moments, their reasons and their sources, each once. A moderator sees the types their role
covers; a member who is no moderator sees nothing. `entry($target)` gives one target as a moderator reviews it: what
its package says of it, its open reports, and its history.

## Roles

The configuration lists the roles in order; the current member acts under the first whose security attribute they are
granted. A role with `types` decides on those types only, and sees only them in the queue.

## Decisions

`Decisions::decide($target, $action, $publicReason, $note, $public, $grounds)` is a moderator's decision:

| Action | What the target's package is asked |
|---|---|
| `Dismiss` | Nothing; the content stays as it is |
| `Approve` | Publish a held content |
| `Hide` | Hide the content from the public |
| `Restore` | Show a hidden content again |
| `Remove` | Remove the content |
| `Warn` | Nothing; the author is warned |

The decision keeps the moderator, the role, the reason the public journal shows (up to 500 characters), the note only
the moderation reads (up to 2,000), and the author the target's package named at that moment. It closes every open
report on the target. A member who is no moderator, or whose role does not cover the type, is refused with
`ModerationRefused`. `on($target)` and `about($author)` give the history, the latest first.

`Hide`, `Remove` and `Warn` restrict the author: **such a decision on a content with an author comes with a statement
of reasons**, and its `Grounds` — a reason, the facts, a provision — are required and checked before the action is
applied ([Statements of reasons](statements-of-reasons.md)).

## Events

| Event | When |
|---|---|
| `ReportFiled` | A report was filed, by a member or by the bundle |
| `DecisionTaken` | A moderator decided |
| `ContentHeld` | A content waits for the moderation: a first post, or a text the watched words held |
| `ContentHiddenByMember` | A trusted member hid a content |
| `StatementIssued` | A statement of reasons was issued to the author of a restricted content, after `DecisionTaken` |

They are dispatched in-process, carrying the report, the decision or the statement; a listener that sends a message
carries their identifiers only.
