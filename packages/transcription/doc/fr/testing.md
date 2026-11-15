<!-- phil-dev source-sha256=2e6f329747c4993a992a8b13c4d7fa977bf1808f4beb4bc25e83ea8462f5c9db -->
# Tests

## Les tests de la bibliothèque

```sh
vendor/bin/phpunit packages/transcription/tests
```

Aucun service n'est appelé et aucun modèle ne s'exécute : chaque transcripteur reçoit le `MockHttpClient` de Symfony, qui
lit le corps transmis en flux jusqu'au bout et répond par une réponse enregistrée. Les tests vérifient chaque champ de
chaque requête, le consentement refusé avant que rien ne soit envoyé, les limites, les échecs qui ne citent jamais un
corps, et la lecture de chaque réponse.

La bibliothèque est tenue à 100 % de score de mutation couvert par Infection.

## Fuzzing

| Cible | Ce qu'elle soumet au fuzzing |
|---|---|
| `verbose-json` | `Reader\VerboseJsonReader::read()`, puis le WebVTT de la piste |
| `deepgram` | `Reader\DeepgramReader::read()`, puis le WebVTT de la piste |

```sh
composer fuzz -- verbose-json deepgram
```

## Contre un vrai service

La suite n'a pas besoin de service. Pour vérifier un serveur whisper.cpp, ou le compte d'un service, exécutez un
transcripteur sur un court enregistrement à vous, avec le consentement des personnes qu'on y entend :

```php
$transcriber = new Phil\Transcription\WhisperCpp\WhisperCppTranscriber(Symfony\Component\HttpClient\HttpClient::create(), 'http://localhost:8080');
var_dump($transcriber->transcribe(new Phil\Transcription\TranscriptionRequest('sample.wav', new Phil\Transcription\Language('fr-CA')))->text());
```
