<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Tool;

use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\AuthenticationRequest;
use Phil\Lti\Platform\LaunchIssuer;
use Phil\Lti\Platform\LaunchStarter;
use Phil\Lti\Registration\InMemoryToolRegistrations;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Lti\Security\LoginState;
use Phil\Lti\Service\Scope;
use Phil\Lti\Tests\Fixture\Keys;
use Phil\Lti\Tests\Fixture\Lti;
use Phil\Lti\Tests\Fixture\RecordingNonces;
use Phil\Lti\Tool\LaunchValidator;
use Phil\Lti\Tool\LoginInitiator;
use Phil\Lti\Tool\LoginRequest;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The sizes the parameters of a launch are read at, the addresses of the local network the launch refuses by default,
 * the keys its nonces are kept under and the checks that come before its signature.
 */
#[CoversClass(LoginRequest::class)]
#[CoversClass(AuthenticationRequest::class)]
#[CoversClass(LoginInitiator::class)]
#[CoversClass(LaunchStarter::class)]
#[CoversClass(LaunchIssuer::class)]
#[CoversClass(LaunchValidator::class)]
#[CoversClass(JwtVerifier::class)]
final class FlowEdgesTest extends TestCase
{
    private const string LOCAL = 'https://tool.localhost/launch';

    private Lti $lti;

    protected function setUp(): void
    {
        $this->lti = new Lti();
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function initiationParameters(): iterable
    {
        yield 'issuer' => ['iss', 1024];
        yield 'login hint' => ['login_hint', 2048];
        yield 'target' => ['target_link_uri', 2048];
        yield 'message hint' => ['lti_message_hint', 4096];
        yield 'client' => ['client_id', 255];
        yield 'deployment' => ['lti_deployment_id', 255];
    }

    #[DataProvider('initiationParameters')]
    public function testTheParametersOfAnInitiationHaveTheirLimits(string $name, int $max): void
    {
        $this->assertBound(static fn (string $value): LoginRequest => LoginRequest::fromParameters([$name => $value] + ['iss' => 'i', 'login_hint' => 'h', 'target_link_uri' => 't']), $max);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function authenticationParameters(): iterable
    {
        yield 'scope' => ['scope', 64];
        yield 'response type' => ['response_type', 64];
        yield 'response mode' => ['response_mode', 64];
        yield 'prompt' => ['prompt', 64];
        yield 'client' => ['client_id', 255];
        yield 'redirect' => ['redirect_uri', 2048];
        yield 'login hint' => ['login_hint', 2048];
        yield 'nonce' => ['nonce', 255];
        yield 'state' => ['state', 2048];
        yield 'message hint' => ['lti_message_hint', 4096];
    }

    #[DataProvider('authenticationParameters')]
    public function testTheParametersOfAnAuthenticationRequestHaveTheirLimits(string $name, int $max): void
    {
        $base = ['scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post', 'prompt' => 'none', 'client_id' => 'c', 'redirect_uri' => 'r', 'login_hint' => 'h', 'nonce' => 'n'];

        $this->assertBound(static fn (string $value): AuthenticationRequest => AuthenticationRequest::fromParameters([$name => $value] + $base), $max);
    }

    public function testTheTargetOfAnInitiationOnTheLocalNetworkIsRefusedByDefault(): void
    {
        $request = LoginRequest::fromParameters(['iss' => Lti::ISSUER, 'login_hint' => 'h', 'target_link_uri' => self::LOCAL]);
        $redirect = Url::fromString(self::LOCAL, true);

        self::assertSame('lms.example.org', new LoginInitiator($this->lti->platforms, $this->lti->states, $this->lti->clock, allowLocalhost: true)->initiate($request, $redirect)->url->host);

        $this->expectExceptionMessageIsOrContains('The target of the launch is not a web address.');
        new LoginInitiator($this->lti->platforms, $this->lti->states, $this->lti->clock)->initiate($request, $redirect);
    }

    public function testTheLoginAddressOfAToolOnTheLocalNetworkIsRefusedByDefault(): void
    {
        $tool = new ToolRegistration('c', Url::fromString('https://tool.localhost/login', true), [Url::fromString(self::LOCAL, true)], Url::fromString('https://tool.localhost/jwks', true));
        $tools = new InMemoryToolRegistrations()->add($tool, ['d']);

        self::assertSame('c', $this->query(new LaunchStarter(Lti::ISSUER, $tools, true)->loginUrl($tool, 'd', 'h', Url::fromString(self::LOCAL, true)))['client_id']);

        $this->expectException(InvalidUrl::class);
        new LaunchStarter(Lti::ISSUER, $tools)->loginUrl($tool, 'd', 'h', Url::fromString(self::LOCAL, true));
    }

    public function testTheRedirectOfAToolOnTheLocalNetworkIsRefusedByDefault(): void
    {
        $tool = new ToolRegistration(Lti::CLIENT, Url::fromString('https://tool.localhost/login', true), [Url::fromString(self::LOCAL, true)], Url::fromString('https://tool.localhost/jwks', true), Scope::ALL);
        $tools = new InMemoryToolRegistrations()->add($tool, [Lti::DEPLOYMENT]);
        $request = AuthenticationRequest::fromParameters(['scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post', 'prompt' => 'none', 'client_id' => Lti::CLIENT, 'redirect_uri' => self::LOCAL, 'login_hint' => 'h', 'nonce' => 'n']);

        self::assertSame(self::LOCAL, new LaunchIssuer(Lti::ISSUER, $tools, $this->lti->platformKeys, $this->lti->clock, true)->check($request)[1]->toString());

        $this->expectExceptionMessageIsOrContains('The redirect address is not a web address.');
        new LaunchIssuer(Lti::ISSUER, $tools, $this->lti->platformKeys, $this->lti->clock)->check($request);
    }

    public function testALaunchOnTheLocalNetworkIsRefusedByDefault(): void
    {
        $message = new LaunchMessage(MessageType::ResourceLink, Lti::DEPLOYMENT, Url::fromString(self::LOCAL, true), new Person('u'), Roles::of([]), new ResourceLink('l'));
        $token = $this->tokenOf($message);

        $this->lti->states->save(new LoginState('s1', 'n1', Lti::ISSUER, Lti::CLIENT, self::LOCAL, $this->lti->clock->now()->modify('+1 minute')));
        $allowed = new LaunchValidator($this->lti->platforms, $this->lti->states, $this->lti->nonces, $this->lti->verifier, true)->validate($token, 's1');
        self::assertSame(self::LOCAL, $allowed->message->targetLinkUri->toString());

        $this->lti->states->save(new LoginState('s2', 'n1', Lti::ISSUER, Lti::CLIENT, self::LOCAL, $this->lti->clock->now()->modify('+1 minute')));
        $this->lti->nonces->reset();
        $this->expectException(InvalidToken::class);
        $this->lti->validator()->validate($token, 's2');
    }

    public function testTheNonceOfALaunchIsKeptPerPlatform(): void
    {
        $nonces = new RecordingNonces($this->lti->nonces);
        $this->lti->states->save(new LoginState('s1', 'n1', Lti::ISSUER, Lti::CLIENT, Lti::LAUNCH, $this->lti->clock->now()->modify('+1 minute')));

        new LaunchValidator($this->lti->platforms, $this->lti->states, $nonces, $this->lti->verifier)->validate($this->tokenOf(Lti::message()), 's1');

        self::assertSame([[LaunchValidator::NONCE_SCOPE, $this->lti->platform->key()."\nn1"]], $nonces->used);
    }

    public function testATokenForAnotherClientIsRefusedBeforeItsSignature(): void
    {
        $this->lti->states->save(new LoginState('s1', 'n1', Lti::ISSUER, Lti::CLIENT, Lti::LAUNCH, $this->lti->clock->now()->modify('+1 minute')));
        $now = $this->lti->clock->now()->getTimestamp();
        $token = JwtSigner::sign(['iss' => Lti::ISSUER, 'aud' => 'another-client', 'iat' => $now, 'exp' => $now + 60, 'nonce' => 'n1'] + Lti::message()->toClaims(), Keys::platform());

        $this->expectExceptionMessageIsOrContains('The token is not addressed to the client of the tool.');
        $this->lti->validator()->validate($token, 's1');
    }

    public function testAnExpiredTokenIsRefusedByTheVerifier(): void
    {
        $now = $this->lti->clock->now()->getTimestamp();
        $token = JwtSigner::sign(['iss' => Lti::ISSUER, 'iat' => $now - 120, 'exp' => $now - 60], Keys::platform());

        $this->expectExceptionMessageIsOrContains('The token has expired.');
        $this->lti->verifier->verify($token, Url::fromString(Lti::PLATFORM_JWKS));
    }

    /**
     * @return array<array-key, string>
     */
    private function query(Url $url): array
    {
        parse_str((string) $url->query, $query);

        return array_map(static fn (mixed $value): string => \is_string($value) ? $value : '', $query);
    }

    private function tokenOf(LaunchMessage $message): string
    {
        $now = $this->lti->clock->now()->getTimestamp();

        return JwtSigner::sign(['iss' => Lti::ISSUER, 'aud' => Lti::CLIENT, 'iat' => $now, 'exp' => $now + 60, 'nonce' => 'n1'] + $message->toClaims(), Keys::platform());
    }

    /**
     * @param callable(string): mixed $read
     */
    private function assertBound(callable $read, int $max): void
    {
        try {
            $read(str_repeat('x', $max));
        } catch (InvalidRequest $invalidRequest) {
            self::assertStringNotContainsString('at most', $invalidRequest->getMessage(), 'The largest value is within the limit.');
        }

        try {
            $read(str_repeat('x', $max + 1));
            self::fail('A value over the limit was read.');
        } catch (InvalidRequest $invalidRequest) {
            self::assertStringContainsString('at most', $invalidRequest->getMessage());
        }
    }
}
