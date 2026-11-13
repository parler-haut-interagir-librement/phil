# Extension points

## `PollPolicy`

The port of who may do what, in `Phil\PollBundle\Application\Contract`. The application implements it with its own
roles, memberships or invitations and names the service under `phil_poll.policy`.

```php
interface PollPolicy
{
    public function mayCreate(ResourceReference $actor, ?ResourceReference $subject): bool;

    public function mayVote(Poll $poll, ResourceReference $voter): bool;

    public function mayManage(Poll $poll, ?ResourceReference $actor): bool;
}
```

- `mayCreate()` is asked by `create()`, with the subject of the draft.
- `mayVote()` is asked by `cast()`, `change()`, `changeWithReceipt()` and `withdraw()`, once the poll is known open.
- `mayManage()` is asked by `close()`, `reschedule()` and `delete()`, and by the visibility of the results: whoever
  manages a poll sees its results. Its actor is `null` for someone not signed in.

`DefaultPollPolicy` lets everyone create and vote, and only the creator manage. A policy of members only:

```php
use Phil\PollBundle\Application\Contract\PollPolicy;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\Resource\ResourceReference;

final readonly class MembersPolicy implements PollPolicy
{
    public function __construct(
        private Members $members,
    ) {
    }

    public function mayCreate(ResourceReference $actor, ?ResourceReference $subject): bool
    {
        return $this->members->isBoardMember($actor);
    }

    public function mayVote(Poll $poll, ResourceReference $voter): bool
    {
        return $this->members->isMemberInGoodStanding($voter);
    }

    public function mayManage(Poll $poll, ?ResourceReference $actor): bool
    {
        return null !== $actor && ($poll->isCreatedBy($actor) || $this->members->isBoardMember($actor));
    }
}
```

The policy is asked on every action: it keeps no state between requests, or resets it with `kernel.reset`.

## Counting methods

Every service implementing `Phil\Poll\Contract\CountingMethod` is added to `CountingMethods` beside the library's
four, by autoconfiguration; a poll names it by its `name()`. See the extension points of `phil/poll` for the
contract. Removing a method a stored poll names makes its results fail with `UnknownCountingMethod`.

A method that fills several seats implements `Phil\PollBundle\Domain\Counting\Contract\ElectingMethod`, which extends
`CountingMethod`: `elect($rules, $ballots, $seats)` returns an `Election` with its rounds, `count()` filling one seat.
`Polls` lets a poll counted by it name more than one seat, gives its election through `election()`, and its result
through `results()`. The scores of an election are billionths of a vote.

## The stores

`PollRepository` and `VoteRepository`, in `Phil\PollBundle\Domain\Repository`, are the ports of where polls and
ballots live; the bundle ships Doctrine and in-memory stores. Another store keeps a new ballot and its participation
together or not at all, refuses a second ballot of a voter with `AlreadyVoted`, and streams the ballots of a count
rather than loading them at once.

## The templates

`@PhilPoll/ballot.html.twig` and `@PhilPoll/results.html.twig` have blocks an embedding page replaces: `choices`,
`status` and `actions` for the ballot; `lines` and `revealed` for the results. An application overrides a whole
template under `templates/bundles/PhilPollBundle/`, and the words under its own translation domain `phil_poll`. A page
that draws the views its own way reads them from `PollViews`. See [The voting interface](voting-interface.md).

## The events

The events of [Events](events.md) are the way to act after a vote — refresh a cache, notify the creator, move an
idea to the next status — without changing the bundle; the DOM event `poll:revealed` is the way to act in the
browser when results are revealed.
