<!-- phil-dev source-sha256=279d06c9c1ccb125d4ccb521d9a7812c59713ae6d6655c51b40bce04dfd4bc43 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare aucun service et ses classes sont finales. Ce que choisit une
application se trouve dans les valeurs qu'elle lui passe.

| Point | Comment |
|---|---|
| Où la numérotation recommence | `NoteOptions::$restart`, `$chapterLevel`, `$pageMarkers` |
| Comment les numéros s'écrivent | `Numbering::decimal()`, `lowerRoman()`, `upperRoman()`, `symbols($sequence)` |
| La numérotation d'une locale | `NoteOptions::$locales`, par locale ou par langue |
| Où vont les notes | Le `Placement` de chaque `render()` |
| Le rendu des autres blocs | Le `HtmlRenderer` passé à `NotesRenderer`, avec ses propres moteurs de rendu et filtres |
| Ce qu'une note peut contenir | Le `InlineSanitizer` et le profil passés à `NotesRenderer`, `rich` par défaut |
| Les mots | `NoteLabels`, construit avec les traductions de l'application |
| Les identifiants | `$idPrefix` de `render()` |
| L'apparence | Les règles propres à l'application sur les classes, ou les jetons que lisent les feuilles ; voir [Style](styling.md) |
| La numérotation des pages réelles | Les règles d'impression de l'application ; voir [Impression](print.md) |
| La conversion des autres blocs en texte | Le `PlainText` passé à `NotesPlainText` |
| Que faire d'un problème | L'application lit `Resolution::$problems` : refuser, avertir ou continuer |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Exception\FootnotesException` |
