<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Service;

use DateTimeImmutable;
use Phil\Lti\Exception\AccessDenied;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Exception\TokenRequestRefused;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Http\ServiceResponse;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Key\RsaPrivateKey;
use Phil\Lti\Platform\Token\AccessToken;
use Phil\Lti\Platform\Token\InMemoryAccessTokens;
use Phil\Lti\Platform\Token\IssuedToken;
use Phil\Lti\Platform\Token\ServiceGuard;
use Phil\Lti\Platform\Token\TokenEndpoint;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Lti\Service\AccessTokenProvider;
use Phil\Lti\Service\Scope;
use Phil\Lti\Tests\Fixture\Keys;
use Phil\Lti\Tests\Fixture\Lti;
use Phil\Lti\Tests\Fixture\RecordingServiceClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The tool asks the platform's token endpoint for access tokens, and the platform checks them on its services.
 */
#[CoversClass(AccessTokenProvider::class)]
#[CoversClass(TokenEndpoint::class)]
#[CoversClass(AccessToken::class)]
#[CoversClass(IssuedToken::class)]
#[CoversClass(InMemoryAccessTokens::class)]
#[CoversClass(ServiceGuard::class)]
#[CoversClass(TokenRequestRefused::class)]
#[CoversClass(AccessDenied::class)]
final class AccessTokenTest extends TestCase
{
    private Lti $lti;

    private InMemoryAccessTokens $tokens;

    protected function setUp(): void
    {
        $this->lti = new Lti();
        $this->tokens = new InMemoryAccessTokens($this->lti->clock);
    }

    public function testTheToolGetsATokenAndThePlatformAcceptsIt(): void
    {
        $client = $this->platformClient();
        $provider = new AccessTokenProvider($client, $this->lti->toolKeys, new ArrayAdapter(), $this->lti->clock);

        $value = $provider->token($this->lti->platform, [Scope::SCORE, Scope::LINE_ITEM, Scope::SCORE]);
        $token = new ServiceGuard($this->tokens)->authorize('Bearer '.$value, [Scope::SCORE]);

        self::assertSame(Lti::CLIENT, $token->clientId);
        self::assertSame([Scope::LINE_ITEM, Scope::SCORE], $token->scopes);
        self::assertEquals(new DateTimeImmutable('2026-11-14 13:00:00 UTC'), $token->expiresAt);
        self::assertSame($value, $provider->token($this->lti->platform, [Scope::LINE_ITEM, Scope::SCORE]));
        self::assertCount(1, $client->requests);

        $request = $client->requests[0];
        self::assertSame('POST', $request->method);
        self::assertSame('https://lms.example.org/token', $request->url->toString());
        self::assertSame(['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'], $request->headers);
        parse_str((string) $request->body, $body);
        self::assertSame('client_credentials', $body['grant_type']);
        self::assertSame(AccessTokenProvider::ASSERTION_TYPE, $body['client_assertion_type']);
        self::assertSame(Scope::LINE_ITEM.' '.Scope::SCORE, $body['scope']);
        self::assertIsString($body['client_assertion']);
        $assertion = Jwt::parse($body['client_assertion']);
        $now = $this->lti->clock->now()->getTimestamp();
        self::assertSame([Lti::CLIENT, Lti::CLIENT, 'https://lms.example.org/token', $now, $now + 300], [$assertion->claims['iss'], $assertion->claims['sub'], $assertion->claims['aud'], $assertion->claims['iat'], $assertion->claims['exp']]);
        self::assertIsString($assertion->claims['jti']);
        self::assertSame(43, \strlen($assertion->claims['jti']));
    }

    public function testTheTokenIsAskedAgainAfterItsLifetimeLessTheMargin(): void
    {
        $client = new RecordingServiceClient(null, RecordingServiceClient::json(200, ['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => 100]), RecordingServiceClient::json(200, ['access_token' => 'b', 'token_type' => 'bearer']));
        $cache = new ArrayAdapter(clock: $this->lti->clock);
        $provider = new AccessTokenProvider($client, $this->lti->toolKeys, $cache, $this->lti->clock);

        self::assertSame('a', $provider->token($this->lti->platform, [Scope::SCORE]));
        $this->lti->clock->sleep(69);
        self::assertSame('a', $provider->token($this->lti->platform, [Scope::SCORE]));
        $this->lti->clock->sleep(2);
        self::assertSame('b', $provider->token($this->lti->platform, [Scope::SCORE]));
        self::assertSame('a', new AccessTokenProvider(new RecordingServiceClient(null, RecordingServiceClient::json(200, ['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => 1])), $this->lti->toolKeys, new ArrayAdapter(), $this->lti->clock)->token($this->lti->platform, []));
    }

    public function testATokenAudienceReplacesTheEndpoint(): void
    {
        $client = new RecordingServiceClient(null, RecordingServiceClient::json(200, ['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => 60]));
        $platform = new PlatformRegistration(Lti::ISSUER, Lti::CLIENT, $this->lti->platform->authorizationUrl, $this->lti->platform->tokenUrl, $this->lti->platform->keySetUrl, 'https://lms.example.org/oauth');
        new AccessTokenProvider($client, $this->lti->toolKeys, new ArrayAdapter(), $this->lti->clock)->token($platform, [Scope::SCORE]);
        parse_str((string) $client->requests[0]->body, $body);

        self::assertIsString($body['client_assertion']);
        self::assertSame('https://lms.example.org/oauth', Jwt::parse($body['client_assertion'])->claims['aud']);
    }

    public function testABadAnswerOfTheTokenEndpointFails(): void
    {
        $cases = [
            'status' => [RecordingServiceClient::json(400, ['error' => 'invalid_client']), 'The token endpoint of "https://lms.example.org" answered 400.'],
            'not json' => [RecordingServiceClient::json(200, 'nope'), 'The token endpoint answered something that is not JSON.'],
            'no token' => [RecordingServiceClient::json(200, ['token_type' => 'Bearer']), 'The token endpoint did not answer with a bearer token and its lifetime.'],
            'empty token' => [RecordingServiceClient::json(200, ['access_token' => '', 'token_type' => 'Bearer']), 'The token endpoint did not answer with a bearer token and its lifetime.'],
            'mac' => [RecordingServiceClient::json(200, ['access_token' => 'a', 'token_type' => 'mac']), 'The token endpoint did not answer with a bearer token and its lifetime.'],
            'no type' => [RecordingServiceClient::json(200, ['access_token' => 'a']), 'The token endpoint did not answer with a bearer token and its lifetime.'],
            'text lifetime' => [RecordingServiceClient::json(200, ['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => '60']), 'The token endpoint did not answer with a bearer token and its lifetime.'],
            'a list' => [RecordingServiceClient::json(200, ['a']), 'The token endpoint did not answer with a bearer token and its lifetime.'],
        ];

        foreach ($cases as $name => [$response, $message]) {
            try {
                new AccessTokenProvider(new RecordingServiceClient(null, $response), $this->lti->toolKeys, new ArrayAdapter(), $this->lti->clock)->token($this->lti->platform, [Scope::SCORE]);
                self::fail($name);
            } catch (ServiceFailed $serviceFailed) {
                self::assertSame($message, $serviceFailed->getMessage(), $name);
            }
        }
    }

    public function testTheTokenEndpointRefusesBadRequests(): void
    {
        $now = $this->lti->clock->now()->getTimestamp();
        $assertion = static fn (array $changes = []): string => JwtSigner::sign(array_filter($changes + ['iss' => Lti::CLIENT, 'sub' => Lti::CLIENT, 'aud' => 'https://lms.example.org/token', 'iat' => $now, 'exp' => $now + 60, 'jti' => bin2hex(random_bytes(8))], static fn (mixed $value): bool => null !== $value), Keys::tool());
        $request = static fn (array $changes = []): array => array_filter($changes + ['grant_type' => 'client_credentials', 'client_assertion_type' => AccessTokenProvider::ASSERTION_TYPE, 'client_assertion' => $assertion(), 'scope' => Scope::SCORE], static fn (mixed $value): bool => null !== $value);
        $cases = [
            'grant' => [$request(['grant_type' => 'password']), 'unsupported_grant_type', 'Only the grant "client_credentials" is supported.'],
            'no grant' => [$request(['grant_type' => null]), 'unsupported_grant_type', 'Only the grant "client_credentials" is supported.'],
            'assertion type' => [$request(['client_assertion_type' => 'basic']), 'invalid_request', 'The client authenticates by a JWT assertion.'],
            'no assertion' => [$request(['client_assertion' => null]), 'invalid_request', 'The client authenticates by a JWT assertion.'],
            'malformed' => [$request(['client_assertion' => 'a.b']), 'invalid_client', 'A token has three parts separated by dots.'],
            'other subject' => [$request(['client_assertion' => $assertion(['sub' => 'x'])]), 'invalid_client', 'The assertion is issued by its client, for itself.'],
            'no issuer' => [$request(['client_assertion' => $assertion(['iss' => null])]), 'invalid_client', 'The assertion is issued by its client, for itself.'],
            'unknown client' => [$request(['client_assertion' => $assertion(['iss' => 'x', 'sub' => 'x'])]), 'invalid_client', 'The client is not registered.'],
            'other audience' => [$request(['client_assertion' => $assertion(['aud' => 'https://lms.example.org/other'])]), 'invalid_client', 'The assertion is not addressed to this token endpoint.'],
            'expired' => [$request(['client_assertion' => $assertion(['exp' => $now - 60])]), 'invalid_client', 'The token has expired.'],
            'no jti' => [$request(['client_assertion' => $assertion(['jti' => null])]), 'invalid_client', 'The assertion has no identifier, or it was already used.'],
            'empty jti' => [$request(['client_assertion' => $assertion(['jti' => ''])]), 'invalid_client', 'The assertion has no identifier, or it was already used.'],
            'long jti' => [$request(['client_assertion' => $assertion(['jti' => str_repeat('j', 256)])]), 'invalid_client', 'The assertion has no identifier, or it was already used.'],
            'no scope' => [$request(['scope' => null]), 'invalid_scope', 'None of the scopes asked for is granted to the client.'],
            'unknown scope' => [$request(['scope' => 'openid email']), 'invalid_scope', 'None of the scopes asked for is granted to the client.'],
        ];

        foreach ($cases as $name => [$parameters, $error, $message]) {
            try {
                $this->endpoint()->issue($parameters);
                self::fail($name);
            } catch (TokenRequestRefused $tokenRequestRefused) {
                self::assertSame(['error' => $error, 'error_description' => $message], $tokenRequestRefused->toArray(), $name);
            }
        }

        $signedByPlatform = JwtSigner::sign(['iss' => Lti::CLIENT, 'sub' => Lti::CLIENT, 'aud' => ['https://lms.example.org/token'], 'iat' => $now, 'exp' => $now + 60, 'jti' => 'j'], RsaPrivateKey::fromPem(Keys::pem('platform'), 'tool-1'));

        try {
            $this->endpoint()->issue($request(['client_assertion' => $signedByPlatform]));
            self::fail('A forged assertion was accepted.');
        } catch (TokenRequestRefused $tokenRequestRefused) {
            self::assertSame('The signature of the token does not verify.', $tokenRequestRefused->getMessage());
        }

        $this->lti->keySets->with(Lti::TOOL_JWKS);
        $this->expectException(TokenRequestRefused::class);
        $this->expectExceptionMessageIsOrContains('No key set at https://tool.example.com/jwks');
        $this->endpoint()->issue($request());
    }

    public function testAnAssertionIsUsedOnceAndOnlyRegisteredScopesAreGranted(): void
    {
        $now = $this->lti->clock->now()->getTimestamp();
        $tool = new ToolRegistration(Lti::CLIENT, $this->lti->tool->loginUrl, $this->lti->tool->redirectUrls, $this->lti->tool->keySetUrl, [Scope::SCORE]);
        $this->lti->tools->add($tool);
        $assertion = JwtSigner::sign(['iss' => Lti::CLIENT, 'sub' => Lti::CLIENT, 'aud' => 'https://lms.example.org/token', 'iat' => $now, 'exp' => $now + 60, 'jti' => 'once'], Keys::tool());
        $parameters = ['grant_type' => 'client_credentials', 'client_assertion_type' => AccessTokenProvider::ASSERTION_TYPE, 'client_assertion' => $assertion, 'scope' => Scope::SCORE.'  '.Scope::LINE_ITEM.' '.Scope::SCORE];

        $issued = $this->endpoint()->issue($parameters);
        self::assertSame([Scope::SCORE], $issued->scopes);
        self::assertSame(['access_token' => $issued->value, 'token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => Scope::SCORE], $issued->toArray());
        self::assertSame(AccessToken::hash($issued->value), $this->tokens->find(AccessToken::hash($issued->value))?->hash);

        $this->expectExceptionMessageIsOrContains('The assertion has no identifier, or it was already used.');
        $this->endpoint()->issue($parameters);
    }

    public function testTheGuardChecksTheTokenAndItsScope(): void
    {
        $guard = new ServiceGuard($this->tokens);
        $this->tokens->save(new AccessToken(AccessToken::hash('abcdefghijklmnopqrstuvwxyz'), Lti::CLIENT, [Scope::SCORE], $this->lti->clock->now()->modify('+1 minute')));

        self::assertSame(Lti::CLIENT, $guard->authorize('Bearer abcdefghijklmnopqrstuvwxyz', [Scope::LINE_ITEM, Scope::SCORE])->clientId);

        foreach ([null, 'Basic abc', 'Bearer short', 'Bearer abcdefghijklmnopqrstuvwxyz0', 'bearer abcdefghijklmnopqrstuvwxyz', 'Bearer abcdefghijklmnopqrstuvwxyz '] as $header) {
            try {
                $guard->authorize($header, [Scope::SCORE]);
                self::fail((string) $header);
            } catch (AccessDenied $accessDenied) {
                self::assertSame('The access token is missing, unknown or expired.', $accessDenied->getMessage());
                self::assertFalse($accessDenied->insufficientScope);
            }
        }

        try {
            $guard->authorize('Bearer abcdefghijklmnopqrstuvwxyz', [Scope::LINE_ITEM]);
            self::fail('A token without the scope was accepted.');
        } catch (AccessDenied $accessDenied) {
            self::assertSame('The access token does not grant the scope "'.Scope::LINE_ITEM.'".', $accessDenied->getMessage());
            self::assertTrue($accessDenied->insufficientScope);
        }

        $this->lti->clock->sleep(60);
        $this->expectExceptionMessageIsOrContains('The access token is missing, unknown or expired.');
        $guard->authorize('Bearer abcdefghijklmnopqrstuvwxyz', [Scope::SCORE]);
    }

    public function testTheMemoryStoreForgetsOnReset(): void
    {
        $this->tokens->save(new AccessToken('h', 'c', [], $this->lti->clock->now()->modify('+1 minute')));
        $this->tokens->reset();

        self::assertNull($this->tokens->find('h'));
        self::assertSame(3600, TokenEndpoint::DEFAULT_LIFETIME);
        self::assertFalse(new AccessToken('h', 'c', [Scope::SCORE], $this->lti->clock->now())->grants(Scope::LINE_ITEM));
    }

    private function endpoint(): TokenEndpoint
    {
        return new TokenEndpoint('https://lms.example.org/token', $this->lti->tools, $this->tokens, $this->lti->nonces, $this->lti->verifier, $this->lti->clock);
    }

    private function platformClient(): RecordingServiceClient
    {
        return new RecordingServiceClient(function (ServiceRequest $request): ServiceResponse {
            parse_str((string) $request->body, $parameters);

            try {
                return RecordingServiceClient::json(200, $this->endpoint()->issue($parameters)->toArray());
            } catch (TokenRequestRefused $tokenRequestRefused) {
                return RecordingServiceClient::json(400, $tokenRequestRefused->toArray());
            }
        });
    }
}
