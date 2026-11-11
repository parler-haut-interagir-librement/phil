<!-- phil-dev source-sha256=4a2a441f02a3453a8e7e5cbaa2bbc658e6ab656237fbd373bb96c86dbef1ac13 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\Poll\BallotKind` | enum | `single`, `multiple`, `ranked` |
| `Phil\Poll\PollRules` | `final readonly class` | Ce qu'un bulletin peut contenir : `single()`, `multiple()`, `ranked()`, `of()`, `has()`, `ballot()`, `MAX_OPTIONS`, `MAX_OPTION_LENGTH` |
| `Phil\Poll\Ballot` | `final readonly class` | Les choix d'un électeur : `of()`, `isAbstention()` |
| `Phil\Poll\Result` | `final readonly class` | Un dépouillement : `method`, `ballots`, `abstentions`, `standings`, `rounds`, `winners()`, `standingOf()` |
| `Phil\Poll\Standing` | `final readonly class` | Le rang final d'une option : `option`, `score`, `place` |
| `Phil\Poll\Round` | `final readonly class` | Un tour d'un vote alternatif : `tallies`, `exhausted`, `eliminated` |
| `Phil\Poll\Tally` | `final readonly class` | Les bulletins que détient une option dans un tour : `option`, `votes` |
| `Phil\Poll\CountingMethods` | `final readonly class` | Les méthodes d'une application par nom : `defaults()`, `defaultFor()`, `get()`, `has()`, `namesFor()`, `count()` |
| `Phil\Poll\Contract\CountingMethod` | interface | Une façon de dépouiller : `name()`, `supports()`, `count()` |
| `Phil\Poll\Counting\ChoiceCount` | `final readonly class` | `choices` : majorité simple et vote par approbation |
| `Phil\Poll\Counting\InstantRunoff` | `final readonly class` | `instant_runoff` : tours et transferts |
| `Phil\Poll\Counting\Borda` | `final readonly class` | `borda` : points selon le rang |
| `Phil\Poll\Counting\Schulze` | `final readonly class` | `schulze` : chemins les plus forts |
| `Phil\Poll\Exception\PollException` | interface | Toutes les exceptions de la bibliothèque |
| `Phil\Poll\Exception\InvalidPoll` | `final class` | Des règles, une sorte de bulletin ou une méthode avec lesquelles la bibliothèque ne peut pas travailler |
| `Phil\Poll\Exception\InvalidBallot` | `final class` | Un bulletin qui enfreint les règles de son scrutin |
| `Phil\Poll\Exception\UnknownCountingMethod` | `final class` | Une méthode demandée par un nom auquel aucune ne répond |

`Phil\Poll\Counting\Places`, `Choices` et `BallotGroups` sont internes : les places du classement, les choix sous
forme d'index d'options et le regroupement des classements identiques.
