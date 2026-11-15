# phil/transcription

**Transcription of recordings behind one port: a whisper.cpp server the site runs, or a cloud service only with the
consent of the people heard, timestamped segments turned into phil/timed-text tracks, and a review before publication.**

phil/transcription turns a recording into a `Transcript`: timed segments of text, in the language the request declares —
`fr-CA` as well as `en` —, with speakers when the service tells them apart. A transcript is to review: it becomes a
phil/timed-text `Track` at once, for an editor to correct, and is written as WebVTT, SRT, a Podcasting 2.0 JSON
transcript or HTML only once a person has reviewed it.

Transcribers share one port. `WhisperCpp\WhisperCppTranscriber` posts the recording to a whisper.cpp server the site
runs on its own machines: nothing leaves the site. `OpenAi\OpenAiTranscriber` and `Deepgram\DeepgramTranscriber` send it
to another company, so a request without the consent of the people heard in it is refused before the file is opened,
as Quebec's Law 25 asks of personal information sent outside. No model runs in PHP.

It is a plain PHP library whose requirements are `ext-sodium`, `phil/timed-text` and
`symfony/http-client-contracts`: any HTTP client of the contracts sends the requests, and no SDK is needed.

## Status

| Part | State |
|---|---|
| `Transcript`, `Segment`, `Language`, `ReviewState`: segments in a declared language, to review before publication | Implemented and tested |
| `TranscriptionRequest`, `RecordingConsent`, `Processing`, `Transcription`: consent asked before a recording leaves | Implemented and tested |
| `WhisperCpp\WhisperCppTranscriber`: a whisper.cpp server, `verbose_json` | Implemented and tested against a mock client |
| `OpenAi\OpenAiTranscriber`: OpenAI's transcription API or a server that speaks it | Implemented and tested against a mock client |
| `Deepgram\DeepgramTranscriber`: Deepgram's pre-recorded API, utterances and speakers | Implemented and tested against a mock client |
| `Reader\VerboseJsonReader`, `Reader\DeepgramReader`: the responses, bounded | Implemented, tested and fuzzed |
| Asynchronous services that are polled — AssemblyAI, Rev | Not started: an adapter of the application |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, a whisper.cpp server. |
| [Transcribers](doc/transcribers.md) | The port, the three adapters, what each sends and reads, the limits. |
| [Transcripts](doc/transcripts.md) | Segments, the track for an editor, the review, publishing in each format. |
| [Privacy and accessibility](doc/privacy-and-accessibility.md) | Consent, what leaves the site, the declared language, captions and transcripts. |
| [Extension points](doc/extension-points.md) | The contract an application implements. |
| [Testing](doc/testing.md) | The tests, the fuzz targets, the services in integration. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

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

## Requirements

PHP 8.5 or later, with `ext-sodium`; an HTTP client of `symfony/http-client-contracts`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the [code of
conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md) explains, never in
a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
