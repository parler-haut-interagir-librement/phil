<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Override;
use SensitiveParameter;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

/**
 * Rotates refresh tokens, or fails as it is told to.
 */
final class FakeOidcClient implements OidcClientInterface
{
    /** @var list<string> */
    public array $presented = [];

    public ?Throwable $failure = null;

    private int $serial = 1;

    #[Override]
    public function request(string $endpoint, array $body = [], array $options = []): ResponseInterface
    {
        return new MockResponse('');
    }

    #[Override]
    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        return [];
    }

    #[Override]
    public function refreshToken(#[SensitiveParameter] string $refreshToken, array $scopes = []): array
    {
        $this->presented[] = $refreshToken;
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        ++$this->serial;

        return ['access_token' => 'at'.$this->serial, 'refresh_token' => 'rt'.$this->serial, 'expires_in' => 300];
    }

    #[Override]
    public function fetchUserInfo(string $accessToken): array
    {
        return [];
    }

    #[Override]
    public function getClientAuthenticationMethod(): string
    {
        return 'client_secret_basic';
    }
}
