# Exceptions

Every exception of the bundle implements `Phil\PollBundle\Domain\Exception\PollBundleException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `Domain\Exception\InvalidPollDefinition` | `InvalidArgumentException` | A question, an option or a label out of bounds, options other than the rules', a misnamed counting method or one that does not count the kind of ballot, seats out of bounds or more than one for a method that fills one, a poll closing at or before it opens. |
| `Domain\Exception\AlreadyVoted` | `RuntimeException` | A voter casts a second ballot in a poll. |
| `Application\Exception\NotFound` | `RuntimeException` | No poll has the identifier, or no ballot of the voter, or of the receipt, is kept in it. |
| `Application\Exception\VoteRefused` | `RuntimeException` | The poll is not open, the policy refuses the voter, an anonymous ballot is changed without its receipt or withdrawn, a named one is changed with a receipt. |
| `Application\Exception\PollRefused` | `RuntimeException` | The policy refuses to let the actor create or manage the poll, the viewer may not see its results yet, or an election is asked of a poll whose method fills no seats. |

The exceptions of `phil/poll` pass through `Polls`: `InvalidBallot` when the choices of a ballot break the rules, or
when `BallotForm` reads fields the form does not send,
`InvalidPoll` when a draft's options or bounds do, and `UnknownCountingMethod` when a stored poll names a method the
application no longer declares.

The messages of `VoteRefused`, `PollRefused`, `AlreadyVoted` and `InvalidBallot` are written for the voter and are
safe to show back.
