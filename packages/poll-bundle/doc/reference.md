# Reference

| Class | Kind | Role |
|---|---|---|
| `Phil\PollBundle\PhilPollBundle` | bundle | The configuration `phil_poll` and the Doctrine mapping |
| `Phil\PollBundle\Application\Polls` | `final readonly class` | The entry point: create, cast, change, withdraw, close, reschedule, delete, count, `election()` |
| `Phil\PollBundle\Application\PollViews` | `final readonly class` | What a page shows of a poll to a viewer: `ballot()`, `results()` |
| `Phil\PollBundle\Application\BallotForm` | `final readonly class` | The choices of a ballot read from the fields its form posts: `choices()` |
| `Phil\PollBundle\Application\View\BallotView`, `BallotChoice` | `final readonly class` | The form of a ballot for a viewer, and one of its options |
| `Phil\PollBundle\Application\View\ResultsView`, `ResultLine` | `final readonly class` | The results for a viewer, and one of their lines |
| `Phil\PollBundle\Application\PollDraft` | `final readonly class` | What a new poll is made of: `rules()` |
| `Phil\PollBundle\Application\CastVote` | `final readonly class` | A ballot just cast: `vote`, `receipt` |
| `Phil\PollBundle\Application\Contract\PollPolicy` | interface | Who may create, vote and manage |
| `Phil\PollBundle\Application\DefaultPollPolicy` | `final readonly class` | Everyone votes, the creator manages |
| `Phil\PollBundle\Application\Event\PollCreated`, `VoteCast`, `VoteChanged`, `VoteWithdrawn`, `PollClosed`, `PollDeleted` | `final readonly class` | The events, each naming the poll |
| `Phil\PollBundle\Application\Exception\NotFound`, `VoteRefused`, `PollRefused` | `final class` | What an action of `Polls` refuses |
| `Phil\PollBundle\Domain\Model\Poll` | entity | A poll: `create()`, `rules()`, `options()`, `label()`, `isOpenAt()`, `isClosedAt()`, `close()`, `reschedule()` |
| `Phil\PollBundle\Domain\Model\Vote` | entity | A ballot: `named()`, `anonymous()`, `ballot()`, `change()` |
| `Phil\PollBundle\Domain\Model\Participation` | entity | That a voter took part in an anonymous poll |
| `Phil\PollBundle\Domain\ValueObject\PollOption` | `final readonly class` | An option and its label |
| `Phil\PollBundle\Domain\Status\ResultsVisibility` | enum | `always`, `after_voting`, `after_closing`, `managers` |
| `Phil\PollBundle\Domain\Counting\MeekStv` | `final readonly class` | The single transferable vote by Meek's method, `meek_stv` |
| `Phil\PollBundle\Domain\Counting\Contract\ElectingMethod` | interface | A counting method that fills several seats: `elect()` |
| `Phil\PollBundle\Domain\Counting\Election`, `ElectionRound`, `CandidateTally` | `final readonly class` | An election, its published rounds, an option in a round |
| `Phil\PollBundle\Domain\Counting\Billionths` | `final readonly class` | An exact non-negative number of denominator one billion |
| `Phil\PollBundle\Domain\Counting\MeekCount` | `final class`, internal | One count by Meek's method, round after round |
| `Phil\PollBundle\Domain\Status\CandidateStatus` | enum | `hopeful`, `elected`, `excluded`, `defeated` |
| `Phil\PollBundle\Domain\Status\TieBreak` | enum | `earlier_rounds`, `declared_order` |
| `Phil\PollBundle\Domain\Repository\PollRepository`, `VoteRepository` | interface | Where polls, ballots and participations live |
| `Phil\PollBundle\Domain\Exception\PollBundleException` | interface | Every exception of the bundle |
| `Phil\PollBundle\Domain\Exception\InvalidPollDefinition`, `AlreadyVoted` | `final class` | A poll that cannot be kept; a second ballot |
| `Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository`, `DoctrineVoteRepository` | `final readonly class` | The Doctrine stores |
| `Phil\PollBundle\Infrastructure\Memory\InMemoryPollRepository`, `InMemoryVoteRepository` | `final class` | The in-memory stores, emptied between requests |
| `Phil\PollBundle\Infrastructure\Twig\PollTwig` | `final readonly class` | The functions `phil_poll_ballot()` and `phil_poll_results()` |
| `Phil\PollBundle\Infrastructure\DependencyInjection\PollServices` | `final class` | The services, and the tag `phil_poll.counting_method` |

| Template, script | Role |
|---|---|
| `templates/ballot.html.twig` | The form of a ballot, `@PhilPoll/ballot.html.twig` |
| `templates/results.html.twig` | The results, `@PhilPoll/results.html.twig` |
| `translations/phil_poll.en.php`, `phil_poll.fr.php` | The words of the templates |
| `assets/src/results_controller.js` | The Stimulus controller `phil--poll-bundle--results`, which dispatches `poll:revealed` |
