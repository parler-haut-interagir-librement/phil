# Testing

## The library's tests

```sh
vendor/bin/phpunit packages/transcription/tests
```

No service is called and no model runs: each transcriber is given Symfony's `MockHttpClient`, which reads the streamed
body to its end and answers with a recorded response. The tests check every field of each request, the consent refused
before anything is sent, the limits, the failures that never quote a body, and the reading of each response.

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

| Target | What it fuzzes |
|---|---|
| `verbose-json` | `Reader\VerboseJsonReader::read()`, then the WebVTT of the track |
| `deepgram` | `Reader\DeepgramReader::read()`, then the WebVTT of the track |

```sh
composer fuzz -- verbose-json deepgram
```

## Against a real service

The suite needs no service. To check a whisper.cpp server, or an account of a service, run a transcriber on a short
recording of your own, with the consent of those heard in it:

```php
$transcriber = new Phil\Transcription\WhisperCpp\WhisperCppTranscriber(Symfony\Component\HttpClient\HttpClient::create(), 'http://localhost:8080');
var_dump($transcriber->transcribe(new Phil\Transcription\TranscriptionRequest('sample.wav', new Phil\Transcription\Language('fr-CA')))->text());
```
