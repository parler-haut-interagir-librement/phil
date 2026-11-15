# Extension points

The library is plain PHP and declares no service. What an application adds goes through one contract and the arguments
of the adapters.

## Contract

| Contract | Method | Contract |
|---|---|---|
| `Contract\Transcriber` | `processing(): Processing` | `Local` when nothing leaves the site, `Cloud` otherwise |
| | `transcribe(TranscriptionRequest): Transcript` | A transcript to review; refuses a request without consent when `Cloud`, by `$request->assertAllows()`; keeps no state between calls |

A service the library does not speak — an asynchronous one that is polled, a local model behind another server — is a
`Transcriber` of the application. Wrapping it in `Transcription` keeps the consent asked and the review required.

## Arguments

| Point | How |
|---|---|
| The address of a service | The server of `WhisperCppTranscriber`, the endpoint of the others |
| The model | `$model` of `OpenAiTranscriber` and `DeepgramTranscriber` |
| Speakers told apart | `$diarize` of `DeepgramTranscriber`; the label pattern of `DeepgramReader` |
| The bounds | `TranscriptionLimits`, given to each adapter and reader |
| Catching the library's errors | Every exception implements `Exception\TranscriptionException` |

## Stability

The contract, the model and the adapters are the public API, at 0.1: they may change in a minor version until 1.0. The
`Internal\` namespace — `Http`, `Multipart`, `Segments` — is not.
