<!-- phil-dev source-sha256=2f9bed05534624d4dc2a180b6bd1bc767de1308da1df5544e0ccf7e6620871f0 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\Poll\BallotKind` | enum | `single`, `multiple`, `ranked`, `scale` |
| `Phil\Poll\PollRules` | `final readonly class` | Ce qu'un bulletin peut contenir : `single()`, `multiple()`, `ranked()`, `scale()`, `netPromoter()`, `of()`, `has()`, `mark()`, `ballot()`, `MAX_OPTIONS`, `MAX_OPTION_LENGTH` |
| `Phil\Poll\Ballot` | `final readonly class` | Les choix d'un électeur : `of()`, `isAbstention()` |
| `Phil\Poll\Result` | `final readonly class` | Un dépouillement : `method`, `ballots`, `abstentions`, `standings`, `rounds`, `rating`, `netPromoter`, `winners()`, `standingOf()` |
| `Phil\Poll\Rating` | `final readonly class` | Les notes d'une échelle : `marked`, `sum`, `mean()` |
| `Phil\Poll\NetPromoter` | `final readonly class` | Les groupes d'un Net Promoter Score : `promoters`, `passives`, `detractors`, `respondents()`, `score()` |
| `Phil\Poll\Standing` | `final readonly class` | Le rang final d'une option : `option`, `score`, `place` |
| `Phil\Poll\Round` | `final readonly class` | Un tour d'un vote alternatif : `tallies`, `exhausted`, `eliminated` |
| `Phil\Poll\Tally` | `final readonly class` | Les bulletins que détient une option dans un tour : `option`, `votes` |
| `Phil\Poll\CountingMethods` | `final readonly class` | Les méthodes d'une application par nom : `defaults()`, `defaultFor()`, `get()`, `has()`, `namesFor()`, `count()` |
| `Phil\Poll\Contract\CountingMethod` | interface | Une façon de dépouiller : `name()`, `supports()`, `count()` |
| `Phil\Poll\Counting\ChoiceCount` | `final readonly class` | `choices` : majorité simple et vote par approbation |
| `Phil\Poll\Counting\InstantRunoff` | `final readonly class` | `instant_runoff` : tours et transferts |
| `Phil\Poll\Counting\Borda` | `final readonly class` | `borda` : points selon le rang |
| `Phil\Poll\Counting\Schulze` | `final readonly class` | `schulze` : chemins les plus forts |
| `Phil\Poll\Counting\ScaleCount` | `final readonly class` | `scale` : la distribution et la somme des notes |
| `Phil\Poll\Counting\NetPromoterScore` | `final readonly class` | `nps` : promoteurs, passifs et détracteurs d'une échelle de 0 à 10 |
| `Phil\Poll\Exception\PollException` | interface | Toutes les exceptions de la bibliothèque |
| `Phil\Poll\Exception\InvalidPoll` | `final class` | Des règles, une sorte de bulletin ou une méthode avec lesquelles la bibliothèque ne peut pas travailler |
| `Phil\Poll\Exception\InvalidBallot` | `final class` | Un bulletin qui enfreint les règles de son scrutin |
| `Phil\Poll\Exception\UnknownCountingMethod` | `final class` | Une méthode demandée par un nom auquel aucune ne répond |

`Phil\Poll\Counting\Places`, `Choices`, `BallotGroups` et `Marks` sont internes : les places du classement, les choix
sous forme d'index d'options, le regroupement des classements identiques et les notes d'une échelle lues une fois.
