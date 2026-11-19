<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\Exception\InvalidCredentials;
use Phil\ApiKeysBundle\Application\OwnerTypes;
use Phil\ApiKeysBundle\Domain\Token\TokenFormat;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\Infrastructure\Attribute\RequiresScope;
use Phil\ApiKeysBundle\Infrastructure\Memory\InMemoryApiTokenRepository;
use Phil\ApiKeysBundle\Infrastructure\Security\ApiTokenHandler;
use Phil\ApiKeysBundle\Infrastructure\Security\ProblemErrorResponder;
use Phil\ApiKeysBundle\Infrastructure\Security\RequiresScopeListener;
use Phil\ApiKeysBundle\Infrastructure\Security\ScopeVoter;
use Phil\ApiKeysBundle\Infrastructure\Security\TokenAuthenticationListener;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\FixedAddress;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\FixedAuthorization;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\ScopedController;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[CoversClass(ApiTokenHandler::class)]
#[CoversClass(TokenAuthenticationListener::class)]
#[CoversClass(ProblemErrorResponder::class)]
#[CoversClass(ScopeVoter::class)]
#[CoversClass(RequiresScopeListener::class)]
#[CoversClass(RequiresScope::class)]
final class SecurityTest extends TestCase
{
    public function testTheHandlerGivesTheBadgeOfTheOwnerWithTheScopesAndTheIdentifier(): void
    {
        $tokens = Tokens::service(addresses: new FixedAddress());
        $issued = $tokens->create('alice@example.com', 'Deploy', ['projects:write', 'billing']);
        $requests = new RequestStack([Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7'])]);
        $requests->push(Request::create('/sub', server: ['REMOTE_ADDR' => '198.51.100.1']));

        $badge = new ApiTokenHandler($tokens, $requests)->getUserBadgeFrom($issued->plainText);

        self::assertSame('alice@example.com', $badge->getUserIdentifier());
        self::assertSame(['scope' => ['projects:write', 'billing'], '_api_token_id' => $issued->token->id(), '_api_token_owner_type' => 'user'], $badge->getAttributes());
        self::assertEquals(new DateTimeImmutable(Tokens::NOW), $issued->token->lastUsedAt());
        self::assertSame('anon(203.0.113.7)', $issued->token->lastUsedAddress(), 'the address of the main request');
    }

    public function testTheHandlerWorksWithAnEmptyStackOfRequests(): void
    {
        $tokens = Tokens::service(addresses: new FixedAddress());
        $issued = $tokens->create('alice@example.com', 'Deploy', []);

        new ApiTokenHandler($tokens, new RequestStack())->getUserBadgeFrom($issued->plainText);

        self::assertNull($issued->token->lastUsedAddress());
    }

    public function testTheHandlerWorksWithoutRequests(): void
    {
        $tokens = Tokens::service(addresses: new FixedAddress());
        $issued = $tokens->create('alice@example.com', 'Deploy', []);

        self::assertSame([], new ApiTokenHandler($tokens)->getUserBadgeFrom($issued->plainText)->getAttributes()['scope'] ?? null);
        self::assertNull($issued->token->lastUsedAddress());
    }

    public function testARefusedTokenFailsTheAuthenticationWithoutSayingWhy(): void
    {
        try {
            new ApiTokenHandler(Tokens::service())->getUserBadgeFrom(new TokenFormat('test')->format('AAAAAAAAAAAA', str_repeat('x', 32)));
            self::fail('The token was accepted.');
        } catch (BadCredentialsException $badCredentialsException) {
            self::assertSame('The API token was refused.', $badCredentialsException->getMessage());
            self::assertSame(0, $badCredentialsException->getCode());
            self::assertInstanceOf(InvalidCredentials::class, $badCredentialsException->getPrevious());
        }
    }

    public function testTheRoleOfAScope(): void
    {
        self::assertSame('ROLE_API_SCOPE_PROJECTS_READ', ApiTokenHandler::scopeRole('projects:read'));
        self::assertSame('ROLE_API_SCOPE_A_B_C_D9', ApiTokenHandler::scopeRole('a.b-c_d9'));
    }

    public function testSymfonysAuthenticatorWithTheHandlerGivesTheRolesOfTheScopes(): void
    {
        $tokens = Tokens::service();
        $issued = $tokens->create('alice@example.com', 'Deploy', ['projects:write', 'billing']);
        $request = $this->request('Bearer '.$issued->plainText);

        $token = $this->authenticate($request, new TokenAuthenticationListener(), $tokens);

        self::assertInstanceOf(PostAuthenticationToken::class, $token);
        self::assertSame('alice@example.com', $token->getUserIdentifier());
        self::assertSame('api', $token->getFirewallName());
        self::assertSame(['ROLE_MEMBER', 'ROLE_API_SCOPE_PROJECTS_WRITE', 'ROLE_API_SCOPE_BILLING'], $token->getRoleNames());
        self::assertSame($issued->token->id(), $token->getAttribute(ApiTokenHandler::TOKEN_ID_ATTRIBUTE));
        self::assertSame(['projects:write', 'billing'], $token->getAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE));
        self::assertSame($issued->token->id(), $request->attributes->get(ApiTokenHandler::TOKEN_ID_ATTRIBUTE));
        self::assertSame(['projects:write', 'billing'], $request->attributes->get(ApiTokenHandler::SCOPES_ATTRIBUTE));
    }

    public function testAnOrganizationIsLoadedByTheProviderOfItsType(): void
    {
        $tokens = Tokens::service(owners: new OwnerTypes(['organization' => null]));
        $issued = $tokens->create(new TokenOwner('organization', 'acme'), 'Export', ['billing']);
        $request = $this->request('Bearer '.$issued->plainText);
        $providers = new ServiceLocator(['organization' => static fn (): InMemoryUserProvider => new InMemoryUserProvider(['acme' => ['roles' => ['ROLE_ORGANIZATION']]])]);

        $token = $this->authenticate($request, new TokenAuthenticationListener(), $tokens, providers: $providers);

        self::assertSame('acme', $token->getUserIdentifier());
        self::assertSame(['ROLE_ORGANIZATION', 'ROLE_API_SCOPE_BILLING'], $token->getRoleNames());
        self::assertSame('organization', $token->getAttribute(ApiTokenHandler::OWNER_TYPE_ATTRIBUTE));
        self::assertSame('organization', $request->attributes->get(ApiTokenHandler::OWNER_TYPE_ATTRIBUTE));

        $member = $this->request('Bearer '.$tokens->create('alice@example.com', 'Mine', [])->plainText);
        self::assertSame('user', $this->authenticate($member, new TokenAuthenticationListener(), $tokens, providers: $providers)->getAttribute(ApiTokenHandler::OWNER_TYPE_ATTRIBUTE));
        self::assertSame('user', $member->attributes->get(ApiTokenHandler::OWNER_TYPE_ATTRIBUTE));
    }

    public function testATokenOfATypeNoProviderLoadsOpensNothing(): void
    {
        $tokens = Tokens::service(owners: new OwnerTypes(['organization' => null, 'application' => null]));
        $organization = $tokens->create(new TokenOwner('organization', 'acme'), 'Export', [])->plainText;
        $application = $tokens->create(new TokenOwner('application', 'ci'), 'CI', [])->plainText;
        $providers = new ServiceLocator(['application' => static fn (): object => new stdClass()]);

        foreach ([[$organization, null], [$organization, $providers], [$application, $providers]] as [$plainText, $locator]) {
            try {
                new ApiTokenHandler($tokens, null, $locator)->getUserBadgeFrom($plainText);
                self::fail('The token opened the API.');
            } catch (BadCredentialsException $badCredentialsException) {
                self::assertSame('The API token was refused.', $badCredentialsException->getMessage());
                self::assertNull($badCredentialsException->getPrevious());
            }
        }
    }

    public function testTheOwnersRolesMayBeLeftOut(): void
    {
        $tokens = Tokens::service();
        $issued = $tokens->create('alice@example.com', 'Deploy', ['billing']);

        self::assertSame(['ROLE_API_SCOPE_BILLING'], $this->authenticate($this->request('Bearer '.$issued->plainText), new TokenAuthenticationListener(false), $tokens)->getRoleNames());
    }

    public function testRolesAreGivenOnce(): void
    {
        $tokens = Tokens::service();
        $issued = $tokens->create('alice@example.com', 'Deploy', ['billing']);

        self::assertSame(['ROLE_API_SCOPE_BILLING', 'ROLE_MEMBER'], $this->authenticate($this->request('Bearer '.$issued->plainText), new TokenAuthenticationListener(), $tokens, ['ROLE_API_SCOPE_BILLING', 'ROLE_MEMBER'])->getRoleNames());
    }

    public function testTheListenerKeepsTheOtherAttributesOfTheToken(): void
    {
        $user = new InMemoryUser('alice@example.com', null, ['ROLE_MEMBER']);
        $passport = new SelfValidatingPassport(new UserBadge('alice@example.com', static fn (): InMemoryUser => $user, ['scope' => [7, 'billing'], '_api_token_id' => 'AAAAAAAAAAAA']));
        $created = new PostAuthenticationToken($user, 'api', ['ROLE_MEMBER']);
        $created->setAttribute('kept', 'yes');

        $event = new AuthenticationTokenCreatedEvent($created, $passport);

        new TokenAuthenticationListener()->onAuthenticationTokenCreated($event);

        $token = $event->getAuthenticatedToken();
        self::assertNotSame($created, $token);
        self::assertSame('yes', $token->getAttribute('kept'));
        self::assertSame(['billing'], $token->getAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE), 'only strings, as a list');
        self::assertSame(['ROLE_MEMBER', 'ROLE_API_SCOPE_BILLING'], $token->getRoleNames());
    }

    public function testTheRolesAreAList(): void
    {
        $user = new InMemoryUser('alice@example.com', null, ['ROLE_MEMBER', 'ROLE_MEMBER', 'ROLE_X']);
        $passport = new SelfValidatingPassport(new UserBadge('alice@example.com', static fn (): InMemoryUser => $user, ['scope' => ['billing'], '_api_token_id' => 'AAAAAAAAAAAA']));
        $event = new AuthenticationTokenCreatedEvent(new PostAuthenticationToken($user, 'api', $user->getRoles()), $passport);

        new TokenAuthenticationListener()->onAuthenticationTokenCreated($event);

        self::assertSame(['ROLE_MEMBER', 'ROLE_X', 'ROLE_API_SCOPE_BILLING'], $event->getAuthenticatedToken()->getRoleNames());
    }

    public function testTheListenerLeavesOtherAuthenticationsAlone(): void
    {
        $user = new InMemoryUser('alice@example.com', null, ['ROLE_MEMBER']);
        $created = new PostAuthenticationToken($user, 'api', ['ROLE_MEMBER']);
        $listener = new TokenAuthenticationListener();

        $withoutIdentifier = new AuthenticationTokenCreatedEvent($created, new SelfValidatingPassport(new UserBadge('alice@example.com', static fn (): InMemoryUser => $user, ['scope' => 'openid', '_api_token_id' => 12])));
        $listener->onAuthenticationTokenCreated($withoutIdentifier);
        self::assertSame($created, $withoutIdentifier->getAuthenticatedToken());

        $withoutAttributes = new AuthenticationTokenCreatedEvent($created, new SelfValidatingPassport(new UserBadge('alice@example.com', static fn (): InMemoryUser => $user)));
        $listener->onAuthenticationTokenCreated($withoutAttributes);
        self::assertSame($created, $withoutAttributes->getAuthenticatedToken());

        $session = new UsernamePasswordToken($user, 'main', ['ROLE_MEMBER']);
        $notPostAuthentication = new AuthenticationTokenCreatedEvent($session, new SelfValidatingPassport(new UserBadge('alice@example.com', static fn (): InMemoryUser => $user, ['_api_token_id' => 'AAAAAAAAAAAA'])));
        $listener->onAuthenticationTokenCreated($notPostAuthentication);
        self::assertSame($session, $notPostAuthentication->getAuthenticatedToken());

        $request = $this->request(null);
        $listener->onLoginSuccess(new LoginSuccessEvent(self::createStub(AuthenticatorInterface::class), new SelfValidatingPassport(new UserBadge('alice@example.com')), $session, $request, null, 'main'));
        self::assertSame([], $request->attributes->all());
    }

    public function testTheRequestGetsNoScopesWhenTheTokenHasNone(): void
    {
        $user = new InMemoryUser('alice@example.com', null);
        $token = new PostAuthenticationToken($user, 'api', []);
        $token->setAttribute(ApiTokenHandler::TOKEN_ID_ATTRIBUTE, 'AAAAAAAAAAAA');

        $request = $this->request(null);

        new TokenAuthenticationListener()->onLoginSuccess(new LoginSuccessEvent(self::createStub(AuthenticatorInterface::class), new SelfValidatingPassport(new UserBadge('alice@example.com')), $token, $request, null, 'api'));

        self::assertSame(['_api_token_id' => 'AAAAAAAAAAAA', '_api_token_owner_type' => 'user', '_api_token_scopes' => []], $request->attributes->all());
    }

    public function testTheProblemDetailsOfA401(): void
    {
        $responder = new ProblemErrorResponder('my "api"');

        $refused = $responder->onAuthenticationFailure($this->request(null), new BadCredentialsException());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $refused->getStatusCode());
        self::assertSame('application/problem+json', $refused->headers->get('Content-Type'));
        self::assertSame('Bearer realm="my \"api\"",error="invalid_token"', $refused->headers->get('WWW-Authenticate'));
        self::assertSame(['type' => 'about:blank', 'status' => 401, 'title' => 'Unauthorized', 'detail' => 'The API token was refused.'], json_decode((string) $refused->getContent(), true));

        $missing = new ProblemErrorResponder('a\\b')->start($this->request(null));
        self::assertSame(Response::HTTP_UNAUTHORIZED, $missing->getStatusCode());
        self::assertSame('Bearer realm="a\\\\b"', $missing->headers->get('WWW-Authenticate'));
        self::assertSame(['type' => 'about:blank', 'status' => 401, 'title' => 'Unauthorized', 'detail' => 'An API token is required.'], json_decode((string) $missing->getContent(), true));
    }

    public function testTheVoterGrantsTheScopesOfTheToken(): void
    {
        $voter = new ScopeVoter();
        $token = $this->apiToken(['projects:read']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, 'projects:read', [ScopeVoter::ATTRIBUTE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, 'projects:write', [ScopeVoter::ATTRIBUTE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, 'projects:read', ['ROLE_ADMIN']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, [ScopeVoter::ATTRIBUTE]));
        self::assertTrue($voter->supportsAttribute(ScopeVoter::ATTRIBUTE));
        self::assertFalse($voter->supportsAttribute('ROLE_ADMIN'));

        $broken = $this->apiToken([]);
        $broken->setAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE, 'projects:read');
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($broken, 'projects:read', [ScopeVoter::ATTRIBUTE]));

        $withoutScopes = new PostAuthenticationToken(new InMemoryUser('alice', null), 'api', []);
        $withoutScopes->setAttribute(ApiTokenHandler::TOKEN_ID_ATTRIBUTE, 'AAAAAAAAAAAA');
        self::assertSame(VoterInterface::ACCESS_DENIED, new ScopeVoter(true)->vote($withoutScopes, 'projects:read', [ScopeVoter::ATTRIBUTE]), 'a token is never another authentication');
    }

    public function testOtherAuthenticationsAreDeniedUnlessLetThrough(): void
    {
        $session = new UsernamePasswordToken(new InMemoryUser('alice', null), 'main');
        $oauth = new PostAuthenticationToken(new InMemoryUser('alice', null), 'oauth', []);
        $oauth->setAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE, ['projects:read']);

        self::assertSame(VoterInterface::ACCESS_DENIED, new ScopeVoter()->vote($session, 'projects:read', [ScopeVoter::ATTRIBUTE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, new ScopeVoter()->vote($oauth, 'projects:read', [ScopeVoter::ATTRIBUTE]), 'the scopes of another access token are not those of a personal token');
        self::assertSame(VoterInterface::ACCESS_GRANTED, new ScopeVoter(true)->vote($session, 'projects:read', [ScopeVoter::ATTRIBUTE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, new ScopeVoter(true)->vote(new NullToken(), 'projects:read', [ScopeVoter::ATTRIBUTE]), 'nobody signed in');
    }

    public function testTheListenerRequiresEveryScopeOfTheController(): void
    {
        $listener = new RequiresScopeListener($this->authorization(['projects:read']), 'api');
        $listener->onKernelControllerArguments($this->event('show'));

        try {
            $listener->onKernelControllerArguments($this->event('update'));
            self::fail('The controller was reached.');
        } catch (AccessDeniedHttpException $accessDeniedHttpException) {
            self::assertSame('The token does not hold the scope "projects:write".', $accessDeniedHttpException->getMessage());
            self::assertSame(0, $accessDeniedHttpException->getCode());
            self::assertNull($accessDeniedHttpException->getPrevious());
            self::assertSame(['WWW-Authenticate' => 'Bearer realm="api",error="insufficient_scope",scope="projects:write"'], $accessDeniedHttpException->getHeaders());
        }

        try {
            new RequiresScopeListener($this->authorization(['projects:write']), 'my "api"')->onKernelControllerArguments($this->event('update'));
            self::fail('The controller was reached.');
        } catch (AccessDeniedHttpException $accessDeniedHttpException) {
            self::assertSame('The token does not hold the scope "projects:read".', $accessDeniedHttpException->getMessage());
            self::assertSame(['WWW-Authenticate' => 'Bearer realm="my \"api\"",error="insufficient_scope",scope="projects:read"'], $accessDeniedHttpException->getHeaders());
        }
    }

    public function testARequestNobodyIsAuthenticatedOnIsAnswered401(): void
    {
        foreach ([new RequiresScopeListener($this->authorization(['projects:read'], false), 'api'), new RequiresScopeListener(null, 'api')] as $listener) {
            try {
                $listener->onKernelControllerArguments($this->event('show'));
                self::fail('The controller was reached.');
            } catch (UnauthorizedHttpException $unauthorizedHttpException) {
                self::assertSame('An API token is required.', $unauthorizedHttpException->getMessage());
                self::assertSame(401, $unauthorizedHttpException->getStatusCode());
                self::assertSame(['WWW-Authenticate' => 'Bearer realm="api"'], $unauthorizedHttpException->getHeaders());
            }
        }
    }

    public function testAControllerWithoutTheAttributeIsLeftAlone(): void
    {
        $authorization = $this->authorization([], false);
        new RequiresScopeListener($authorization, 'api')->onKernelControllerArguments($this->event('open'));
        new RequiresScopeListener(null, 'api')->onKernelControllerArguments($this->event('open'));

        self::assertSame(0, $authorization->calls, 'nothing asked');
    }

    public function testTheAttributeNamesAScope(): void
    {
        self::assertSame('projects:read', new RequiresScope('projects:read')->scope);
    }

    private function request(?string $authorization): Request
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']);

        if (null !== $authorization) {
            $request->headers->set('Authorization', $authorization);
        }

        return $request;
    }

    /**
     * The authentication Symfony's authenticator runs with the handler, then the two events of the listener.
     *
     * @param list<string>                $roles
     * @param ServiceLocator<object>|null $providers
     */
    private function authenticate(Request $request, TokenAuthenticationListener $listener, ?ApiTokens $tokens = null, array $roles = ['ROLE_MEMBER'], ?ServiceLocator $providers = null): TokenInterface
    {
        $tokens ??= Tokens::service();
        $users = new InMemoryUserProvider(['alice@example.com' => ['roles' => $roles]]);
        $authenticator = new AccessTokenAuthenticator(new ApiTokenHandler($tokens, null, $providers), new HeaderAccessTokenExtractor(), $users, realm: 'api');
        $passport = $authenticator->authenticate($request);
        $event = new AuthenticationTokenCreatedEvent($authenticator->createToken($passport, 'api'), $passport);
        $listener->onAuthenticationTokenCreated($event);
        $token = $event->getAuthenticatedToken();
        $listener->onLoginSuccess(new LoginSuccessEvent($authenticator, $passport, $token, $request, null, 'api'));

        return $token;
    }

    /**
     * @param list<string> $scopes
     */
    private function apiToken(array $scopes): TokenInterface
    {
        $tokens = Tokens::service(new InMemoryApiTokenRepository(), new MockClock(Tokens::NOW));

        return $this->authenticate($this->request('Bearer '.$tokens->create('alice@example.com', 'n', $scopes)->plainText), new TokenAuthenticationListener(), $tokens);
    }

    /**
     * @param list<string> $granted
     */
    private function authorization(array $granted, bool $authenticated = true): FixedAuthorization
    {
        return new FixedAuthorization($granted, $authenticated);
    }

    private function event(string $action): ControllerArgumentsEvent
    {
        $kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
        $request = Request::create('/');
        $controller = match ($action) {
            'show' => new ScopedController()->show(...),
            'update' => new ScopedController()->update(...),
            default => static fn (): string => 'open',
        };

        return new ControllerArgumentsEvent($kernel, new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST), [], $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
