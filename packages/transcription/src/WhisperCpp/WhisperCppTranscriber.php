<?php

declare(strict_types=1);

namespace Phil\Transcription\WhisperCpp;

use Override;
use Phil\Transcription\Contract\Transcriber;
use Phil\Transcription\Exception\InvalidTranscription;
use Phil\Transcription\Internal\Http;
use Phil\Transcription\Internal\Multipart;
use Phil\Transcription\Processing;
use Phil\Transcription\Reader\VerboseJsonReader;
use Phil\Transcription\Transcript;
use Phil\Transcription\TranscriptionLimits;
use Phil\Transcription\TranscriptionRequest;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The server of whisper.cpp, run by the site on its own machines: the recording is posted to its `/inference` endpoint
 * with the declared language and read back as `verbose_json`. Nothing leaves the site, so the consent of the people
 * heard is not needed; the model runs in that server, never in PHP.
 */
final readonly class WhisperCppTranscriber implements Transcriber
{
    private Http $http;

    private string $server;

    /**
     * @param string $server the address of the server, set by the operator of the site — `http://whisper:8080`, an
     *                       internal name or address —; its path, when it has one, is the prefix of `/inference`
     *
     * @throws InvalidTranscription when the address is not `http` or `https`
     */
    public function __construct(
        HttpClientInterface $client,
        string $server,
        private TranscriptionLimits $limits = new TranscriptionLimits(),
    ) {
        $this->http = new Http($client, $limits);
        $this->server = Http::address($server);
    }

    #[Override]
    public function processing(): Processing
    {
        return Processing::Local;
    }

    #[Override]
    public function transcribe(TranscriptionRequest $request): Transcript
    {
        $file = $this->http->open($request->path);

        try {
            $fields = ['response_format' => 'verbose_json', 'language' => $request->language->primary(), 'temperature' => '0'];

            if (null !== $request->prompt) {
                $fields['prompt'] = $request->prompt;
            }

            $body = new Multipart($fields, $file, $request->path);
            $json = $this->http->send('POST', $this->server.'/inference', [
                'headers' => ['Content-Type' => $body->contentType()],
                'body' => $body->body(),
            ]);
        } finally {
            fclose($file);
        }

        return new VerboseJsonReader($this->limits)->read($json, $request->language, 'whisper.cpp');
    }
}
