# Transcribers

## The port

```php
interface Transcriber
{
    public function processing(): Processing;                       // Local or Cloud
    public function transcribe(TranscriptionRequest $request): Transcript;
}
```

A `TranscriptionRequest` is the path of a local file, its declared `Language`, the `RecordingConsent` of the people
heard in it — `LocalOnly` by default, `Cloud` once they agreed that it leaves the site —, and a hint of 1000 bytes at
most: names and terms the service may expect.

`Transcription` is the transcriber an application calls: it asks the consent before the transcriber it wraps sees the
request, and gives back every transcript to review, whatever the transcriber said.

## The adapters

| Adapter | Processing | Request | Response |
|---|---|---|---|
| `WhisperCpp\WhisperCppTranscriber` | Local | `POST <server>/inference`, multipart: `file`, `response_format=verbose_json`, `language` (the primary language, `fr`), `temperature=0`, `prompt` | `segments` with `start`, `end` in seconds and `text` |
| `OpenAi\OpenAiTranscriber` | Cloud | `POST https://api.openai.com/v1/audio/transcriptions`, bearer key, multipart: `file`, `model` (`whisper-1` by default), `language`, `response_format=verbose_json`, `timestamp_granularities[]=segment`, `prompt` | the same |
| `Deepgram\DeepgramTranscriber` | Cloud | `POST https://api.deepgram.com/v1/listen?model=nova-3&language=fr-CA&utterances=true&punctuate=true&smart_format=true&diarize=…&keyterm=…`, `Authorization: Token <key>`, the recording as the body | `results.utterances` with `start`, `end`, `transcript`, `confidence`, `speaker` |

The recording is streamed, a mebibyte at a time, never held whole in memory. Whisper takes the primary language of the
tag; Deepgram takes the tag in full. The language declared in the request is the language of the transcript, whatever
the service detected. Another endpoint — a server that speaks OpenAI's API, a region of Deepgram — is given to the
constructor; it is the operator's to set.

Deepgram names its speakers by number from 0; `DeepgramReader` writes them `Speaker 1`, `Speaker 2`…, or after the
pattern it is given.

## The limits

`TranscriptionLimits` bounds each transcription:

| Limit | Default | When it is passed |
|---|---|---|
| `maxAudioBytes` | 1 GiB; 25 MiB for `OpenAiTranscriber`, the API's own limit | The recording is refused before it is opened |
| `maxResponseBytes` | 16 MiB | The response is cut and refused |
| `maxSegments` | 50 000 | The transcript is refused |
| `maxTextBytes` | 8 KiB | A segment longer is refused |
| `timeout` | 3600 seconds | The request is abandoned |

A segment the service could not time — no number, negative, past the last instant of phil/timed-text, an end before
its start — or left blank is skipped.

## Errors

| Exception | When |
|---|---|
| `ConsentRequired` | A cloud transcriber, a request without consent: nothing is opened or sent |
| `InvalidTranscription` | A value refused, a recording that cannot be read, an address that is not `http` or `https` |
| `LimitExceeded` | One of the limits above |
| `TranscriptionFailed` | The service cannot be reached, answers other than 2xx — its body is never quoted —, or answers what is no transcript |
| `NotReviewed` | A transcript published before a person reviewed it |

All implement `Exception\TranscriptionException`.
