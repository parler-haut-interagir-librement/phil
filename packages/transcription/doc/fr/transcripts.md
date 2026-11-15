<!-- phil-dev source-sha256=0733d64d3a0e3d8e898d635a8603fa8381ad25497c5b184ecfa1b28f05083b32 -->
# Transcriptions

## Segments

Un `Segment` est ce qui a été dit entre deux instants en millisecondes : son texte sur une ligne, son locuteur quand le
service distingue les locuteurs, et la confiance du service de 0 à 1. Les espaces sont réduites ; un texte vide, un
instant hors plage ou une fin avant son début est refusé avec `InvalidTranscription`.

Une `Transcript` tient sa `Language`, ses segments dans l'ordre, son `ReviewState` et le moteur qui l'a produite, pour
mémoire : `whisper.cpp`, `openai:whisper-1`, `deepgram:nova-3`.

## Pour un éditeur

`toTrack()` donne les segments comme les repères d'une `Track` de phil/timed-text, un locuteur comme la voix de chaque
repère : un éditeur la corrige dans n'importe quel format de la bibliothèque — WebVTT, SRT — et `Transcript::fromTrack()`
relit la piste corrigée, chaque repère devenant un segment, sa voix le locuteur, un repère laissé vide étant abandonné.

```php
$vtt = TranscriptFormat::WebVtt->writer()->write($transcript->toTrack());
// … corrected by a person …
$corrected = Transcript::fromTrack(TranscriptFormat::WebVtt->reader()->read($edited), $transcript->language, ReviewState::Reviewed);
```

## La relecture

La transcription d'une machine est `ToReview`. `reviewed()` la marque relue ; `withSegments()` change ses segments et la
remet à relire. `publish($format)` l'écrit dans un format de phil/timed-text — WebVTT, SRT, JSON de Podcasting 2.0,
HTML — seulement une fois relue, et lance `NotReviewed` sinon ; `isPublishable()` dit laquelle s'applique.

```php
$transcript->reviewed()->publish(TranscriptFormat::Json);   // a <podcast:transcript> file
```

`text()` donne le texte entier, un segment par ligne, pour un index de recherche ou un résumé.
