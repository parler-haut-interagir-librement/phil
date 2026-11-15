<!-- phil-dev source-sha256=a19f8ae43703523af06ea1644b056ab80980f7d8aafaa62d42227d7712ebb857 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Ce qu'ajoute une application passe par quatre contrats
et par les arguments des lecteurs et des écrivains.

## Contrats

| Contrat | Méthode | Contrat |
|---|---|---|
| `Contract\TrackReader` | `read(string): Track` | Lit un document strictement ; lève `InvalidTimedText` ou `LimitExceeded` ; ne garde aucun état entre les appels |
| | `inspect(string): Inspection` | Lit ce qu'il peut et liste les problèmes |
| `Contract\TrackWriter` | `write(Track): string` | Écrit une piste en omettant ce que le format ne peut porter |
| `Contract\ChapterReader` | `read(string): ChapterList` | Lit des chapitres strictement |
| `Contract\ChapterWriter` | `write(ChapterList): string` | Écrit des chapitres |

Un format que la bibliothèque ne lit pas — TTML, SCC, le JSON propre à un service — est un lecteur de l'application qui
construit un `Track`, avec le texte de repère de `CueText::fromPlain()` ; chaque écrivain de la bibliothèque l'écrit
alors.

## Arguments

| Point | Comment |
|---|---|
| Les bornes du travail | `Limits`, donné à chaque lecteur |
| Les blocs `STYLE` | `StylePolicy::Keep` ou `StylePolicy::Drop`, donné à `WebVttReader` et à `WebVttWriter` |
| La durée du média | Le `$duration` de `WebVttChapterWriter`, qui termine le dernier chapitre |
| Choisir un format à l'exécution | `TranscriptFormat::fromMediaType()` et `ChapterFormat::fromMediaType()` |
| Intercepter les erreurs de la bibliothèque | Chaque exception implémente `Exception\TimedTextException` |

## Stabilité

Les contrats, le modèle et les lecteurs et écrivains des formats forment l'API publique. L'espace de noms `Internal\` —
`CueToken`, `Input`, `IssueLog`, `Json`, `SrtParser`, `WebVttParser` — n'en fait pas partie : il change sans préavis.
