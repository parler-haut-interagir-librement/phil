<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional\App;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Clock\ClockInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A local OpenID Connect provider behind a mock HTTP client: discovery, keys, and a token endpoint that rotates
 * refresh tokens and revokes the family when one is presented twice.
 */
final class FakeOidcProvider
{
    public const string ISSUER = 'https://id.example.test';

    public const string CLIENT_ID = 'example-site';

    public const string SESSION_ID = 'provider-session';

    private static ?JWK $key = null;

    /** @var list<array{grant: string}> */
    public array $tokenRequests = [];

    /** @var array<string, string> */
    private array $codes = [];

    /** @var array<string, array<string, mixed>> */
    private array $codeClaims = [];

    /** @var array<string, bool> refresh tokens: true while valid */
    private array $refreshTokens = [];

    private int $serial = 0;

    public bool $tokenEndpointDown = false;

    /** @var (callable(string): string)|null */
    public $idTokenForger;

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public static function key(): JWK
    {
        return self::$key ??= JWKFactory::createRSAKey(2048, ['use' => 'sig', 'kid' => bin2hex(random_bytes(8))]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        return match ($url) {
            self::ISSUER.'/.well-known/openid-configuration' => $this->json([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER.'/authorize',
                'token_endpoint' => self::ISSUER.'/token',
                'userinfo_endpoint' => self::ISSUER.'/userinfo',
                'jwks_uri' => self::ISSUER.'/jwks',
                'end_session_endpoint' => self::ISSUER.'/logout',
            ]),
            self::ISSUER.'/jwks' => $this->json(['keys' => [self::key()->toPublic()->jsonSerialize()]]),
            self::ISSUER.'/userinfo' => $this->json(['sub' => 'subject-1', 'name' => 'From Userinfo']),
            self::ISSUER.'/token' => $this->token($options),
            default => throw new RuntimeException('Unexpected request to '.$url),
        };
    }

    /**
     * Answers an authorization request and returns the local callback address.
     *
     * @param array<string, mixed> $claims claims of the ID token, over the defaults
     */
    public function authorize(string $authorizationUrl, array $claims = []): string
    {
        $parameters = self::query($authorizationUrl);
        $code = 'code-'.++$this->serial;
        $this->codes[$code] = \is_string($parameters['nonce'] ?? null) ? $parameters['nonce'] : '';
        $this->codeClaims[$code] = $claims;

        return '/oidc/callback?'.http_build_query(['code' => $code, 'state' => $parameters['state'] ?? '']);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function query(string $url): array
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $parameters);

        return $parameters;
    }

    /**
     * @param array<string, mixed> $claims claims over the defaults; null removes one
     */
    public function logoutToken(array $claims = []): string
    {
        $now = $this->clock->now()->getTimestamp();

        return self::sign(array_filter($claims + [
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'iat' => $now,
            'exp' => $now + 120,
            'jti' => bin2hex(random_bytes(8)),
            'sid' => self::SESSION_ID,
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => new stdClass()],
        ], static fn (mixed $value): bool => null !== $value));
    }

    /**
     * @param array<string, mixed> $claims
     */
    public static function sign(array $claims, string $algorithm = 'RS256'): string
    {
        $key = self::key();
        $signature = 'RS256' === $algorithm ? new RS256() : new RS512();
        $jws = new JWSBuilder(new AlgorithmManager([$signature]))
            ->create()
            ->withPayload(json_encode($claims, \JSON_THROW_ON_ERROR))
            ->addSignature($key, ['alg' => $algorithm, 'kid' => $key->get('kid')])
            ->build();

        return new CompactSerializer()->serialize($jws, 0);
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $claims
     */
    public static function encode(array $header, array $claims, string $signature = ''): string
    {
        return self::b64(json_encode($header, \JSON_THROW_ON_ERROR)).'.'.self::b64(json_encode($claims, \JSON_THROW_ON_ERROR)).'.'.self::b64($signature);
    }

    public static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function token(array $options): MockResponse
    {
        if ($this->tokenEndpointDown) {
            return new MockResponse('', ['http_code' => 503]);
        }

        $body = \is_string($options['body'] ?? null) ? $options['body'] : '';
        parse_str($body, $form);
        $grant = \is_string($form['grant_type'] ?? null) ? $form['grant_type'] : '';
        $this->tokenRequests[] = ['grant' => $grant];

        return 'refresh_token' === $grant ? $this->rotate($form) : $this->exchange($form);
    }

    /**
     * @param array<array-key, mixed> $form
     */
    private function exchange(array $form): MockResponse
    {
        $code = \is_string($form['code'] ?? null) ? $form['code'] : '';
        $now = $this->clock->now()->getTimestamp();
        $claims = array_filter(($this->codeClaims[$code] ?? []) + [
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'subject-1',
            'iat' => $now,
            'exp' => $now + 300,
            'nonce' => $this->codes[$code] ?? '',
            'auth_time' => $now,
            'sid' => self::SESSION_ID,
        ], static fn (mixed $value): bool => null !== $value);

        $idToken = self::sign($claims);
        if (null !== $this->idTokenForger) {
            $idToken = ($this->idTokenForger)(json_encode($claims, \JSON_THROW_ON_ERROR));
        }

        $refresh = 'refresh-'.++$this->serial;
        $this->refreshTokens[$refresh] = true;

        return $this->json(['access_token' => 'access-'.$this->serial, 'token_type' => 'Bearer', 'expires_in' => 300, 'refresh_token' => $refresh, 'id_token' => $idToken]);
    }

    /**
     * @param array<array-key, mixed> $form
     */
    private function rotate(array $form): MockResponse
    {
        $presented = \is_string($form['refresh_token'] ?? null) ? $form['refresh_token'] : '';
        if (true !== ($this->refreshTokens[$presented] ?? null)) {
            $this->revokeFamily();

            return $this->json(['error' => 'invalid_grant'], 400);
        }

        $this->refreshTokens[$presented] = false;
        $next = 'refresh-'.++$this->serial;
        $this->refreshTokens[$next] = true;

        return $this->json(['access_token' => 'access-'.$this->serial, 'token_type' => 'Bearer', 'expires_in' => 300, 'refresh_token' => $next]);
    }

    public function revokeFamily(): void
    {
        $this->refreshTokens = array_map(static fn (): bool => false, $this->refreshTokens);
    }

    public function familyRevoked(): bool
    {
        return !\in_array(true, $this->refreshTokens, true);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body, \JSON_THROW_ON_ERROR), ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }
}
