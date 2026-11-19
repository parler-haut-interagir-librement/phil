<?php

declare(strict_types=1);

namespace Phil\EventWeather\Internal;

use JsonException;
use Phil\EventWeather\Contact;
use Phil\EventWeather\Exception\WeatherUnavailable;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * One GET of a provider's JSON, bounded: the provider's identification sent, the silence and the whole duration of
 * the request limited, the body read in chunks up to a number of bytes, the nesting of the JSON bounded. Not part of
 * the public API.
 *
 * @internal
 */
final readonly class JsonClient
{
    public const int MAX_BYTES = 8_388_608;

    public function __construct(
        private HttpClientInterface $client,
        private Contact $contact,
        private string $provider,
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws WeatherUnavailable when the provider does not answer, answers with an error, or sends what is not a JSON
     *                            object within the limits
     */
    public function get(string $url): array
    {
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => ['User-Agent' => $this->contact->userAgent(), 'Accept' => 'application/json, application/geo+json;q=0.9'],
                'timeout' => 10,
                'max_duration' => 20,
                'max_redirects' => 3,
            ]);
            $status = $response->getStatusCode();

            // 203 is MET Norway's answer for a product version about to be retired: the data is still good.
            if (200 !== $status && 203 !== $status) {
                $response->cancel();

                throw WeatherUnavailable::status($this->provider, $status);
            }

            $body = '';

            foreach ($this->client->stream($response) as $chunk) {
                $body .= $chunk->getContent();

                if (\strlen($body) > $this->maxBytes) {
                    $response->cancel();

                    throw WeatherUnavailable::tooLarge($this->provider, $this->maxBytes);
                }
            }
        } catch (ExceptionInterface $exception) {
            throw WeatherUnavailable::transport($this->provider, $exception);
        }

        try {
            $data = json_decode($body, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw WeatherUnavailable::format($this->provider, 'not JSON', $jsonException);
        }

        if (!\is_array($data)) {
            throw WeatherUnavailable::format($this->provider, 'not a JSON object');
        }

        return $data;
    }
}
