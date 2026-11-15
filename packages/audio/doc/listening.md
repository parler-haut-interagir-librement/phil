# Listening

## A track

`Audio` is a track as a player shows it:

| Property | What it holds |
|---|---|
| `title` | Its title, never blank |
| `sources` | Its files, `AudioSource`s, in the order a browser tries them |
| `policy` | The creator's `ListeningPolicy`; the whole track by default |
| `duration` | In milliseconds, when known |
| `peaks` | Its waveform |
| `chapters` | A `ChapterList` of phil/timed-text |
| `transcripts` | `AudioTranscript`s: a `Track` of phil/timed-text, a BCP 47 language, a label, the address of its file |

An `AudioSource` is an address read by phil/url, an `AudioFormat`, and whether the file holds only the excerpt.

## The listening policy

The creator chooses what a listener who has not bought a track may hear:

| Policy | Before a purchase | After |
|---|---|---|
| `ListeningPolicy::full()` | The whole track | The whole track |
| `ListeningPolicy::excerpt(new Excerpt(30_000, 30_000))` | The excerpt | The whole track |
| `ListeningPolicy::purchaseOnly()` | Nothing | The whole track |

`forListener($entitled)` says which; `$entitled` is the application's answer — a purchase, a subscription, the creator
themselves. `Audio::playable($entitled)` gives the files that listener's player may fetch: the whole files, the files of
the excerpt, or none.

The policy decides what the page offers. It does not protect the whole file: an application that sells tracks serves
the whole files only to entitled listeners — signed addresses, a controller that checks — and makes the excerpt a file
of its own, which `transcode()` with an `Excerpt` produces.

## Chapters and transcripts

The chapters and the transcripts are those of phil/timed-text: read from WebVTT, SRT, Podcasting 2.0 JSON or HTML,
written back in any of them, shifted and cut in integer milliseconds. phil/transcription produces a transcript as a
`Track` to review.
