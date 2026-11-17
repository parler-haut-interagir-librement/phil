<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Exception\AuthorizationError;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequest;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequestParser;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Domain\Oidc\QueryString;
use Phil\IdentityProviderBundle\Domain\Oidc\Scopes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthorizationRequestParser::class)]
#[CoversClass(AuthorizationRequest::class)]
#[CoversClass(AuthorizationError::class)]
#[CoversClass(Pkce::class)]
#[CoversClass(QueryString::class)]
#[CoversClass(Scopes::class)]
final class OidcTest extends TestCase
{
    private const string CHALLENGE = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

    /**
     * RFC 7636, appendix B.
     */
    public function testPkceS256(): void
    {
        self::assertSame(self::CHALLENGE, Pkce::challengeOf('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
        self::assertTrue(Pkce::verify('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk', self::CHALLENGE));
        self::assertFalse(Pkce::verify('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXK', self::CHALLENGE));
        self::assertFalse(Pkce::verify('short', Pkce::challengeOf('short')));
        self::assertFalse(Pkce::verify(str_repeat('a', 129), Pkce::challengeOf(str_repeat('a', 129))));
        self::assertTrue(Pkce::verify(str_repeat('a', 128), Pkce::challengeOf(str_repeat('a', 128))));
        self::assertTrue(Pkce::verify(str_repeat('~', 43), Pkce::challengeOf(str_repeat('~', 43))));
    }

    public function testTheQueryOfARedirect(): void
    {
        self::assertSame('https://a.example/cb?code=x&state=a%20b', QueryString::append('https://a.example/cb', ['code' => 'x', 'state' => 'a b']));
        self::assertSame('https://a.example/cb?v=1&code=x', QueryString::append('https://a.example/cb?v=1', ['code' => 'x']));
        self::assertSame('https://a.example/cb?code=x#top', QueryString::append('https://a.example/cb#top', ['code' => 'x']));
    }

    public function testScopesAreSplitWithoutRepeats(): void
    {
        self::assertSame(['openid', 'email'], Scopes::split(' openid  email openid '));
        self::assertSame([], Scopes::split(null));
        self::assertSame([], Scopes::split(''));
    }

    public function testAValidRequest(): void
    {
        $request = $this->parse(['scope' => 'openid email phone unknown', 'state' => 's', 'nonce' => 'n', 'prompt' => 'login consent', 'max_age' => '300', 'login_hint' => 'ada@example.com']);

        self::assertSame('app', $request->clientId);
        self::assertSame('https://app.example/cb', $request->redirectUri);
        self::assertSame(['openid', 'email'], $request->scopes);
        self::assertSame('s', $request->state);
        self::assertSame('n', $request->nonce);
        self::assertSame(self::CHALLENGE, $request->codeChallenge);
        self::assertSame(['login', 'consent'], $request->prompt);
        self::assertSame(300, $request->maxAge);
        self::assertSame('ada@example.com', $request->loginHint);
        self::assertTrue($request->prompts('login'));
        self::assertFalse($request->prompts('none'));
        self::assertSame([
            'response_type' => 'code',
            'client_id' => 'app',
            'redirect_uri' => 'https://app.example/cb',
            'scope' => 'openid email',
            'state' => 's',
            'nonce' => 'n',
            'code_challenge' => self::CHALLENGE,
            'code_challenge_method' => 'S256',
            'prompt' => 'login consent',
            'max_age' => '300',
            'login_hint' => 'ada@example.com',
        ], $request->toQuery());
    }

    public function testAMinimalRequest(): void
    {
        $request = $this->parse(['state' => '', 'response_mode' => 'query']);

        self::assertNull($request->state);
        self::assertNull($request->nonce);
        self::assertNull($request->maxAge);
        self::assertSame([], $request->prompt);
        self::assertSame(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid', 'code_challenge' => self::CHALLENGE, 'code_challenge_method' => 'S256'], $request->toQuery());
        self::assertSame(['none'], $this->parse(['prompt' => 'none'])->prompt);
        self::assertSame(999_999_999, $this->parse(['max_age' => '999999999'])->maxAge);
    }

    public function testAClientMayGoWithoutPkceWhenRegisteredSo(): void
    {
        $request = $this->parse(['client_id' => 'server', 'code_challenge' => null, 'code_challenge_method' => null]);

        self::assertNull($request->codeChallenge);
        self::assertSame(['response_type' => 'code', 'client_id' => 'server', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid'], $request->toQuery());
        self::assertSame(self::CHALLENGE, $this->parse(['client_id' => 'server'])->codeChallenge);
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('shownErrors')]
    public function testErrorsAboutTheClientAreShown(array $query, string $error): void
    {
        try {
            $this->parse($query);
            self::fail('Accepted.');
        } catch (AuthorizationError $authorizationError) {
            self::assertFalse($authorizationError->isRedirectable());
            self::assertSame($error, $authorizationError->error);
            self::assertNull($authorizationError->state);
            self::assertSame($error.': '.$authorizationError->description, $authorizationError->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function shownErrors(): iterable
    {
        yield 'no client' => [['client_id' => null], 'invalid_client'];
        yield 'unknown client' => [['client_id' => 'other'], 'invalid_client'];
        yield 'disabled client' => [['client_id' => 'disabled'], 'invalid_client'];
        yield 'list client' => [['client_id' => ['app']], 'invalid_request'];
        yield 'long client' => [['client_id' => str_repeat('a', 2049)], 'invalid_request'];
        yield 'empty client' => [['client_id' => ''], 'invalid_request'];
        yield 'no redirect' => [['redirect_uri' => null], 'invalid_request'];
        yield 'other redirect' => [['redirect_uri' => 'https://evil.example/cb'], 'invalid_request'];
        yield 'prefix of a redirect' => [['redirect_uri' => 'https://app.example/cb/../evil'], 'invalid_request'];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('redirectedErrors')]
    public function testOtherErrorsGoBackToTheClient(array $query, string $error, ?string $state = 'xyz'): void
    {
        try {
            $this->parse($query + ['state' => 'xyz']);
            self::fail('Accepted.');
        } catch (AuthorizationError $authorizationError) {
            self::assertTrue($authorizationError->isRedirectable());
            self::assertSame($error, $authorizationError->error);
            self::assertSame($state, $authorizationError->state);
            self::assertStringStartsWith('https://app.example/cb?error='.$error.'&error_description=', $authorizationError->redirectTo('https://id.example'));
            self::assertStringEndsWith((null === $state ? '' : '&state='.$state).'&iss=https%3A%2F%2Fid.example', $authorizationError->redirectTo('https://id.example'));
        }
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2?: ?string}>
     */
    public static function redirectedErrors(): iterable
    {
        yield 'repeated parameter' => [['scope' => ['openid']], 'invalid_request'];
        yield 'request object' => [['request' => 'jwt'], 'request_not_supported'];
        yield 'request uri' => [['request_uri' => 'https://x'], 'request_uri_not_supported'];
        yield 'implicit flow' => [['response_type' => 'token'], 'unsupported_response_type'];
        yield 'hybrid flow' => [['response_type' => 'code id_token'], 'unsupported_response_type'];
        yield 'fragment mode' => [['response_mode' => 'fragment'], 'invalid_request'];
        yield 'no openid' => [['scope' => 'email'], 'invalid_scope'];
        yield 'no challenge' => [['code_challenge' => null], 'invalid_request'];
        yield 'no challenge, a method' => [['client_id' => 'server', 'code_challenge' => null], 'invalid_request'];
        yield 'plain challenge' => [['code_challenge_method' => 'plain'], 'invalid_request'];
        yield 'no method' => [['client_id' => 'server', 'code_challenge_method' => null], 'invalid_request'];
        yield 'short challenge' => [['code_challenge' => 'abc'], 'invalid_request'];
        yield 'short challenge, optional' => [['client_id' => 'server', 'code_challenge' => 'abc'], 'invalid_request'];
        yield 'unknown prompt' => [['prompt' => 'login other'], 'invalid_request'];
        yield 'account chooser, which the provider does not show' => [['prompt' => 'select_account'], 'invalid_request'];
        yield 'none with another' => [['prompt' => 'none login'], 'invalid_request'];
        yield 'negative age' => [['max_age' => '-1'], 'invalid_request'];
        yield 'long age' => [['max_age' => '1234567890'], 'invalid_request'];
        yield 'long nonce' => [['nonce' => str_repeat('n', 2049)], 'invalid_request'];
        yield 'long state' => [['state' => str_repeat('s', 2049)], 'invalid_request', null];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function parse(array $query): AuthorizationRequest
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $clients = [
            'app' => Client::register('app', 'App', 'secret', ['https://app.example/cb'], [], ['email', 'profile'], false, $now),
            'disabled' => Client::register('disabled', 'Off', null, ['https://app.example/cb'], [], [], false, $now),
            'server' => Client::register('server', 'Server', 'secret', ['https://app.example/cb'], [], [], false, $now, requirePkce: false),
        ];
        $clients['disabled']->disable($now);
        $query = array_filter($query + [
            'response_type' => 'code',
            'client_id' => 'app',
            'redirect_uri' => 'https://app.example/cb',
            'scope' => 'openid',
            'code_challenge' => self::CHALLENGE,
            'code_challenge_method' => 'S256',
        ], static fn (mixed $value): bool => null !== $value);

        return new AuthorizationRequestParser(['openid', 'profile', 'email', 'phone'])->parse($query, static fn (string $id): ?Client => $clients[$id] ?? null);
    }
}
