<?php

declare(strict_types=1);

namespace Phil\Transcription\Internal;

use Generator;
use Phil\Transcription\Exception\InvalidTranscription;
use Phil\Transcription\Exception\LimitExceeded;
use Phil\Transcription\Exception\TranscriptionFailed;
use Phil\Transcription\TranscriptionLimits;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What the transcribers over HTTP share: the recording opened within its bytes and streamed, never held whole in
 * memory, and the response read within its bytes, a status other than 2xx a failure that never quotes the body.
 *
 * @internal
 */
final readonly class Http
{
    private const int CHUNK = 1_048_576;

    public function __construct(
        private HttpClientInterface $client,
        private TranscriptionLimits $limits,
    ) {
    }

    /**
     * The address of a service, set by the operator of the site, without its trailing slashes: `http` or `https`, an
     * internal name or address allowed, since the site reaches its own servers.
     *
     * @throws InvalidTranscription when it is not `http` or `https`, or holds a space or a control character
     */
    public static function address(string $address): string
    {
        if (1 !== preg_match('#^https?://[^/]#i', $address) || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $address)) {
            throw new InvalidTranscription('The address of a transcription service is an http or https address.');
        }

        return rtrim($address, '/');
    }

    /**
     * @return resource
     *
     * @throws InvalidTranscription when the file cannot be read
     * @throws LimitExceeded        when it is larger than allowed
     */
    public function open(string $path): mixed
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidTranscription('The recording cannot be read.');
        }

        if (filesize($path) > $this->limits->maxAudioBytes) {
            throw new LimitExceeded(\sprintf('The recording holds more than %d bytes.', $this->limits->maxAudioBytes));
        }

        $file = fopen($path, 'r');

        if (false === $file) {
            throw new InvalidTranscription('The recording cannot be read.');
        }

        return $file;
    }

    /**
     * The chunks of a file, read to its end.
     *
     * @param resource $file
     *
     * @return Generator<int, string>
     */
    public static function chunks(mixed $file): Generator
    {
        while (!feof($file)) {
            $chunk = fread($file, self::CHUNK);

            if (false === $chunk || '' === $chunk) {
                return;
            }

            yield $chunk;
        }
    }

    /**
     * @param array<string, mixed> $options the options of the request, the timeout added
     *
     * @throws TranscriptionFailed when the service cannot be reached or answers other than 2xx
     * @throws LimitExceeded       when the response is longer than allowed
     */
    public function send(string $method, string $url, array $options): string
    {
        try {
            $response = $this->client->request($method, $url, $options + ['max_duration' => $this->limits->timeout]);
            $status = $response->getStatusCode();

            if ($status < 200 || $status >= 300) {
                $response->cancel();

                throw new TranscriptionFailed(\sprintf('The transcription service answered HTTP %d.', $status));
            }

            $body = '';

            foreach ($this->client->stream($response) as $chunk) {
                $body .= $chunk->getContent();

                if (\strlen($body) > $this->limits->maxResponseBytes) {
                    $response->cancel();

                    throw new LimitExceeded(\sprintf('The response holds more than %d bytes.', $this->limits->maxResponseBytes));
                }
            }

            return $body;
        } catch (ExceptionInterface $exception) {
            throw new TranscriptionFailed('The transcription service cannot be reached: '.$exception->getMessage(), 0, $exception);
        }
    }
}
