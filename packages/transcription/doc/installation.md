# Installation

```sh
composer require phil/transcription
```

| Requirement | Why |
|---|---|
| PHP 8.5 or later, with `ext-sodium` | Readonly classes; the random boundary of a multipart body, written without timing leaks |
| `phil/timed-text` | A transcript becomes a `Track`, written in every format of the library |
| `symfony/http-client-contracts` | The transcribers send their requests through any `HttpClientInterface` |

An HTTP client implements the contracts: `symfony/http-client`, or a client of the application.

## A whisper.cpp server

whisper.cpp (MIT) runs the Whisper models on the CPU or the GPU of a machine of the site, and answers HTTP:

```sh
./build/bin/whisper-server --model models/ggml-large-v3-turbo.bin --host 0.0.0.0 --port 8080
```

`WhisperCppTranscriber` posts to its `/inference` endpoint. The address is the operator's to set — an internal name
such as `http://whisper:8080`, or an address of the private network —; it is never one a visitor gives.

## Cloud services

`OpenAiTranscriber` and `DeepgramTranscriber` take the key of the account, which the application keeps as a secret.
They are refused for a recording without the consent of the people heard in it.
