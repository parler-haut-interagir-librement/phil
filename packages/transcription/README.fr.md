<!-- phil-dev source-sha256=ff0c651314ac221b22ee631bf7af0067016bdffc1f67642843e00939375d56ca -->
# phil/transcription

**La transcription d'enregistrements derrière un seul port : un serveur whisper.cpp qu'exploite le site, ou un service
infonuagique seulement avec le consentement des personnes entendues, des segments datés devenus des pistes de
phil/timed-text, et une relecture avant la publication.**

phil/transcription transforme un enregistrement en `Transcript` : des segments de texte datés, dans la langue que déclare
la requête — `fr-CA` comme `en` —, avec les locuteurs quand le service les distingue. Une transcription est à relire :
elle devient aussitôt une `Track` de phil/timed-text, qu'un éditeur corrige, et n'est écrite en WebVTT, en SRT, en
transcription JSON de Podcasting 2.0 ou en HTML qu'une fois relue par une personne.

Les transcripteurs partagent un port. `WhisperCpp\WhisperCppTranscriber` envoie l'enregistrement à un serveur whisper.cpp
que le site exploite sur ses propres machines : rien ne quitte le site. `OpenAi\OpenAiTranscriber` et
`Deepgram\DeepgramTranscriber` l'envoient à une autre entreprise ; une requête sans le consentement des personnes qu'on y
entend est donc refusée avant l'ouverture du fichier, comme la Loi 25 du Québec l'exige des renseignements personnels
envoyés à l'extérieur. Aucun modèle ne s'exécute en PHP.

C'est une bibliothèque PHP simple dont les prérequis sont `ext-sodium`, `phil/timed-text` et
`symfony/http-client-contracts` : n'importe quel client HTTP des contrats envoie les requêtes, et aucun SDK n'est
nécessaire.

## État

| Partie | État |
|---|---|
| `Transcript`, `Segment`, `Language`, `ReviewState` : des segments dans une langue déclarée, à relire avant la publication | Implémentée et testée |
| `TranscriptionRequest`, `RecordingConsent`, `Processing`, `Transcription` : le consentement demandé avant qu'un enregistrement ne parte | Implémentée et testée |
| `WhisperCpp\WhisperCppTranscriber` : un serveur whisper.cpp, `verbose_json` | Implémentée et testée contre un client mock |
| `OpenAi\OpenAiTranscriber` : l'API de transcription d'OpenAI ou un serveur qui la parle | Implémentée et testée contre un client mock |
| `Deepgram\DeepgramTranscriber` : l'API d'enregistrements préenregistrés de Deepgram, énoncés et locuteurs | Implémentée et testée contre un client mock |
| `Reader\VerboseJsonReader`, `Reader\DeepgramReader` : les réponses, bornées | Implémentée, testée et soumise au fuzzing |
| Services asynchrones qu'on interroge — AssemblyAI, Rev | Non commencée : un adaptateur de l'application |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, un serveur whisper.cpp. |
| [Transcripteurs](doc/fr/transcribers.md) | Le port, les trois adaptateurs, ce que chacun envoie et lit, les limites. |
| [Transcriptions](doc/fr/transcripts.md) | Les segments, la piste pour un éditeur, la relecture, la publication dans chaque format. |
| [Vie privée et accessibilité](doc/fr/privacy-and-accessibility.md) | Le consentement, ce qui quitte le site, la langue déclarée, les sous-titres et les transcriptions. |
| [Points d'extension](doc/fr/extension-points.md) | Le contrat qu'une application implémente. |
| [Tests](doc/fr/testing.md) | Les tests, les cibles de fuzzing, les services en intégration. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/transcription symfony/http-client
```

```php
use Phil\TimedText\TranscriptFormat;
use Phil\Transcription\Language;
use Phil\Transcription\Transcription;
use Phil\Transcription\TranscriptionRequest;
use Phil\Transcription\WhisperCpp\WhisperCppTranscriber;
use Symfony\Component\HttpClient\HttpClient;

$transcription = new Transcription(new WhisperCppTranscriber(HttpClient::create(), 'http://whisper:8080'));
$transcript = $transcription->transcribe(new TranscriptionRequest('/recordings/episode.wav', new Language('fr-CA')));

$draft = $transcript->toTrack();                       // for an editor, before anything is published
$vtt = $transcript->reviewed()->publish(TranscriptFormat::WebVtt);
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-sodium` ; un client HTTP de `symfony/http-client-contracts`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
