<!-- phil-dev source-sha256=1d21a75bd7c3dba4a5e9b3daa1126d996595ecb2f186022ef8f31c47278e063b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/transcription` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-24

### Modifié

- Exige la dernière mineure de chaque dépendance (`symfony/http-client-contracts` 3.8).

## 0.1.0-alpha - 2026-11-15

### Ajouté

- Le modèle : un `Transcript` fait de `Segment` dans une `Language` déclarée — `fr-CA` comprise —, avec son moteur et
  son `ReviewState` ; `toTrack()` et `fromTrack()` pour un éditeur, `publish()` dans chaque format de phil/timed-text
  une fois révisé, `NotReviewed` avant.
- Le port `Contract\Transcriber`, son `Processing` — local ou en nuage —, et `TranscriptionRequest` avec le
  `RecordingConsent` des personnes entendues ; `Transcription`, qui demande le consentement avant qu'aucun transcripteur
  voie la demande et laisse chaque transcription à réviser.
- `WhisperCpp\WhisperCppTranscriber` : un serveur whisper.cpp exploité par le site, `verbose_json`.
- `OpenAi\OpenAiTranscriber` et `Deepgram\DeepgramTranscriber` : des services en nuage refusés sans consentement avant
  l'ouverture du fichier, les clés comme paramètres sensibles, les points d'accès fixés par l'exploitant.
- `Reader\VerboseJsonReader` et `Reader\DeepgramReader` : les réponses lues dans les `TranscriptionLimits`, un segment
  qui ne peut pas être minuté ou qui est vide sauté, les locuteurs nommés d'après un motif.
- L'enregistrement envoyé en flux par morceaux, la réponse lue dans ses octets, un échec qui ne cite jamais le corps.
- Les exceptions, toutes des `Exception\TranscriptionException` : `ConsentRequired`, `InvalidTranscription`,
  `TranscriptionFailed`, `LimitExceeded`, `NotReviewed`.
- Les cibles de fuzzing `verbose-json` et `deepgram`.
- Le squelette du paquet, sa documentation dans `doc/`, `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
