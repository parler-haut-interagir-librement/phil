<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost;

use DateTimeImmutable;
use JsonException;
use Override;
use Phil\CommerceLogisticsBundle\Application\Port\CarrierGateway;
use Phil\CommerceLogisticsBundle\Domain\Carrier\Label;
use Phil\CommerceLogisticsBundle\Domain\Carrier\LabelRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateRequest;
use Phil\CommerceLogisticsBundle\Domain\Exception\CarrierRefused;
use Phil\Money\Money;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Canada Post's rates and labels, through its REST API in JSON on Symfony's HTTP client: an OAuth 2.0 access token
 * for the shop's developer application, the prices of the services for a parcel, and a shipment whose label is
 * fetched as a PDF.
 *
 * The access token is kept until shortly before it expires, and dropped between requests of a worker by `reset()`.
 * Links the carrier answers with are followed only to the API's own host, so the token never leaves for another.
 */
final class CanadaPostCarrier implements CarrierGateway, ResetInterface
{
    private const int MAX_JSON_BYTES = 1_048_576;

    private const int MAX_LABEL_BYTES = 5_242_880;

    /** A token is renewed this many seconds before the carrier says it expires. */
    private const int TOKEN_MARGIN = 60;

    private ?string $token = null;

    private ?DateTimeImmutable $tokenExpiresAt = null;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CanadaPostCredentials $credentials,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    #[Override]
    public function carrier(): string
    {
        return CanadaPostPayloads::CARRIER;
    }

    #[Override]
    public function rates(RateRequest $request): array
    {
        return CanadaPostPayloads::quotes($this->json('POST', '/prices', CanadaPostPayloads::rateRequest($request, $this->credentials)));
    }

    #[Override]
    public function label(LabelRequest $request): Label
    {
        $shipment = $this->json('POST', '/shipments', CanadaPostPayloads::labelRequest($request, $this->credentials));
        $tracking = CanadaPostPayloads::tracking($shipment);
        $cost = CanadaPostPayloads::shipmentPrice($shipment) ?? $this->price($shipment);
        $labelLink = CanadaPostPayloads::link($shipment, 'label') ?? throw CarrierRefused::unavailable($this->carrier(), 'its shipment has no label link.');

        return new Label($this->carrier(), $request->service, $tracking, $cost, $this->document($labelLink), 'application/pdf');
    }

    #[Override]
    public function reset(): void
    {
        $this->token = null;
        $this->tokenExpiresAt = null;
    }

    private function price(mixed $shipment): Money
    {
        $link = CanadaPostPayloads::link($shipment, 'price') ?? throw CarrierRefused::unavailable($this->carrier(), 'its shipment has neither a price nor a price link.');

        return CanadaPostPayloads::price($this->json('GET', $link));
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function json(string $method, string $path, ?array $body = null): mixed
    {
        $options = ['headers' => $this->headers('application/json')];

        if (null !== $body) {
            $options['json'] = $body;
        }

        $response = $this->send($method, $this->uri($path), $options);
        $status = $this->status($response);
        $content = $this->content($response, self::MAX_JSON_BYTES);

        try {
            $answer = json_decode($content, true, 32, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException $jsonException) {
            throw CarrierRefused::unavailable($this->carrier(), \sprintf('its answer is not JSON (HTTP %d).', $status), $jsonException);
        }

        if ($status >= 300) {
            throw CarrierRefused::unavailable($this->carrier(), \sprintf('HTTP %d, %s', $status, CanadaPostPayloads::messages($answer)));
        }

        return $answer;
    }

    private function document(string $link): string
    {
        $response = $this->send('GET', $this->uri($link), ['headers' => $this->headers('application/pdf')]);
        $status = $this->status($response);
        $document = $this->content($response, self::MAX_LABEL_BYTES);

        if (200 !== $status || !str_starts_with($document, '%PDF-')) {
            throw CarrierRefused::unavailable($this->carrier(), \sprintf('its label is not a PDF (HTTP %d).', $status));
        }

        return $document;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $accept): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token(),
            'Accept' => $accept,
            'Accept-Language' => $this->credentials->language,
        ];
    }

    private function token(): string
    {
        $now = $this->clock->now();

        if (null !== $this->token && $this->tokenExpiresAt instanceof DateTimeImmutable && $now < $this->tokenExpiresAt) {
            return $this->token;
        }

        $response = $this->send('POST', $this->credentials->tokenUri, [
            'auth_basic' => [$this->credentials->apiKey, $this->credentials->apiSecret],
            'headers' => ['Accept' => 'application/json'],
            'body' => ['grant_type' => 'client_credentials'],
        ]);
        $status = $this->status($response);
        $content = $this->content($response, self::MAX_JSON_BYTES);

        try {
            $answer = json_decode($content, true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw CarrierRefused::unavailable($this->carrier(), 'its token answer is not JSON.', $jsonException);
        }

        $token = \is_array($answer) ? ($answer['access_token'] ?? null) : null;
        $lifetime = \is_array($answer) ? ($answer['expires_in'] ?? null) : null;

        if (200 !== $status || !\is_string($token) || 1 !== preg_match('/^[\x21-\x7E]{1,4096}$/D', $token) || !\is_int($lifetime) || $lifetime < 1) {
            throw CarrierRefused::unavailable($this->carrier(), \sprintf('no access token was given (HTTP %d).', $status));
        }

        $this->token = $token;
        // A lifetime under the margin gives a moment already past: the token serves this request only.
        $this->tokenExpiresAt = $now->modify(\sprintf('%+d seconds', $lifetime - self::TOKEN_MARGIN));

        return $token;
    }

    /**
     * A path of the API, or a link it answered with, as long as it stays on the API's own scheme, host and port.
     *
     * The link is read by `phil/url`, as a browser reads it, and sent as it was read: a user name or a password before
     * the host refuses it, a backslash ends the host, and the default port is the port of no port.
     */
    private function uri(string $pathOrLink): string
    {
        if (str_starts_with($pathOrLink, '/')) {
            return rtrim($this->credentials->baseUri, '/').$pathOrLink;
        }

        try {
            $base = Url::fromString($this->credentials->baseUri);
            $link = Url::fromString($pathOrLink);
        } catch (InvalidUrl) {
            throw CarrierRefused::unavailable($this->carrier(), 'it linked to a host other than its API.');
        }

        if ('https' !== $link->scheme || $link->host !== $base->host || $link->port !== $base->port) {
            throw CarrierRefused::unavailable($this->carrier(), 'it linked to a host other than its API.');
        }

        return $link->toString();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function send(string $method, string $uri, array $options): ResponseInterface
    {
        try {
            return $this->client->request($method, $uri, $options + ['timeout' => 10, 'max_duration' => 30, 'max_redirects' => 0]);
        } catch (ExceptionInterface $exception) {
            throw CarrierRefused::unavailable($this->carrier(), 'the request could not be sent.', $exception);
        }
    }

    private function status(ResponseInterface $response): int
    {
        try {
            return $response->getStatusCode();
        } catch (ExceptionInterface $exception) {
            throw CarrierRefused::unavailable($this->carrier(), 'no answer came back.', $exception);
        }
    }

    /**
     * The body, read chunk by chunk and given up beyond the limit, so a large answer is never held whole. The status
     * is read first, which keeps the client from turning an error status into an exception.
     */
    private function content(ResponseInterface $response, int $limit): string
    {
        $content = '';

        try {
            foreach ($this->client->stream($response) as $chunk) {
                $content .= $chunk->getContent();

                if (\strlen($content) > $limit) {
                    $response->cancel();

                    throw CarrierRefused::unavailable($this->carrier(), 'its answer is too large.');
                }
            }
        } catch (ExceptionInterface $exception) {
            throw CarrierRefused::unavailable($this->carrier(), 'no answer came back.', $exception);
        }

        return $content;
    }
}
