<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Http;

use Phil\VirtualClassroom\Exception\InvalidResponse;
use Phil\VirtualClassroom\Exception\ProviderRefused;
use Phil\VirtualClassroom\Exception\ProviderUnavailable;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A GET within a budget: no redirect followed, the silence and the whole call bounded, the answer read chunk by chunk
 * and cut past its ceiling. A failure names the call by its label, never by its address, which carries a checksum.
 *
 * @internal
 */
final readonly class BoundedHttp
{
    public function __construct(
        private HttpClientInterface $client,
        private HttpBudget $budget,
    ) {
    }

    /**
     * @throws ProviderUnavailable when the server cannot be reached in time, or answers with a server error
     * @throws ProviderRefused     when the server answers with a client error or a redirect
     * @throws InvalidResponse     when the answer is larger than the budget
     */
    public function get(string $url, string $label): string
    {
        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => $this->budget->timeout,
                'max_duration' => $this->budget->maxDuration,
                'max_redirects' => 0,
                'headers' => ['Accept' => 'application/xml, application/json'],
            ]);
            $status = $response->getStatusCode();

            if ($status >= 500) {
                throw ProviderUnavailable::because(\sprintf('The call "%s" was answered with the server error %d.', $label, $status));
            }

            if ($status >= 300) {
                throw ProviderRefused::because(\sprintf('The call "%s" was answered with the status %d.', $label, $status));
            }

            $body = '';

            foreach ($this->client->stream($response) as $chunk) {
                $body .= $chunk->getContent();

                if (\strlen($body) > $this->budget->maxBytes) {
                    $response->cancel();

                    throw InvalidResponse::because(\sprintf('The answer to "%s" is larger than %d bytes.', $label, $this->budget->maxBytes));
                }
            }

            return $body;
        } catch (ExceptionInterface) {
            throw ProviderUnavailable::because(\sprintf('The call "%s" could not be completed in time.', $label));
        }
    }
}
