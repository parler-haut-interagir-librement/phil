<!-- phil-dev source-sha256=797a089674b1c61a63f02d24a989f389eaff3780470894485665105ca5519c44 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Nature | Rôle |
|---|---|---|
| `Phil\CommunityNotes\CommunityNotes` | `final readonly class` | Le point d'entrée : rédiger, évaluer, retirer, décider de nouveau, énumérer, exporter et effacer les données d'un membre |
| `Phil\CommunityNotes\Note` | `final readonly class` | Une note : `write()`, `restore()`, `decided()`, `explained()`, `withdrawn()`, `isShown()` |
| `Phil\CommunityNotes\NoteStatus` | énumération | `needs_more_ratings`, `helpful`, `not_helpful`, `withdrawn` |
| `Phil\CommunityNotes\Rating` | `final readonly class` | Le verdict d'un évaluateur sur une note, et son heure |
| `Phil\CommunityNotes\Verdict` | énumération | `helpful`, `not_helpful` |
| `Phil\CommunityNotes\CrossAgreementRule` | `final readonly class` | La règle qui décide d'une note : `decide()`, `toArray()` |
| `Phil\CommunityNotes\Decision` | `final readonly class` | Ce que la règle a décidé et pourquoi : `ratings()`, `toArray()`, `toPublicArray()` |
| `Phil\CommunityNotes\DecisionReason` | énumération | `too_few_ratings`, `no_clear_majority`, `no_cross_agreement`, `cross_agreement` |
| `Phil\CommunityNotes\Bridge` | `final readonly class` | Deux évaluateurs qui divergent d'ordinaire et ont rendu le même verdict, avec leur historique |
| `Phil\CommunityNotes\PairHistory` | `final readonly class` | Les notes que deux évaluateurs ont partagées, et celles sur lesquelles ils se sont accordés : `agreesLessThan()` |
| `Phil\CommunityNotes\Contract\Qualification` | interface | Qui peut rédiger et évaluer |
| `Phil\CommunityNotes\Contract\NoteRepository` | interface | L'endroit où les notes sont conservées |
| `Phil\CommunityNotes\Contract\RatingRepository`, `RatingHistory` | interface | L'endroit où les évaluations sont conservées ; l'historique de deux évaluateurs |
| `Phil\CommunityNotes\Memory\InMemoryNoteRepository`, `InMemoryRatingRepository` | `final class` | Les stockages en mémoire |
| `Phil\CommunityNotes\Security\AuthorizationQualification` | `final readonly class` | La qualification demandée à Symfony Security |
| `Phil\CommunityNotes\Security\QualificationSubject` | `final readonly class` | Ce sur quoi les *voters* de l'application décident |
| `Phil\CommunityNotes\Event\NoteWritten`, `NoteRated`, `NoteStatusChanged`, `NoteWithdrawn`, `NoteErased` | `final readonly class` | Les événements, chacun nommant la note |
| `Phil\CommunityNotes\Message\ReconsiderNote`, `ReconsiderNoteHandler` | `final readonly class` | Le message pour décider de nouveau d'une note, et son *handler* |
| `Phil\CommunityNotes\Exception\CommunityNotesException` | interface | Toutes les exceptions du paquet |
| `Phil\CommunityNotes\Exception\InvalidNote` | `final class` | Une note hors bornes |
| `Phil\CommunityNotes\Exception\NoteNotFound` | `final class` | Aucune note n'a cet identifiant |
| `Phil\CommunityNotes\Exception\NoteRefused` | `final class` | Une action que refuse la qualification ou l'état de la note |
