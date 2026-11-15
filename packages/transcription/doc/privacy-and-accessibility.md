# Privacy and accessibility

## The consent of the people heard

A recording holds the voices of people, and what they say: personal information. Sending it to a service of another
company is sending it outside the site, which Quebec's Law 25 allows with their consent and an assessment of the
service. The library makes the consent part of the request:

- `RecordingConsent::LocalOnly`, the default: only a transcriber whose processing is `Local` may transcribe it;
- `RecordingConsent::Cloud`: the people heard agreed that it leaves the site.

A cloud transcriber refuses a request without that consent before it opens the file, and `Transcription` refuses it
before any transcriber sees it. Recording the consent — who agreed, when, to which service — is the application's.

## What leaves the site

| Transcriber | What leaves |
|---|---|
| `WhisperCppTranscriber` | Nothing: the server runs on the site's machines |
| `OpenAiTranscriber` | The recording, the hint, the language, to OpenAI |
| `DeepgramTranscriber` | The recording, the hint, the language, to Deepgram |

The answer of a service that fails is never quoted in an exception, so that a log keeps no transcript by accident. The
keys are `#[SensitiveParameter]`.

## A review before publication

A machine mishears names, accents and terms. A transcript is published only once a person reviewed it, so that a
caption or a transcript does not put words in a speaker's mouth.

## Accessibility

Captions serve those who do not hear the sound (WCAG 1.2.2); a transcript serves those who neither see nor hear it
(WCAG 1.2.1 and 1.2.8). The declared language — `fr-CA` rather than a guess — is the `lang` of the transcript a page
shows, so that a screen reader reads it with the right voice.
