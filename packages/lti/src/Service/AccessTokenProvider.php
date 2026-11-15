<?php

declare(strict_types=1);

namespace Phil\Lti\Service;

use JsonException;
use Phil\Lti\Contract\KeyStore;
use Phil\Lti\Contract\ServiceClient;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Security\Random;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The access tokens of the tool for the services of a platform: the grant `client_credentials`, the tool authenticated
 * by a JWT signed with its key (RFC 7523) — `iss` and `sub` the client, `aud` the token endpoint, a fresh `jti`, five
 * minutes of life —, the token kept in a cache per platform and set of scopes until shortly before it expires.
 */
final readonly class AccessTokenProvider
{
    public const string ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    /**
     * The seconds a token is dropped from the cache before it expires, so that it never expires on its way.
     */
    public const int MARGIN = 30;

    public function __construct(
        private ServiceClient $client,
        private KeyStore $keys,
        private CacheInterface $cache,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $scopes
     *
     * @throws ServiceFailed when the token endpoint refuses, or answers something that is not a bearer token
     */
    public function token(PlatformRegistration $platform, array $scopes): string
    {
        $scopes = array_values(array_unique($scopes));
        sort($scopes);
        $key = 'phil_lti.token.'.hash('xxh128', $platform->key()."\n".implode(' ', $scopes));

        return $this->cache->get($key, fn (ItemInterface $item): string => $this->request($platform, $scopes, $item));
    }

    /**
     * @param list<string> $scopes
     */
    private function request(PlatformRegistration $platform, array $scopes, ItemInterface $item): string
    {
        $now = $this->clock->now()->getTimestamp();
        $assertion = JwtSigner::sign([
            'iss' => $platform->clientId,
            'sub' => $platform->clientId,
            'aud' => $platform->audience(),
            'iat' => $now,
            'exp' => $now + 300,
            'jti' => Random::token(),
        ], $this->keys->signingKey());

        $response = $this->client->send(new ServiceRequest('POST', $platform->tokenUrl, ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'], http_build_query([
            'grant_type' => 'client_credentials',
            'client_assertion_type' => self::ASSERTION_TYPE,
            'client_assertion' => $assertion,
            'scope' => implode(' ', $scopes),
        ], '', '&', \PHP_QUERY_RFC3986)));

        if (!$response->isSuccessful()) {
            throw ServiceFailed::because(\sprintf('The token endpoint of "%s" answered %d.', $platform->issuer, $response->status), status: $response->status);
        }

        try {
            $body = json_decode($response->body, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw ServiceFailed::because('The token endpoint answered something that is not JSON.', $jsonException);
        }

        $token = \is_array($body) ? $body['access_token'] ?? null : null;
        $type = \is_array($body) ? $body['token_type'] ?? null : null;
        $expiresIn = \is_array($body) ? $body['expires_in'] ?? 3600 : null;

        if (!\is_string($token) || '' === $token || !\is_string($type) || 'bearer' !== strtolower($type) || !\is_int($expiresIn)) {
            throw ServiceFailed::because('The token endpoint did not answer with a bearer token and its lifetime.');
        }

        $item->expiresAfter(max(1, $expiresIn - self::MARGIN));

        return $token;
    }
}
