<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Http;

use Override;
use Phil\SvgBlocks\Contract\MermaidService;
use Phil\SvgBlocks\Exception\InvalidConfiguration;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The diagram service over HTTP, the Node.js service that the bundle ships as a container image: `POST /render` with
 * `{"code": text}` answers `image/svg+xml`, or `422` with `{"error": text}` when Mermaid refuses the diagram.
 *
 * The call has a deadline, follows no redirect, and reads at most `$maxBytes` of the answer.
 */
final readonly class HttpMermaidService implements MermaidService
{
    /**
     * @param string $endpoint the address of the service, such as `http://mermaid:3000`
     * @param float  $timeout  seconds before the call is given up
     * @param int    $maxBytes the largest answer read
     *
     * @throws InvalidConfiguration when the address is not HTTP, or a limit is not positive
     */
    public function __construct(
        private HttpClientInterface $client,
        private string $endpoint,
        private float $timeout = 10.0,
        private int $maxBytes = 1_048_576,
    ) {
        if (1 !== preg_match('#^https?://[^\s/?\#]+(?:/[^\s?\#]*)?$#D', $endpoint)) {
            throw InvalidConfiguration::of('address of the diagram service', 'an http:// or https:// address without query');
        }

        if ($timeout <= 0.0 || $maxBytes < 1) {
            throw InvalidConfiguration::of('timeout and the largest answer of the diagram service', 'positive');
        }
    }

    #[Override]
    public function toSvg(string $code): string
    {
        try {
            $response = $this->client->request('POST', rtrim($this->endpoint, '/').'/render', [
                'json' => ['code' => $code],
                'headers' => ['Accept' => 'image/svg+xml'],
                'timeout' => $this->timeout,
                'max_duration' => $this->timeout,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
            $type = $response->getHeaders(false)['content-type'][0] ?? '';
            $body = '';

            foreach ($this->client->stream($response) as $chunk) {
                $body .= $chunk->getContent();

                if (\strlen($body) > $this->maxBytes) {
                    $response->cancel();

                    throw RenderingFailed::because(\sprintf('The diagram is larger than %d bytes.', $this->maxBytes));
                }
            }
        } catch (ExceptionInterface $exception) {
            throw RenderingFailed::because('The diagram service did not answer: '.$exception->getMessage(), $exception);
        }

        if (422 === $status) {
            $error = json_decode($body, true);

            throw RenderingFailed::because('Mermaid refuses the diagram: '.(\is_array($error) && \is_string($error['error'] ?? null) ? mb_substr($error['error'], 0, 500) : 'no reason given').'.');
        }

        if (200 !== $status || !str_starts_with($type, 'image/svg+xml')) {
            throw RenderingFailed::because(\sprintf('The diagram service answered %d %s instead of an SVG image.', $status, '' === $type ? 'without a type' : $type));
        }

        return $body;
    }
}
