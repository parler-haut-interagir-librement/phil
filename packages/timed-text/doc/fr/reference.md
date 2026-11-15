<!-- phil-dev source-sha256=cd951f850f50e9855aa50a06a53acff3959d312260a8429b1aa363ab462049ef -->
# Référence

| Type | Rôle |
|---|---|
| `Track` | Les repères d'un média et ce que seul WebVTT porte ; décalage, fusion, scission, recherche de repères |
| `Cue` | Un texte minuté : début, fin, texte de repère, identifiant, réglages |
| `CueSettings` | Les réglages WebVTT d'un repère |
| `Region` | Une région WebVTT et ses réglages |
| `Note` | Un commentaire WebVTT ancré à un instant |
| `StylePolicy` | Si les blocs `STYLE` de WebVTT sont gardés ou écartés |
| `CueText` | Lecture et écriture du texte d'un repère : texte brut, voix, texte SRT, HTML |
| `Timecode` | Les instants en millisecondes et leurs formes écrites |
| `Limits` | Les bornes du travail sur un document |
| `Inspection` | Ce qu'une lecture indulgente a fait d'un document : la piste et les problèmes |
| `Issue` | Un problème, avec sa ligne |
| `Chapter` | Un chapitre : début, titre, fin, lien, image, table des matières, lieu |
| `ChapterLocation` | Le lieu d'un chapitre |
| `ChapterList` | Les chapitres d'un média et ce que le fichier dit de lui-même |
| `TranscriptFormat` | Les formats d'une piste, par type de média, avec leur lecteur et leur écrivain |
| `ChapterFormat` | Les formats de chapitres, par type de média, avec leur lecteur et leur écrivain |
| `Contract\TrackReader`, `Contract\TrackWriter` | Lire et écrire une piste |
| `Contract\ChapterReader`, `Contract\ChapterWriter` | Lire et écrire des chapitres |
| `WebVtt\WebVttReader`, `WebVtt\WebVttWriter` | WebVTT |
| `WebVtt\WebVttChapterReader`, `WebVtt\WebVttChapterWriter` | Chapitres WebVTT |
| `Srt\SrtReader`, `Srt\SrtWriter` | SubRip |
| `Podcast\JsonTranscriptReader`, `Podcast\JsonTranscriptWriter` | Transcriptions JSON de Podcasting 2.0 |
| `Podcast\JsonChapterReader`, `Podcast\JsonChapterWriter` | Chapitres JSON de Podcasting 2.0 |
| `Html\HtmlTranscriptReader`, `Html\HtmlTranscriptWriter` | Transcriptions HTML |
| `Exception\TimedTextException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidTimedText` | Un document refusé, avec son `documentLine` |
| `Exception\LimitExceeded` | Un document au-delà des limites |
| `Exception\InvalidCue` | Une valeur construite qu'aucun format ne peut porter |
| `Exception\InvalidLimits` | Des limites inférieures à 1 |
| `Internal\*` | Les analyseurs et les utilitaires : hors de l'API publique |
