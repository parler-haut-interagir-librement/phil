# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\CommunityNotes\CommunityNotes` | `final readonly class` | The entry point: write, rate, withdraw, reconsider, list, export and erase a member's data |
| `Phil\CommunityNotes\Note` | `final readonly class` | A note: `write()`, `restore()`, `decided()`, `explained()`, `withdrawn()`, `isShown()` |
| `Phil\CommunityNotes\NoteStatus` | enum | `needs_more_ratings`, `helpful`, `not_helpful`, `withdrawn` |
| `Phil\CommunityNotes\Rating` | `final readonly class` | The verdict of a rater on a note, and its time |
| `Phil\CommunityNotes\Verdict` | enum | `helpful`, `not_helpful` |
| `Phil\CommunityNotes\CrossAgreementRule` | `final readonly class` | The rule that decides a note: `decide()`, `toArray()` |
| `Phil\CommunityNotes\Decision` | `final readonly class` | What the rule decided and why: `ratings()`, `toArray()`, `toPublicArray()` |
| `Phil\CommunityNotes\DecisionReason` | enum | `too_few_ratings`, `no_clear_majority`, `no_cross_agreement`, `cross_agreement` |
| `Phil\CommunityNotes\Bridge` | `final readonly class` | Two raters who usually disagree and gave the same verdict, with their history |
| `Phil\CommunityNotes\PairHistory` | `final readonly class` | The notes two raters shared, and those they agreed on: `agreesLessThan()` |
| `Phil\CommunityNotes\Contract\Qualification` | interface | Who may write and rate |
| `Phil\CommunityNotes\Contract\NoteRepository` | interface | Where notes are kept |
| `Phil\CommunityNotes\Contract\RatingRepository`, `RatingHistory` | interface | Where ratings are kept; the history of two raters |
| `Phil\CommunityNotes\Memory\InMemoryNoteRepository`, `InMemoryRatingRepository` | `final class` | The stores in memory |
| `Phil\CommunityNotes\Security\AuthorizationQualification` | `final readonly class` | The qualification asked of Symfony Security |
| `Phil\CommunityNotes\Security\QualificationSubject` | `final readonly class` | What the application's voters decide on |
| `Phil\CommunityNotes\Event\NoteWritten`, `NoteRated`, `NoteStatusChanged`, `NoteWithdrawn`, `NoteErased` | `final readonly class` | The events, each naming the note |
| `Phil\CommunityNotes\Message\ReconsiderNote`, `ReconsiderNoteHandler` | `final readonly class` | The message to decide a note again, and its handler |
| `Phil\CommunityNotes\Exception\CommunityNotesException` | interface | Every exception of the package |
| `Phil\CommunityNotes\Exception\InvalidNote` | `final class` | A note out of bounds |
| `Phil\CommunityNotes\Exception\NoteNotFound` | `final class` | No note has the identifier |
| `Phil\CommunityNotes\Exception\NoteRefused` | `final class` | An action the qualification or the state of the note refuses |
