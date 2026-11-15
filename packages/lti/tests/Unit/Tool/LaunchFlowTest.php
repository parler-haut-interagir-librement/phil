<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Tool;

use DateTimeImmutable;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Http\FormPost;
use Phil\Lti\Http\UrlQuery;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\AuthenticationRequest;
use Phil\Lti\Platform\LaunchIssuer;
use Phil\Lti\Platform\LaunchStarter;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Lti\Security\LoginState;
use Phil\Lti\Tests\Fixture\Keys;
use Phil\Lti\Tests\Fixture\Lti;
use Phil\Lti\Tool\LaunchValidator;
use Phil\Lti\Tool\LoginInitiator;
use Phil\Lti\Tool\LoginRedirect;
use Phil\Lti\Tool\LoginRequest;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The whole launch, from the platform's login initiation to the tool's verified launch, and each check on the way.
 */
#[CoversClass(LaunchStarter::class)]
#[CoversClass(LoginRequest::class)]
#[CoversClass(LoginInitiator::class)]
#[CoversClass(LoginRedirect::class)]
#[CoversClass(LoginState::class)]
#[CoversClass(AuthenticationRequest::class)]
#[CoversClass(LaunchIssuer::class)]
#[CoversClass(LaunchValidator::class)]
#[CoversClass(FormPost::class)]
#[CoversClass(UrlQuery::class)]
final class LaunchFlowTest extends TestCase
{
    private Lti $lti;

    protected function setUp(): void
    {
        $this->lti = new Lti();
    }

    public function testAPlatformLaunchesTheTool(): void
    {
        $post = $this->launch();
        $launch = $this->lti->validator()->validate($post->fields['id_token'], $post->fields['state']);

        self::assertSame(Lti::ISSUER, $launch->issuer);
        self::assertSame(Lti::CLIENT, $launch->clientId);
        self::assertSame('user-7', $launch->subject());
        self::assertSame('link-1', $launch->message->resourceLink?->id);
        self::assertSame('Ada Lovelace', $launch->message->person->name);
        self::assertSame(Lti::LAUNCH, $launch->message->targetLinkUri->toString());
        self::assertSame(Lti::CLIENT, $launch->claims['azp']);
    }

    public function testTheLoginUrlOfThePlatformCarriesTheInitiation(): void
    {
        $url = new LaunchStarter(Lti::ISSUER, $this->lti->tools)->loginUrl($this->lti->tool, Lti::DEPLOYMENT, 'hint-7', Url::fromString(Lti::LAUNCH.'?x=1'), 'link-1');
        parse_str((string) $url->query, $query);

        self::assertSame('https://tool.example.com/lti/login', $url->scheme.'://'.$url->host.$url->path);
        self::assertSame(['iss' => Lti::ISSUER, 'login_hint' => 'hint-7', 'target_link_uri' => Lti::LAUNCH.'?x=1', 'client_id' => Lti::CLIENT, 'lti_deployment_id' => Lti::DEPLOYMENT, 'lti_message_hint' => 'link-1'], $query);

        $bare = new LaunchStarter(Lti::ISSUER, $this->lti->tools)->loginUrl($this->lti->tool, Lti::DEPLOYMENT, 'hint-7', Url::fromString(Lti::LAUNCH));
        self::assertStringNotContainsString('lti_message_hint', (string) $bare->query);
    }

    public function testThePlatformStartsOnlyRegisteredLaunches(): void
    {
        $starter = new LaunchStarter(Lti::ISSUER, $this->lti->tools);
        $other = new ToolRegistration('other', Url::fromString('https://other.example.com/login'), [Url::fromString('https://other.example.com/launch')], Url::fromString('https://other.example.com/jwks'));
        $cases = [
            'unknown tool' => [static fn (): Url => $starter->loginUrl($other, Lti::DEPLOYMENT, 'h', Url::fromString('https://other.example.com/launch')), UnknownRegistration::class, 'No tool is registered under the client "other".'],
            'unknown deployment' => [fn (): Url => $starter->loginUrl($this->lti->tool, 'nope', 'h', Url::fromString(Lti::LAUNCH)), UnknownRegistration::class, 'The deployment "nope" is not registered.'],
            'foreign target' => [fn (): Url => $starter->loginUrl($this->lti->tool, Lti::DEPLOYMENT, 'h', Url::fromString('https://evil.example.com/')), InvalidRequest::class, 'The target of the launch is not on an origin of the tool.'],
        ];

        foreach ($cases as $name => [$call, $class, $message]) {
            try {
                $call();
                self::fail($name);
            } catch (InvalidRequest|UnknownRegistration $exception) {
                self::assertInstanceOf($class, $exception, $name);
                self::assertSame($message, $exception->getMessage(), $name);
            }
        }
    }

    public function testTheToolAnswersTheInitiationWithAnAuthenticationRequest(): void
    {
        $redirect = $this->initiate(['lti_message_hint' => 'link-1', 'client_id' => Lti::CLIENT, 'lti_deployment_id' => Lti::DEPLOYMENT]);
        parse_str((string) $redirect->url->query, $query);

        self::assertSame('https://lms.example.org/auth', $redirect->url->scheme.'://'.$redirect->url->host.$redirect->url->path);
        self::assertSame([
            'scope' => 'openid',
            'response_type' => 'id_token',
            'response_mode' => 'form_post',
            'prompt' => 'none',
            'client_id' => Lti::CLIENT,
            'redirect_uri' => Lti::LAUNCH,
            'login_hint' => 'hint-7',
            'state' => $redirect->state->state,
            'nonce' => $redirect->state->nonce,
            'lti_message_hint' => 'link-1',
        ], $query);
        self::assertSame(43, \strlen($redirect->state->state));
        self::assertNotSame($redirect->state->state, $redirect->state->nonce);
        self::assertSame(Lti::ISSUER, $redirect->state->issuer);
        self::assertSame(Lti::CLIENT, $redirect->state->clientId);
        self::assertEquals(new DateTimeImmutable('2026-11-14 12:10:00 UTC'), $redirect->state->expiresAt);
        self::assertSame($redirect->state, $this->lti->states->take($redirect->state->state));
    }

    public function testTheInitiationIsChecked(): void
    {
        $platform = new PlatformRegistration(Lti::ISSUER, 'second', Url::fromString('https://lms.example.org/auth'), Url::fromString('https://lms.example.org/token'), Url::fromString(Lti::PLATFORM_JWKS));
        $cases = [
            'unknown issuer' => [['iss' => 'https://other.example.org'], UnknownRegistration::class, 'No platform is registered for the issuer "https://other.example.org", or several are and the client is not named.'],
            'unknown client' => [['client_id' => 'nope'], UnknownRegistration::class, 'No platform is registered for the issuer "https://lms.example.org" and the client "nope".'],
            'unknown deployment' => [['lti_deployment_id' => 'nope'], UnknownRegistration::class, 'The deployment "nope" is not registered.'],
            'foreign target' => [['target_link_uri' => 'https://evil.example.com/lti/launch'], InvalidRequest::class, 'The target of the launch is not on the origin of the tool.'],
            'other port' => [['target_link_uri' => 'https://tool.example.com:8443/lti/launch'], InvalidRequest::class, 'The target of the launch is not on the origin of the tool.'],
            'plain http' => [['target_link_uri' => 'http://tool.example.com/lti/launch'], InvalidRequest::class, 'The target of the launch is not on the origin of the tool.'],
            'not an address' => [['target_link_uri' => 'tool'], InvalidRequest::class, 'The target of the launch is not a web address.'],
        ];

        foreach ($cases as $name => [$parameters, $class, $message]) {
            try {
                $this->initiate($parameters);
                self::fail($name);
            } catch (InvalidRequest|UnknownRegistration $exception) {
                self::assertInstanceOf($class, $exception, $name);
                self::assertSame($message, $exception->getMessage(), $name);
            }
        }

        $this->lti->platforms->add($platform, [Lti::DEPLOYMENT]);
        $this->expectExceptionMessageIsOrContains('or several are and the client is not named.');
        $this->initiate();
    }

    public function testTheParametersOfAnInitiationAreRead(): void
    {
        $request = LoginRequest::fromParameters(['iss' => 'i', 'login_hint' => 'h', 'target_link_uri' => 't', 'lti_message_hint' => '', 'client_id' => 'c', 'lti_deployment_id' => 'd']);

        self::assertSame(['i', 'h', 't', null, 'c', 'd'], [$request->issuer, $request->loginHint, $request->targetLinkUri, $request->messageHint, $request->clientId, $request->deploymentId]);

        $cases = [
            [['login_hint' => 'h', 'target_link_uri' => 't'], 'A login initiation names the issuer, "iss".'],
            [['iss' => 'i', 'target_link_uri' => 't'], 'A login initiation carries the "login_hint".'],
            [['iss' => 'i', 'login_hint' => 'h'], 'A login initiation carries the "target_link_uri".'],
            [['iss' => ['i'], 'login_hint' => 'h', 'target_link_uri' => 't'], 'The parameter "iss" is a string of 1024 bytes at most.'],
            [['iss' => str_repeat('i', 1025), 'login_hint' => 'h', 'target_link_uri' => 't'], 'The parameter "iss" is a string of 1024 bytes at most.'],
            [['iss' => 'i', 'login_hint' => "\xff", 'target_link_uri' => 't'], 'The parameter "login_hint" is a string of 2048 bytes at most.'],
        ];

        foreach ($cases as [$parameters, $message]) {
            try {
                LoginRequest::fromParameters($parameters);
                self::fail($message);
            } catch (InvalidRequest $invalidRequest) {
                self::assertSame($message, $invalidRequest->getMessage());
            }
        }

        self::assertSame(1024, \strlen(LoginRequest::fromParameters(['iss' => str_repeat('i', 1024), 'login_hint' => 'h', 'target_link_uri' => 't'])->issuer));
    }

    public function testTheAuthenticationRequestIsRead(): void
    {
        $request = AuthenticationRequest::fromParameters($this->authentication() + ['lti_message_hint' => 'm']);

        self::assertSame([Lti::CLIENT, Lti::LAUNCH, 'hint-7', 'n-1', 's-1', 'm'], [$request->clientId, $request->redirectUri, $request->loginHint, $request->nonce, $request->state, $request->messageHint]);
        self::assertSame('openid', Lti::CLIENT === AuthenticationRequest::fromParameters(['scope' => 'email openid'] + $this->authentication())->clientId ? 'openid' : '');
        self::assertNull(AuthenticationRequest::fromParameters(['state' => ''] + $this->authentication())->state);

        $cases = [
            [['scope' => 'email'], 'An authentication request of LTI has "scope=openid".'],
            [['response_type' => 'code'], 'An authentication request of LTI has "response_type=id_token".'],
            [['response_mode' => 'query'], 'An authentication request of LTI has "response_mode=form_post".'],
            [['prompt' => 'login'], 'An authentication request of LTI has "prompt=none".'],
            [['prompt' => null], 'An authentication request of LTI has "prompt=none".'],
            [['client_id' => null], 'An authentication request names the client.'],
            [['redirect_uri' => null], 'An authentication request names the redirect address.'],
            [['login_hint' => null], 'An authentication request carries the login hint.'],
            [['nonce' => null], 'An authentication request carries a nonce.'],
            [['nonce' => str_repeat('n', 256)], 'The parameter "nonce" is a string of 255 bytes at most.'],
        ];

        foreach ($cases as [$changes, $message]) {
            try {
                AuthenticationRequest::fromParameters(array_filter($changes + $this->authentication(), static fn (mixed $value): bool => null !== $value));
                self::fail($message);
            } catch (InvalidRequest $invalidRequest) {
                self::assertSame($message, $invalidRequest->getMessage());
            }
        }
    }

    public function testThePlatformChecksTheAuthenticationRequest(): void
    {
        $issuer = $this->lti->issuer();
        $message = Lti::message();
        $cases = [
            'unknown client' => [['client_id' => 'nope'], 'hint-7', UnknownRegistration::class, 'No tool is registered under the client "nope".'],
            'other redirect' => [['redirect_uri' => 'https://tool.example.com/other'], 'hint-7', InvalidRequest::class, 'The redirect address is not registered for the tool.'],
            'bad redirect' => [['redirect_uri' => 'tool'], 'hint-7', InvalidRequest::class, 'The redirect address is not a web address.'],
            'other person' => [[], 'hint-8', InvalidRequest::class, 'The login hint is not the one of the person signed in.'],
        ];

        foreach ($cases as $name => [$changes, $hint, $class, $text]) {
            try {
                $issuer->authorize(AuthenticationRequest::fromParameters($changes + $this->authentication()), $hint, $message);
                self::fail($name);
            } catch (InvalidRequest|UnknownRegistration $exception) {
                self::assertInstanceOf($class, $exception, $name);
                self::assertSame($text, $exception->getMessage(), $name);
            }
        }

        try {
            $issuer->authorize(AuthenticationRequest::fromParameters($this->authentication()), 'hint-7', new LaunchMessage(MessageType::ResourceLink, 'nope', $message->targetLinkUri, $message->person, $message->roles, $message->resourceLink));
            self::fail('An unknown deployment was launched.');
        } catch (UnknownRegistration $unknownRegistration) {
            self::assertSame('The deployment "nope" is not registered.', $unknownRegistration->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('An anonymous launch carries nothing of the person.');
        $issuer->authorize(AuthenticationRequest::fromParameters($this->authentication()), 'hint-7', new LaunchMessage(MessageType::ResourceLink, Lti::DEPLOYMENT, $message->targetLinkUri, new Person(null, 'Ada'), $message->roles, $message->resourceLink));
    }

    public function testTheIdTokenOfThePlatform(): void
    {
        $post = $this->lti->issuer()->authorize(AuthenticationRequest::fromParameters($this->authentication()), 'hint-7', Lti::message());
        $jwt = Jwt::parse($post->fields['id_token']);
        $now = $this->lti->clock->now()->getTimestamp();

        self::assertSame(Lti::LAUNCH, $post->action->toString());
        self::assertSame('s-1', $post->fields['state']);
        self::assertSame('platform-1', $jwt->keyId());
        self::assertSame([Lti::ISSUER, Lti::CLIENT, Lti::CLIENT, $now, $now + 300, 'n-1'], [$jwt->claims['iss'], $jwt->claims['aud'], $jwt->claims['azp'], $jwt->claims['iat'], $jwt->claims['exp'], $jwt->claims['nonce']]);

        $withoutState = $this->lti->issuer()->authorize(AuthenticationRequest::fromParameters(['state' => null] + $this->authentication()), 'hint-7', Lti::message());
        self::assertSame(['id_token'], array_keys($withoutState->fields));
        self::assertSame(LaunchIssuer::LIFETIME, 300);
    }

    public function testAStateIsUsedOnce(): void
    {
        $post = $this->launch();
        $this->lti->validator()->validate($post->fields['id_token'], $post->fields['state']);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('The state of the launch is unknown, already used or expired.');
        $this->lti->validator()->validate($post->fields['id_token'], $post->fields['state']);
    }

    public function testAnExpiredStateIsRefused(): void
    {
        $post = $this->launch();
        $this->lti->clock->sleep(601);

        $this->expectExceptionMessageIsOrContains('The state of the launch is unknown, already used or expired.');
        $this->lti->validator()->validate($post->fields['id_token'], $post->fields['state']);
    }

    public function testANonceIsUsedOnce(): void
    {
        $post = $this->launch();
        $redirect = $this->initiate();
        $copy = new LoginState($redirect->state->state, $this->nonceOf($post), Lti::ISSUER, Lti::CLIENT, Lti::LAUNCH, $redirect->state->expiresAt);
        $this->lti->validator()->validate($post->fields['id_token'], $post->fields['state']);
        $this->lti->states->save($copy);

        $this->expectExceptionMessageIsOrContains('The nonce of the token was already used.');
        $this->lti->validator()->validate($post->fields['id_token'], $copy->state);
    }

    public function testTheTokenMustMatchTheState(): void
    {
        $post = $this->launch();
        $other = $this->initiate();

        $this->expectExceptionMessageIsOrContains('The nonce of the token is not the one of the login.');
        $this->lti->validator()->validate($post->fields['id_token'], $other->state->state);
    }

    public function testATokenOfAnotherPlatformKeyIsRefused(): void
    {
        $post = $this->launch();
        $this->lti->withPlatformKeys(new KeySet([Keys::tool()->publicKey]));

        $this->expectExceptionMessageIsOrContains('The key set does not hold the key "platform-1".');
        $this->lti->validator()->validate($post->fields['id_token'], $post->fields['state']);
    }

    public function testTheAudienceIsChecked(): void
    {
        $state = $this->initiate()->state;
        $now = $this->lti->clock->now()->getTimestamp();
        $base = ['iss' => Lti::ISSUER, 'aud' => Lti::CLIENT, 'iat' => $now, 'exp' => $now + 60, 'nonce' => $state->nonce];
        $cases = [
            'other issuer' => [['iss' => 'https://other.example.org'], 'The token is not issued by the platform of the login.'],
            'other audience' => [['aud' => 'other'], 'The token is not addressed to the client of the tool.'],
            'audience number' => [['aud' => 42], 'The token is not addressed to the client of the tool.'],
            'list without azp' => [['aud' => [Lti::CLIENT, 'other']], 'The authorized party of the token is not the client of the tool.'],
            'other azp' => [['azp' => 'other'], 'The authorized party of the token is not the client of the tool.'],
            'subject number' => [['sub' => 7], 'The subject of the token is a string.'],
        ];

        foreach ($cases as $name => [$changes, $message]) {
            try {
                LaunchValidator::checkAudience(Jwt::parse(JwtSigner::sign($changes + $base, Keys::platform())), Lti::ISSUER, Lti::CLIENT);
                self::fail($name);
            } catch (InvalidToken $invalidToken) {
                self::assertSame($message, $invalidToken->getMessage(), $name);
            }
        }

        LaunchValidator::checkAudience(Jwt::parse(JwtSigner::sign(['aud' => [Lti::CLIENT, 'other'], 'azp' => Lti::CLIENT] + $base, Keys::platform())), Lti::ISSUER, Lti::CLIENT);
        LaunchValidator::checkAudience(Jwt::parse(JwtSigner::sign(['aud' => [Lti::CLIENT]] + $base, Keys::platform())), Lti::ISSUER, Lti::CLIENT);
        self::addToAssertionCount(2);
    }

    public function testTheDeploymentAndTheTargetAreThoseOfTheLogin(): void
    {
        $this->lti->tools->add($this->lti->tool, ['deployment-2']);
        $state = $this->initiate()->state;
        $post = $this->lti->issuer()->authorize(AuthenticationRequest::fromParameters(['state' => $state->state, 'nonce' => $state->nonce] + $this->authentication()), 'hint-7', new LaunchMessage(MessageType::ResourceLink, 'deployment-2', Url::fromString(Lti::LAUNCH), new Person('u'), Roles::of([]), new ResourceLink('l')));

        try {
            $this->lti->validator()->validate($post->fields['id_token'], $state->state);
            self::fail('An unregistered deployment was launched.');
        } catch (UnknownRegistration $unknownRegistration) {
            self::assertSame('The deployment "deployment-2" is not registered.', $unknownRegistration->getMessage());
        }

        $state = $this->initiate()->state;
        $post = $this->lti->issuer()->authorize(AuthenticationRequest::fromParameters(['state' => $state->state, 'nonce' => $state->nonce] + $this->authentication()), 'hint-7', Lti::message(target: 'https://tool.example.com/other'));

        $this->expectExceptionMessageIsOrContains('The target of the launch is not the one of the login.');
        $this->lti->validator()->validate($post->fields['id_token'], $state->state);
    }

    public function testARegistrationRemovedBetweenTheStepsIsRefused(): void
    {
        $state = new LoginState('s', 'n', 'https://gone.example.org', 'c', Lti::LAUNCH, $this->lti->clock->now()->modify('+1 minute'));
        $this->lti->states->save($state);

        $this->expectException(UnknownRegistration::class);
        $this->lti->validator()->validate('a.b.c', 's');
    }

    public function testTheStateLifetimeIsConfigurable(): void
    {
        $redirect = new LoginInitiator($this->lti->platforms, $this->lti->states, $this->lti->clock, 30)->initiate(LoginRequest::fromParameters(['iss' => Lti::ISSUER, 'login_hint' => 'h', 'target_link_uri' => Lti::LAUNCH]), Url::fromString(Lti::LAUNCH));

        self::assertEquals(new DateTimeImmutable('2026-11-14 12:00:30 UTC'), $redirect->state->expiresAt);
        self::assertSame(600, LoginInitiator::DEFAULT_STATE_LIFETIME);
    }

    public function testAnAuthorizationUrlWithAQueryKeepsIt(): void
    {
        $url = UrlQuery::with(Url::fromString('https://lms.example.org:8443/auth?tenant=a#top'), ['x' => 'a b']);

        self::assertSame('https://lms.example.org:8443/auth?tenant=a&x=a%20b', $url->toString());
        self::assertSame('https://tool.localhost/?y=1', UrlQuery::with(Url::fromString('https://tool.localhost/', true), ['y' => '1'], true)->toString());
    }

    /**
     * @param array<string, string> $parameters
     */
    private function initiate(array $parameters = []): LoginRedirect
    {
        return $this->lti->initiator()->initiate(LoginRequest::fromParameters($parameters + ['iss' => Lti::ISSUER, 'login_hint' => 'hint-7', 'target_link_uri' => Lti::LAUNCH]), Url::fromString(Lti::LAUNCH));
    }

    private function launch(): FormPost
    {
        $redirect = $this->initiate();
        parse_str((string) $redirect->url->query, $query);

        return $this->lti->issuer()->authorize(AuthenticationRequest::fromParameters($query), 'hint-7', Lti::message());
    }

    private function nonceOf(FormPost $post): string
    {
        $nonce = Jwt::parse($post->fields['id_token'])->claims['nonce'];
        self::assertIsString($nonce);

        return $nonce;
    }

    /**
     * @return array<string, string>
     */
    private function authentication(): array
    {
        return ['scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post', 'prompt' => 'none', 'client_id' => Lti::CLIENT, 'redirect_uri' => Lti::LAUNCH, 'login_hint' => 'hint-7', 'nonce' => 'n-1', 'state' => 's-1'];
    }
}
