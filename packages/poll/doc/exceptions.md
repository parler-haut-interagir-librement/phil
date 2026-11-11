# Exceptions

Every exception of the library implements `Phil\Poll\Exception\PollException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidPoll` | `InvalidArgumentException` | Rules with fewer than 2 or more than 100 options, an option outside its alphabet or declared twice, bounds outside 1 ≤ min ≤ max ≤ options, a single ballot of other bounds; a method asked to count a kind of ballot it does not count; a counting method misnamed or declared twice. |
| `InvalidBallot` | `InvalidArgumentException` | Choices that are not a list, a choice that is not one of the options, a choice given twice, too few or too many choices, a blank ballot the rules refuse. |
| `UnknownCountingMethod` | `RuntimeException` | `CountingMethods::get()` or `count()` is asked for a name no method answers to. |

`InvalidBallot` is the voter's mistake, or a tampered request: its message names the choice and says what is wrong,
and is safe to show back. `InvalidPoll` is a mistake of the application, best caught where a poll is created.
`UnknownCountingMethod` means a stored poll names a method the application no longer declares.
