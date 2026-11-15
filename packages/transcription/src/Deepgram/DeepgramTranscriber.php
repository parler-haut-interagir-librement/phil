<?php

declare(strict_types=1);

namespace Phil\Transcription\Deepgram;

use Override;
use Phil\Transcription\Contract\Transcriber;
use Phil\Transcription\Exception\InvalidTranscription;
use Phil\Transcription\Internal\Http;
use Phil\Transcription\Processing;
use Phil\Transcription\Reader\DeepgramReader;
use Phil\Transcription\Transcript;
use Phil\Transcription\TranscriptionLimits;
use Phil\Transcription\TranscriptionRequest;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Deepgram's pre-recorded API: the recording leaves the site, so a request without the consent of the people heard in
 * it is refused before the file is opened. The recording is the body of the request, with the declared language in
 * full — `fr-CA` —, utterances asked, and speakers told apart when `diarize` is on.
 */
final readonly class DeepgramTranscriber implements Transcriber
{
    public const string ENDPOINT = 'https://api.deepgram.com/v1/listen';

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
        private string $model = 'nova-3',
        private bool $diarize = false,
        string $endpoint = self::ENDPOINT,
        TranscriptionLimits $limits = new TranscriptionLimits(),
        private DeepgramReader $reader = new DeepgramReader(),
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
        $query = [
            'model' => $this->model,
            'language' => $request->language->tag,
            'utterances' => 'true',
            'punctuate' => 'true',
            'smart_format' => 'true',
            'diarize' => $this->diarize ? 'true' : 'false',
        ];

        if (null !== $request->prompt) {
            $query['keyterm'] = $request->prompt;
        }

        try {
            $json = $this->http->send('POST', $this->endpoint, [
                'query' => $query,
                'headers' => ['Authorization' => 'Token '.$this->apiKey, 'Content-Type' => 'application/octet-stream'],
                'body' => Http::chunks($file),
            ]);
        } finally {
            fclose($file);
        }

        return $this->reader->read($json, $request->language, 'deepgram:'.$this->model);
    }
}
