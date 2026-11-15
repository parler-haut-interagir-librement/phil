<!-- phil-dev source-sha256=886c6f544be7a250611a4a0922c31c4c89c0e187400ff9d6f8a7d0b8fb08cbdb -->
# Transcripteurs

## Le port

```php
interface Transcriber
{
    public function processing(): Processing;                       // Local or Cloud
    public function transcribe(TranscriptionRequest $request): Transcript;
}
```

Une `TranscriptionRequest` est le chemin d'un fichier local, sa `Language` déclarée, le `RecordingConsent` des personnes
qu'on y entend — `LocalOnly` par défaut, `Cloud` une fois qu'elles ont accepté que l'enregistrement quitte le site —, et
une suggestion de 1000 octets au plus : des noms et des termes auxquels le service peut s'attendre.

`Transcription` est le transcripteur qu'une application appelle : il demande le consentement avant que le transcripteur
qu'il enveloppe ne voie la requête, et rend chaque transcription à relire, quoi que le transcripteur ait dit.

## Les adaptateurs

| Adaptateur | Traitement | Requête | Réponse |
|---|---|---|---|
| `WhisperCpp\WhisperCppTranscriber` | Local | `POST <server>/inference`, multipart : `file`, `response_format=verbose_json`, `language` (la langue principale, `fr`), `temperature=0`, `prompt` | `segments` avec `start`, `end` en secondes et `text` |
| `OpenAi\OpenAiTranscriber` | Cloud | `POST https://api.openai.com/v1/audio/transcriptions`, clé porteuse, multipart : `file`, `model` (`whisper-1` par défaut), `language`, `response_format=verbose_json`, `timestamp_granularities[]=segment`, `prompt` | la même |
| `Deepgram\DeepgramTranscriber` | Cloud | `POST https://api.deepgram.com/v1/listen?model=nova-3&language=fr-CA&utterances=true&punctuate=true&smart_format=true&diarize=…&keyterm=…`, `Authorization: Token <key>`, l'enregistrement comme corps | `results.utterances` avec `start`, `end`, `transcript`, `confidence`, `speaker` |

L'enregistrement est transmis en flux, un mébioctet à la fois, jamais tenu entier en mémoire. Whisper prend la langue
principale de l'étiquette ; Deepgram prend l'étiquette entière. La langue déclarée dans la requête est la langue de la
transcription, quelle que soit celle que le service a détectée. Un autre point de terminaison — un serveur qui parle
l'API d'OpenAI, une région de Deepgram — se donne au constructeur ; c'est à l'opérateur de le fixer.

Deepgram nomme ses locuteurs par un numéro à partir de 0 ; `DeepgramReader` les écrit `Speaker 1`, `Speaker 2`…, ou
selon le motif qu'on lui donne.

## Les limites

`TranscriptionLimits` borne chaque transcription :

| Limite | Valeur par défaut | Quand elle est dépassée |
|---|---|---|
| `maxAudioBytes` | 1 Gio ; 25 Mio pour `OpenAiTranscriber`, la limite même de l'API | L'enregistrement est refusé avant d'être ouvert |
| `maxResponseBytes` | 16 Mio | La réponse est coupée et refusée |
| `maxSegments` | 50 000 | La transcription est refusée |
| `maxTextBytes` | 8 Kio | Un segment plus long est refusé |
| `timeout` | 3600 secondes | La requête est abandonnée |

Un segment que le service n'a pas pu situer dans le temps — pas de nombre, négatif, au-delà du dernier instant de
phil/timed-text, une fin avant son début — ou laissé vide est ignoré.

## Erreurs

| Exception | Quand |
|---|---|
| `ConsentRequired` | Un transcripteur infonuagique, une requête sans consentement : rien n'est ouvert ni envoyé |
| `InvalidTranscription` | Une valeur refusée, un enregistrement illisible, une adresse qui n'est pas `http` ni `https` |
| `LimitExceeded` | L'une des limites ci-dessus |
| `TranscriptionFailed` | Le service est injoignable, répond autre chose que 2xx — son corps n'est jamais cité —, ou répond ce qui n'est pas une transcription |
| `NotReviewed` | Une transcription publiée avant qu'une personne l'ait relue |

Toutes implémentent `Exception\TranscriptionException`.
