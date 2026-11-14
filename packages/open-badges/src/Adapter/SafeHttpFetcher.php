<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Adapter;

use Override;
use Phil\OpenBadges\Contract\DocumentFetcher;
use Phil\OpenBadges\Did\DidWebMethods;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads DID documents through `phil/safe-http`: HTTPS only, private networks refused, every redirect checked, the time
 * and the bytes bounded — `DidWebMethods::MAX_BYTES` by default. A document is read only when it answers 200 and whole.
 */
final readonly class SafeHttpFetcher implements DocumentFetcher, ResetInterface
{
    public const string ACCEPT = 'application/did+json, application/did+ld+json;q=0.9, application/json;q=0.8';

    private SafeHttpClient $client;

    public function __construct(?SafeHttpClient $client = null)
    {
        $this->client = $client ?? new SafeHttpClient(limits: new Limits(timeout: 5.0, maxDuration: 10.0, maxBytes: DidWebMethods::MAX_BYTES, maxRedirects: 3, userAgent: 'PhilOpenBadges/0.1'));
    }

    #[Override]
    public function fetch(Url $url): string
    {
        if ('https' !== $url->scheme) {
            throw InvalidCredential::because(\sprintf('A DID document is read over HTTPS; "%s" is not.', $url->toString()));
        }

        try {
            $response = $this->client->get($url, new ReadRule(self::ACCEPT));
        } catch (SafeHttpException $safeHttpException) {
            throw InvalidCredential::because(\sprintf('The DID document at "%s" cannot be read: %s', $url->toString(), $safeHttpException->getMessage()));
        }

        if (200 !== $response->status) {
            throw InvalidCredential::because(\sprintf('The DID document at "%s" answered %d.', $url->toString(), $response->status));
        }

        if (!$response->complete) {
            throw InvalidCredential::because(\sprintf('The DID document at "%s" is larger than the limits allow.', $url->toString()));
        }

        if ('https' !== $response->url->scheme) {
            throw InvalidCredential::because(\sprintf('The DID document at "%s" was redirected away from HTTPS.', $url->toString()));
        }

        return $response->body;
    }

    #[Override]
    public function reset(): void
    {
        $this->client->reset();
    }
}
