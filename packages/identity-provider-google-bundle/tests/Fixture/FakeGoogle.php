<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Fixture;

use OpenSSLAsymmetricKey;
use Phil\IdentityProviderGoogleBundle\Infrastructure\Http\SafeHttpTransport;
use Phil\SafeHttp\SafeHttpClient;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * A local Google, without a network: its discovery documents — fixtures shaped as Google publishes them —, a key set
 * of an RSA key made for the tests, a token endpoint that answers with the ID token the test chose, and the requests
 * it received. The host names are pinned to public addresses of the documentation range, so nothing is resolved.
 */
final class FakeGoogle
{
    public const string CLIENT_ID = '1234-test.apps.googleusercontent.com';

    public const string CLIENT_SECRET = 'a test secret';

    public const string SUBJECT = '110169484474386276334';

    public const string KEY_ID = 'test-key-1';

    /** 2026-11-20 12:00:00 UTC, the time of every test. */
    public const int NOW = 1_795_176_000;

    public const string DISCOVERY = 'https://accounts.google.com/.well-known/openid-configuration';

    public const string RISC_CONFIGURATION = 'https://accounts.google.com/.well-known/risc-configuration';

    public const string JWKS = 'https://www.googleapis.com/oauth2/v3/certs';

    public const string TOKEN = 'https://oauth2.googleapis.com/token';

    public const array RESOLVE = [
        'accounts.google.com' => '93.184.215.14',
        'oauth2.googleapis.com' => '93.184.215.15',
        'www.googleapis.com' => '93.184.215.16',
    ];

    private static ?OpenSSLAsymmetricKey $key = null;

    private static ?OpenSSLAsymmetricKey $otherKey = null;

    /** @var list<array{method: string, url: string, headers: list<string>, body: string}> */
    public array $requests = [];

    /** @var array<string, MockResponse> answers that replace those of an address */
    public array $answers = [];

    /** The ID token the token endpoint answers with; none when null. */
    public ?string $idToken = null;

    public function transport(): SafeHttpTransport
    {
        return new SafeHttpTransport(new SafeHttpClient(new MockHttpClient($this->respond(...)), SafeHttpTransport::limits(), self::RESOLVE));
    }

    /**
     * The claims of an ID token Google would sign for the nonce, at `NOW`.
     *
     * @param array<string, mixed> $overrides claims added or replaced; a null value removes the claim
     *
     * @return array<string, mixed>
     */
    public static function claims(string $nonce, array $overrides = []): array
    {
        $claims = [
            'iss' => 'https://accounts.google.com',
            'azp' => self::CLIENT_ID,
            'aud' => self::CLIENT_ID,
            'sub' => self::SUBJECT,
            'email' => 'ada.lovelace@gmail.com',
            'email_verified' => true,
            'nonce' => $nonce,
            'name' => 'Ada Lovelace',
            'iat' => self::NOW,
            'exp' => self::NOW + 3600,
        ];

        return array_filter(array_replace($claims, $overrides), static fn (mixed $value): bool => null !== $value);
    }

    /**
     * A token in the compact form, signed RS256 with the key of the key set — or with another key.
     *
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $header more of the protected header
     */
    public static function sign(array $claims, array $header = [], bool $otherKey = false): string
    {
        $input = self::encode(json_encode(['alg' => 'RS256', 'kid' => self::KEY_ID, 'typ' => 'JWT'] + $header, \JSON_THROW_ON_ERROR))
            .'.'.self::encode(json_encode($claims, \JSON_THROW_ON_ERROR));

        if (!openssl_sign($input, $signature, $otherKey ? self::otherKey() : self::key(), \OPENSSL_ALGO_SHA256) || !\is_string($signature)) {
            throw new RuntimeException('The test token could not be signed.');
        }

        return $input.'.'.self::encode($signature);
    }

    /**
     * @return array{keys: list<array<string, string>>}
     */
    public static function jwks(): array
    {
        $details = openssl_pkey_get_details(self::key());

        $modulus = \is_array($details) && \is_array($details['rsa'] ?? null) ? $details['rsa']['n'] ?? null : null;
        $exponent = \is_array($details) && \is_array($details['rsa'] ?? null) ? $details['rsa']['e'] ?? null : null;

        if (!\is_string($modulus) || !\is_string($exponent)) {
            throw new RuntimeException('The test key has no RSA details.');
        }

        return ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => self::KEY_ID,
            'n' => self::encode($modulus),
            'e' => self::encode($exponent),
        ]]];
    }

    public static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/'.$name);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function respond(string $method, string $url, array $options): MockResponse
    {
        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? '';
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => \is_array($headers) ? array_values(array_filter($headers, \is_string(...))) : [],
            'body' => \is_string($body) ? $body : '',
        ];

        return $this->answers[$url] ?? match ($url) {
            self::DISCOVERY => $this->json(self::fixture('google-openid-configuration.json')),
            self::RISC_CONFIGURATION => $this->json(self::fixture('google-risc-configuration.json')),
            self::JWKS => $this->json(json_encode(self::jwks(), \JSON_THROW_ON_ERROR)),
            self::TOKEN => $this->json(json_encode(array_filter([
                'access_token' => 'ya29.a-test-access-token',
                'expires_in' => 3599,
                'token_type' => 'Bearer',
                'scope' => 'openid https://www.googleapis.com/auth/userinfo.email',
                'id_token' => $this->idToken,
            ], static fn (mixed $value): bool => null !== $value), \JSON_THROW_ON_ERROR)),
            default => new MockResponse('{"error":"not_found"}', ['http_code' => 404, 'response_headers' => ['content-type: application/json']]),
        };
    }

    private function json(string $body): MockResponse
    {
        return new MockResponse($body, ['response_headers' => ['content-type: application/json; charset=utf-8', 'cache-control: public, max-age=3600']]);
    }

    private static function encode(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    private static function key(): OpenSSLAsymmetricKey
    {
        return self::$key ??= self::newKey();
    }

    private static function otherKey(): OpenSSLAsymmetricKey
    {
        return self::$otherKey ??= self::newKey();
    }

    private static function newKey(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);

        if (!$key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('The test key could not be made.');
        }

        return $key;
    }
}
