<!-- phil-dev source-sha256=55b173ac7fe87a51fdbba00e3be1348dd3a5fc7383d55a6a4768842fedbe2a0d -->
# Référence

| Classe | Nature | Rôle |
|---|---|---|
| `Phil\PollBundle\PhilPollBundle` | bundle | La configuration `phil_poll` et le mappage Doctrine |
| `Phil\PollBundle\Application\Polls` | `final readonly class` | Le point d'entrée : créer, déposer, modifier, retirer, fermer, changer les dates, supprimer, compter, `election()` |
| `Phil\PollBundle\Application\PollViews` | `final readonly class` | Ce qu'une page montre d'un sondage à une personne : `ballot()`, `results()` |
| `Phil\PollBundle\Application\BallotForm` | `final readonly class` | Les choix d'un bulletin lus dans les champs que poste son formulaire : `choices()` |
| `Phil\PollBundle\Application\View\BallotView`, `BallotChoice` | `final readonly class` | Le formulaire d'un bulletin pour une personne, et l'une de ses options |
| `Phil\PollBundle\Application\View\ResultsView`, `ResultLine` | `final readonly class` | Les résultats pour une personne, et l'une de leurs lignes |
| `Phil\PollBundle\Application\PollDraft` | `final readonly class` | Ce dont est fait un nouveau sondage : `rules()` |
| `Phil\PollBundle\Application\CastVote` | `final readonly class` | Un bulletin tout juste déposé : `vote`, `receipt` |
| `Phil\PollBundle\Application\Contract\PollPolicy` | interface | Qui peut créer, voter et gérer |
| `Phil\PollBundle\Application\DefaultPollPolicy` | `final readonly class` | Tout le monde vote, le créateur gère |
| `Phil\PollBundle\Application\Event\PollCreated`, `VoteCast`, `VoteChanged`, `VoteWithdrawn`, `PollClosed`, `PollDeleted` | `final readonly class` | Les événements, chacun nommant le sondage |
| `Phil\PollBundle\Application\Exception\NotFound`, `VoteRefused`, `PollRefused` | `final class` | Ce que refuse une action de `Polls` |
| `Phil\PollBundle\Domain\Model\Poll` | entité | Un sondage : `create()`, `rules()`, `options()`, `label()`, `isOpenAt()`, `isClosedAt()`, `close()`, `reschedule()` |
| `Phil\PollBundle\Domain\Model\Vote` | entité | Un bulletin : `named()`, `anonymous()`, `ballot()`, `change()` |
| `Phil\PollBundle\Domain\Model\Participation` | entité | La participation d'un votant à un sondage anonyme |
| `Phil\PollBundle\Domain\ValueObject\PollOption` | `final readonly class` | Une option et son libellé |
| `Phil\PollBundle\Domain\Status\ResultsVisibility` | énumération | `always`, `after_voting`, `after_closing`, `managers` |
| `Phil\PollBundle\Domain\Counting\MeekStv` | `final readonly class` | Le vote unique transférable selon la méthode de Meek, `meek_stv` |
| `Phil\PollBundle\Domain\Counting\Contract\ElectingMethod` | interface | Une méthode de dépouillement qui pourvoit plusieurs sièges : `elect()` |
| `Phil\PollBundle\Domain\Counting\Election`, `ElectionRound`, `CandidateTally` | `final readonly class` | Une élection, ses tours publiés, une option dans un tour |
| `Phil\PollBundle\Domain\Counting\Billionths` | `final readonly class` | Un nombre exact non négatif de dénominateur un milliard |
| `Phil\PollBundle\Domain\Counting\MeekCount` | `final class`, interne | Un dépouillement selon la méthode de Meek, tour après tour |
| `Phil\PollBundle\Domain\Status\CandidateStatus` | énumération | `hopeful`, `elected`, `excluded`, `defeated` |
| `Phil\PollBundle\Domain\Status\TieBreak` | énumération | `earlier_rounds`, `declared_order` |
| `Phil\PollBundle\Domain\Repository\PollRepository`, `VoteRepository` | interface | L'endroit où vivent les sondages, les bulletins et les participations |
| `Phil\PollBundle\Domain\Exception\PollBundleException` | interface | Toutes les exceptions du bundle |
| `Phil\PollBundle\Domain\Exception\InvalidPollDefinition`, `AlreadyVoted` | `final class` | Un sondage qui ne peut pas être conservé ; un deuxième bulletin |
| `Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository`, `DoctrineVoteRepository` | `final readonly class` | Les stockages Doctrine |
| `Phil\PollBundle\Infrastructure\Memory\InMemoryPollRepository`, `InMemoryVoteRepository` | `final class` | Les stockages en mémoire, vidés entre deux requêtes |
| `Phil\PollBundle\Infrastructure\Twig\PollTwig` | `final readonly class` | Les fonctions `phil_poll_ballot()` et `phil_poll_results()` |
| `Phil\PollBundle\Infrastructure\DependencyInjection\PollServices` | `final class` | Les services, et l'étiquette `phil_poll.counting_method` |

| Gabarit, script | Rôle |
|---|---|
| `templates/ballot.html.twig` | Le formulaire d'un bulletin, `@PhilPoll/ballot.html.twig` |
| `templates/results.html.twig` | Les résultats, `@PhilPoll/results.html.twig` |
| `translations/phil_poll.en.php`, `phil_poll.fr.php` | Les mots des gabarits |
| `assets/src/results_controller.js` | Le contrôleur Stimulus `phil--poll-bundle--results`, qui diffuse `poll:revealed` |
