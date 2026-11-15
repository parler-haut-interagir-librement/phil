<?php

declare(strict_types=1);

namespace Phil\Transcription\OpenAi;

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
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI's audio transcription API, or a server that speaks it: the recording leaves the site, so a request without the
 * consent of the people heard in it is refused before the file is opened. The response is read as `verbose_json`,
 * timed by segment. The hosted API takes 25 MB at most: the default limit of the recording.
 */
final readonly class OpenAiTranscriber implements Transcriber
{
    public const string ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';

    public const int MAX_AUDIO_BYTES = 26_214_400;

    private Http $http;

    private string $endpoint;

    /**
     * @param string $endpoint the address of the API, the service's own by default, set by the operator of the site
     *
     * @throws InvalidTranscription when the address is not `http` or `https`
     */
    public function __construct(
        HttpClientInterface $client,
        #[SensitiveParameter]
        private string $apiKey,
        private string $model = 'whisper-1',
        string $endpoint = self::ENDPOINT,
        private TranscriptionLimits $limits = new TranscriptionLimits(maxAudioBytes: self::MAX_AUDIO_BYTES),
    ) {
        $this->http = new Http($client, $limits);
        $this->endpoint = Http::address($endpoint);
    }

    #[Override]
    public function processing(): Processing
    {
        return Processing::Cloud;
    }

    #[Override]
    public function transcribe(TranscriptionRequest $request): Transcript
    {
        $request->assertAllows(Processing::Cloud);
        $file = $this->http->open($request->path);

        try {
            $fields = [
                'model' => $this->model,
                'language' => $request->language->primary(),
                'response_format' => 'verbose_json',
                'timestamp_granularities[]' => 'segment',
            ];

            if (null !== $request->prompt) {
                $fields['prompt'] = $request->prompt;
            }

            $body = new Multipart($fields, $file, $request->path);
            $json = $this->http->send('POST', $this->endpoint, [
                'auth_bearer' => $this->apiKey,
                'headers' => ['Content-Type' => $body->contentType()],
                'body' => $body->body(),
            ]);
        } finally {
            fclose($file);
        }

        return new VerboseJsonReader($this->limits)->read($json, $request->language, 'openai:'.$this->model);
    }
}
