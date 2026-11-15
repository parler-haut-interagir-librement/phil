<?php

declare(strict_types=1);

namespace Phil\Lti\Key;

use Override;
use Phil\Lti\Contract\KeySetSource;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Http\SafeHttpServiceClient;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads key sets through `phil/safe-http`: HTTPS only, private networks refused, redirects checked, time and bytes
 * bounded. Each call reads the set again: wrap it in `CachedKeySetSource`.
 */
final readonly class SafeHttpKeySetSource implements KeySetSource, ResetInterface
{
    private SafeHttpClient $client;

    public function __construct(?SafeHttpClient $client = null)
    {
        $this->client = $client ?? new SafeHttpClient(limits: self::defaultLimits());
    }

    /**
     * The limits of the default client: 5 seconds of silence, 10 seconds in all, the bytes of a key set, 2 redirects.
     */
    public static function defaultLimits(): Limits
    {
        return new Limits(timeout: 5.0, maxDuration: 10.0, maxBytes: KeySet::MAX_BYTES, maxRedirects: 2, userAgent: SafeHttpServiceClient::USER_AGENT);
    }

    #[Override]
    public function keySet(Url $url, bool $refresh = false): KeySet
    {
        if ('https' !== $url->scheme) {
            throw ServiceFailed::because(\sprintf('A key set is read over HTTPS; "%s" is not.', $url->toString()));
        }

        try {
            $response = $this->client->get($url, new ReadRule('application/jwk-set+json, application/json;q=0.9'));
        } catch (SafeHttpException $safeHttpException) {
            throw ServiceFailed::because(\sprintf('The key set at "%s" cannot be read: %s', $url->toString(), $safeHttpException->getMessage()), $safeHttpException);
        }

        if (200 !== $response->status) {
            throw ServiceFailed::because(\sprintf('The key set at "%s" answered %d.', $url->toString(), $response->status), status: $response->status);
        }

        if (!$response->complete || 'https' !== $response->url->scheme) {
            throw ServiceFailed::because(\sprintf('The key set at "%s" is too large, or was redirected away from HTTPS.', $url->toString()));
        }

        return KeySet::fromJson($response->body);
    }

    #[Override]
    public function reset(): void
    {
        $this->client->reset();
    }
}
