<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Benchmark;

use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\TokenHasher;
use Phil\ApiKeysBundle\Domain\Token\TokenFormat;
use Phil\ApiKeysBundle\Infrastructure\Memory\InMemoryApiTokenRepository;
use Phil\ApiKeysBundle\Infrastructure\Security\ApiTokenHandler;
use Phil\ApiKeysBundle\Infrastructure\Security\TokenAuthenticationListener;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\Tokens;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Throwable;

/**
 * What every request of the API pays before its controller: reading the token, the keyed hash, the verification on a
 * store in memory with the last use throttled, and the whole authentication from the header to the security token,
 * through Symfony's `access_token` authenticator with the bundle's handler and the listener that gives the roles of the
 * scopes.
 */
final readonly class AuthenticatorBench
{
    private TokenFormat $format;

    private TokenHasher $hasher;

    private ApiTokens $tokens;

    private AccessTokenAuthenticator $authenticator;

    private TokenAuthenticationListener $listener;

    private string $plainText;

    public function __construct()
    {
        $this->format = new TokenFormat('acme');
        $this->hasher = new TokenHasher(Tokens::KEY);
        $this->tokens = new ApiTokens(new InMemoryApiTokenRepository(), $this->format, $this->hasher, Tokens::scopes(), new MockClock(Tokens::NOW));
        $this->authenticator = new AccessTokenAuthenticator(new ApiTokenHandler($this->tokens), new HeaderAccessTokenExtractor(), new InMemoryUserProvider(['alice@example.com' => ['roles' => ['ROLE_MEMBER']]]), realm: 'api');
        $this->listener = new TokenAuthenticationListener();
        $this->plainText = $this->tokens->create('alice@example.com', 'bench', ['projects:read', 'billing'])->plainText;
        $this->tokens->verify($this->plainText);
    }

    public function benchParse(): void
    {
        $this->format->parse($this->plainText);
    }

    public function benchHash(): string
    {
        return $this->hasher->hash('3kTMd9qXbW1z', 'Qx7bV0aLk2sR9uYc4mNp8wTe1hJg6dFo');
    }

    public function benchVerify(): void
    {
        $this->tokens->verify($this->plainText);
    }

    public function benchRefuseAMalformedToken(): void
    {
        try {
            $this->tokens->verify('acme_nope');
        } catch (Throwable) {
        }
    }

    public function benchAuthenticateARequest(): TokenInterface
    {
        $request = Request::create('/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->plainText]);
        $passport = $this->authenticator->authenticate($request);
        $event = new AuthenticationTokenCreatedEvent($this->authenticator->createToken($passport, 'api'), $passport);
        $this->listener->onAuthenticationTokenCreated($event);

        return $event->getAuthenticatedToken();
    }
}
