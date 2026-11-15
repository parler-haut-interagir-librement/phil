<?php

declare(strict_types=1);

namespace Phil\Lti\Service;

use JsonException;
use Phil\Lti\Contract\ServiceClient;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Http\ServiceResponse;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Url\Url;

/**
 * A call of the tool to a service of a platform with an access token of the scope it needs, its JSON read.
 *
 * @internal
 */
final readonly class ServiceCall
{
    public const int MAX_DEPTH = 32;

    public function __construct(
        private ServiceClient $client,
        private AccessTokenProvider $tokens,
    ) {
    }

    /**
     * @param list<string>                 $scopes
     * @param array<array-key, mixed>|null $body
     */
    public function send(PlatformRegistration $platform, array $scopes, string $method, Url $url, string $accept, ?array $body = null, ?string $contentType = null): ServiceResponse
    {
        $headers = ['Authorization' => 'Bearer '.$this->tokens->token($platform, $scopes), 'Accept' => $accept];

        if (null !== $contentType) {
            $headers['Content-Type'] = $contentType;
        }

        $response = $this->client->send(new ServiceRequest($method, $url, $headers, null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)));

        if (!$response->isSuccessful()) {
            throw ServiceFailed::because(\sprintf('The service at "%s" answered %d.', $url->toString(), $response->status), status: $response->status);
        }

        return $response;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function json(ServiceResponse $response): array
    {
        try {
            $value = json_decode($response->body, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw ServiceFailed::because('The service answered something that is not JSON.', $jsonException);
        }

        return \is_array($value) ? $value : throw ServiceFailed::because('The service answered JSON that is neither an object nor a list.');
    }
}
