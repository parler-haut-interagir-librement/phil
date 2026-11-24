<?php

declare(strict_types=1);

namespace Phil\SamlSp\Http;

use Override;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SamlSp\Contract\MetadataFetcher;
use Phil\SamlSp\Exception\MetadataUnavailable;
use Phil\Url\Url;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Fetches metadata through phil/safe-http: HTTPS only, private networks refused, redirects checked, 5 seconds of
 * silence and 15 in all, 4 MiB at most.
 */
final readonly class SafeHttpMetadataFetcher implements MetadataFetcher, ResetInterface
{
    public const string USER_AGENT = 'PhilSamlSp/0.1';

    private SafeHttpClient $client;

    public function __construct(?SafeHttpClient $client = null)
    {
        $this->client = $client ?? new SafeHttpClient(limits: self::defaultLimits());
    }

    public static function defaultLimits(): Limits
    {
        return new Limits(timeout: 5.0, maxDuration: 15.0, maxBytes: 4 * 1024 * 1024, maxRedirects: 3, userAgent: self::USER_AGENT);
    }

    #[Override]
    public function fetch(Url $url): string
    {
        if ('https' !== $url->scheme) {
            throw MetadataUnavailable::because(\sprintf('Metadata is fetched over HTTPS; "%s" is not.', $url->toString()));
        }

        try {
            $response = $this->client->get($url, new ReadRule('application/samlmetadata+xml, application/xml;q=0.9, text/xml;q=0.8'));
        } catch (SafeHttpException $safeHttpException) {
            throw MetadataUnavailable::because(\sprintf('The metadata at "%s" cannot be fetched: %s', $url->toString(), $safeHttpException->getMessage()), $safeHttpException);
        }

        if (200 !== $response->status || !$response->complete || 'https' !== $response->url->scheme) {
            throw MetadataUnavailable::because(\sprintf('The metadata at "%s" answered %d, was too large, or was redirected away from HTTPS.', $url->toString(), $response->status));
        }

        return $response->body;
    }

    #[Override]
    public function reset(): void
    {
        $this->client->reset();
    }
}
