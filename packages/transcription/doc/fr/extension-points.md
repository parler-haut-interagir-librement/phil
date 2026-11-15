<!-- phil-dev source-sha256=52c6ef8099d9298e328a705ff0061a0ef6e5467cae8efad8cbacafa04a60b6d0 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Ce qu'une application ajoute passe par un contrat et par
les arguments des adaptateurs.

## Contrat

| Contrat | Méthode | Rôle |
|---|---|---|
| `Contract\Transcriber` | `processing(): Processing` | `Local` quand rien ne quitte le site, `Cloud` sinon |
| | `transcribe(TranscriptionRequest): Transcript` | Une transcription à relire ; refuse une requête sans consentement quand le traitement est `Cloud`, par `$request->assertAllows()` ; ne garde aucun état entre deux appels |

Un service que la bibliothèque ne parle pas — un service asynchrone qu'on interroge, un modèle local derrière un autre
serveur — est un `Transcriber` de l'application. L'envelopper dans `Transcription` maintient le consentement demandé et
la relecture exigée.

## Arguments

| Point | Comment |
|---|---|
| L'adresse d'un service | Le serveur de `WhisperCppTranscriber`, le point de terminaison des autres |
| Le modèle | `$model` de `OpenAiTranscriber` et de `DeepgramTranscriber` |
| Locuteurs distingués | `$diarize` de `DeepgramTranscriber` ; le motif d'étiquette de `DeepgramReader` |
| Les bornes | `TranscriptionLimits`, donnée à chaque adaptateur et à chaque lecteur |
| Attraper les erreurs de la bibliothèque | Toute exception implémente `Exception\TranscriptionException` |

## Stabilité

Le contrat, le modèle et les adaptateurs sont l'API publique, en 0.1 : ils peuvent changer dans une version mineure
jusqu'à la 1.0. L'espace de noms `Internal\` — `Http`, `Multipart`, `Segments` — ne l'est pas.
