# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\Poll\BallotKind` | enum | `single`, `multiple`, `ranked` |
| `Phil\Poll\PollRules` | `final readonly class` | What a ballot may hold: `single()`, `multiple()`, `ranked()`, `of()`, `has()`, `ballot()`, `MAX_OPTIONS`, `MAX_OPTION_LENGTH` |
| `Phil\Poll\Ballot` | `final readonly class` | The choices of one voter: `of()`, `isAbstention()` |
| `Phil\Poll\Result` | `final readonly class` | A count: `method`, `ballots`, `abstentions`, `standings`, `rounds`, `winners()`, `standingOf()` |
| `Phil\Poll\Standing` | `final readonly class` | Where one option finished: `option`, `score`, `place` |
| `Phil\Poll\Round` | `final readonly class` | One round of an instant runoff: `tallies`, `exhausted`, `eliminated` |
| `Phil\Poll\Tally` | `final readonly class` | The ballots one option holds in a round: `option`, `votes` |
| `Phil\Poll\CountingMethods` | `final readonly class` | The methods of an application by name: `defaults()`, `defaultFor()`, `get()`, `has()`, `namesFor()`, `count()` |
| `Phil\Poll\Contract\CountingMethod` | interface | A way of counting: `name()`, `supports()`, `count()` |
| `Phil\Poll\Counting\ChoiceCount` | `final readonly class` | `choices`: plurality and approval |
| `Phil\Poll\Counting\InstantRunoff` | `final readonly class` | `instant_runoff`: rounds and transfers |
| `Phil\Poll\Counting\Borda` | `final readonly class` | `borda`: points by rank |
| `Phil\Poll\Counting\Schulze` | `final readonly class` | `schulze`: strongest paths |
| `Phil\Poll\Exception\PollException` | interface | Every exception of the library |
| `Phil\Poll\Exception\InvalidPoll` | `final class` | Rules, a kind of ballot or a method the library cannot work with |
| `Phil\Poll\Exception\InvalidBallot` | `final class` | A ballot that breaks the rules of its poll |
| `Phil\Poll\Exception\UnknownCountingMethod` | `final class` | A method asked for by a name none answers to |

`Phil\Poll\Counting\Places`, `Choices` and `BallotGroups` are internal: the places of the standings, the choices as
option indexes and the grouping of identical rankings.
