<!-- phil-dev source-sha256=fd355479e850b9d2a026855489b9bb7cfa52ea83d9c0c64201a37f129d0ff615 -->
# Référence

| Type | Rôle |
|---|---|
| `Transcript` | Les segments datés d'un enregistrement, sa langue, sa relecture ; sa piste et sa publication |
| `Segment` | Ce qui a été dit entre deux instants : texte, locuteur, confiance |
| `Language` | Une étiquette BCP 47 d'une langue, d'une écriture et d'une région, écrite dans sa casse usuelle |
| `ReviewState` | À relire, ou relue par une personne |
| `TranscriptionRequest` | Un enregistrement à transcrire : son fichier, sa langue, le consentement, une suggestion |
| `RecordingConsent` | Si les personnes entendues ont accepté que l'enregistrement quitte le site |
| `Processing` | Si un transcripteur envoie un enregistrement hors du site |
| `TranscriptionLimits` | Les bornes d'une transcription |
| `Transcription` | Le transcripteur qu'une application appelle : le consentement d'abord, chaque transcription à relire |
| `Contract\Transcriber` | Le port des transcripteurs |
| `WhisperCpp\WhisperCppTranscriber` | Un serveur whisper.cpp exploité par le site |
| `OpenAi\OpenAiTranscriber` | L'API de transcription d'OpenAI, ou un serveur qui la parle |
| `Deepgram\DeepgramTranscriber` | L'API d'enregistrements préenregistrés de Deepgram |
| `Reader\VerboseJsonReader` | Lit le `verbose_json` d'un serveur Whisper |
| `Reader\DeepgramReader` | Lit les énoncés de Deepgram |
| `Exception\TranscriptionException` | L'interface de toute exception de la bibliothèque |
| `Exception\ConsentRequired` | Un enregistrement envoyé à l'extérieur sans consentement |
| `Exception\InvalidTranscription` | Une valeur refusée |
| `Exception\TranscriptionFailed` | Un service qui échoue ou ne répond aucune transcription |
| `Exception\LimitExceeded` | Un enregistrement, une réponse ou une transcription au-delà de sa limite |
| `Exception\NotReviewed` | Une transcription publiée avant sa relecture |
| `Internal\*` | Les utilitaires HTTP, multipart et de segments : hors de l'API publique |
