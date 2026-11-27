<!-- phil-dev source-sha256=c247ca8ef9e472963ba1d744865ac451411d4e1d05ba6650fd9a0b0aa2bb7a3c -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/community-notes` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `Note`, une note à côté d'une ressource — son texte, son auteur, son statut et la décision qui le lui a donné —,
  `NoteStatus`, `Rating` et `Verdict`.
- `CrossAgreementRule` : assez d'évaluations, un verdict qui en tient sa part, et assez de paires d'évaluateurs
  habituellement en désaccord qui le donnent ensemble, chaque évaluateur dans une paire au plus, comparés en entiers ;
  `PairHistory`, `Bridge`, `Decision`, avec `toArray()` et `toPublicArray()` sans les noms des évaluateurs, et
  `DecisionReason`.
- `CommunityNotes` : `write()`, `rate()`, `withdraw()`, `reconsider()`, `get()`, `find()`, `notesOf()`, `shownOf()`,
  `exportMember()` et `eraseMember()`, chaque évaluation décidant de nouveau de la note.
- Les ports `Qualification`, `NoteRepository`, `RatingRepository` et `RatingHistory` ; des stockages en mémoire.
- `AuthorizationQualification`, la qualification demandée à Symfony Security, et `QualificationSubject`, ce sur quoi
  décident les *voters* de l'application.
- Les événements `NoteWritten`, `NoteRated`, `NoteStatusChanged`, `NoteWithdrawn` et `NoteErased`, chacun nommant la
  note ; le message `ReconsiderNote` et son handler.
- `CommunityNotesException`, implémentée par `InvalidNote`, `NoteNotFound` et `NoteRefused`.
- Un banc d'essai de la règle.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
