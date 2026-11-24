<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\PendingRequest;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Store\InMemoryPendingRequests;
use Phil\SamlSp\Store\InMemoryReplayGuard;
use Phil\SamlSpBundle\Application\CompletedLogin;
use Phil\SamlSpBundle\Application\IdentityProviderSettings;
use Phil\SamlSpBundle\Application\LoginStart;
use Phil\SamlSpBundle\Application\SamlLogin;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SamlLogin::class)]
#[CoversClass(LoginStart::class)]
#[CoversClass(CompletedLogin::class)]
final class SamlLoginTest extends TestCase
{
    private MockClock $clock;

    private InMemoryPendingRequests $requests;

    private SamlLogin $login;

    protected function setUp(): void
    {
        $this->clock = new MockClock();
        $this->requests = new InMemoryPendingRequests();
        $providers = Saml::providers($this->clock, [Saml::settings(), Saml::settings('post', binding: Binding::Post), Saml::settings('portal', allowUnsolicited: true)]);
        $this->login = new SamlLogin($providers, new MessageFactory($this->clock), new ResponseProcessor($this->clock, new InMemoryReplayGuard($this->clock)), $this->requests, $this->clock, '/home');
    }

    public function testASignInBoundToTheBrowser(): void
    {
        $start = $this->login->start('acme', '/courses?page=2', true);
        $id = (string) Saml::query($start->outbound->url, 'RelayState');

        self::assertTrue($start->outbound->isRedirect());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $start->browserToken);
        self::assertStringContainsString('ID="'.$id.'"', Saml::inflate($start->outbound->url));
        self::assertNotNull(Saml::query($start->outbound->url, 'Signature'));

        $completed = $this->login->complete('acme', Saml::response($id, $this->clock->now()), $id, $start->browserToken);
        self::assertSame('/courses?page=2', $completed->target);
        self::assertSame(Saml::OBJECT_ID, $completed->identity->subject());
        self::assertNull($this->requests->take($id));
    }

    public function testThePendingRequestRemembersTheSignIn(): void
    {
        $start = $this->login->start('acme', null, true);
        $id = (string) Saml::query($start->outbound->url, 'RelayState');
        $pending = $this->requests->take($id);

        self::assertNotNull($pending);
        self::assertSame('acme', $pending->identityProvider);
        self::assertNull($pending->target);
        self::assertSame(hash('sha256', (string) $start->browserToken), $pending->browserBinding);
        self::assertSame($this->clock->now()->getTimestamp(), $pending->createdAt->getTimestamp());
    }

    public function testASignInNotBoundToTheBrowser(): void
    {
        $start = $this->login->start('post', null, false);
        $id = $start->outbound->fields['RelayState'] ?? '';

        self::assertSame(Binding::Post, $start->outbound->binding);
        self::assertNull($start->browserToken);
        self::assertNull($this->requests->take($id)?->browserBinding);

        $this->requests->save(new PendingRequest($id, 'post', $this->clock->now()));
        self::assertSame('/home', $this->login->complete('post', Saml::response($id, $this->clock->now(), base: 'https://app.example.com/saml/post'), $id, null)->target);
    }

    public function testForcedAuthentication(): void
    {
        $providers = Saml::providers($this->clock, [new IdentityProviderSettings('acme', null, Saml::metadata(), forceAuthn: true)]);
        $login = new SamlLogin($providers, new MessageFactory($this->clock), new ResponseProcessor($this->clock, new InMemoryReplayGuard($this->clock)), $this->requests, $this->clock);

        self::assertStringContainsString('ForceAuthn="true"', Saml::inflate($login->start('acme', null, false)->outbound->url));
        self::assertStringNotContainsString('ForceAuthn', Saml::inflate($this->login->start('acme', null, false)->outbound->url));
    }

    public function testAnotherBrowserIsRefused(): void
    {
        $start = $this->login->start('acme', null, true);
        $id = (string) Saml::query($start->outbound->url, 'RelayState');

        foreach (['other', null] as $token) {
            $this->requests->save(new PendingRequest($id, 'acme', $this->clock->now(), null, hash('sha256', (string) $start->browserToken)));

            try {
                $this->login->complete('acme', Saml::response($id, $this->clock->now()), $id, $token);
                self::fail('Another browser was accepted.');
            } catch (InvalidMessage $exception) {
                self::assertSame('The response reached another browser than the one that sent the request.', $exception->getMessage());
            }
        }
    }

    public function testAResponseAtTheServiceOfAnotherIdentityProviderIsRefused(): void
    {
        $start = $this->login->start('acme', null, false);
        $id = (string) Saml::query($start->outbound->url, 'RelayState');

        $this->expectException(InvalidMessage::class);
        $this->expectExceptionMessageIsOrContains('The response reached the service of another identity provider than the request.');

        $this->login->complete('portal', Saml::response($id, $this->clock->now()), $id, null);
    }

    public function testAnUnsolicitedResponseGoesToALocalRelayState(): void
    {
        $response = static fn (DateTimeImmutable $now): string => Saml::response(null, $now, base: 'https://app.example.com/saml/portal');

        self::assertSame('/courses', $this->login->complete('portal', $response($this->clock->now()), '/courses', null)->target);
        self::assertSame('/home', $this->login->complete('portal', $response($this->clock->now()), 'https://evil.example.org/', null)->target);
        self::assertSame('/home', $this->login->complete('portal', $response($this->clock->now()), null, null)->target);
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function targets(): iterable
    {
        yield 'a path' => ['/a/b?c=d#e', '/a/b?c=d#e'];
        yield 'the root' => ['/', '/'];
        yield 'the longest' => ['/'.str_repeat('a', 2047), '/'.str_repeat('a', 2047)];
        yield 'too long' => ['/'.str_repeat('a', 2048), null];
        yield 'another site' => ['https://evil.example.org/', null];
        yield 'protocol-relative' => ['//evil.example.org/', null];
        yield 'backslash' => ['/\\evil.example.org/', null];
        yield 'a backslash later' => ['/a\\b', null];
        yield 'a line break' => ["/a\nb", null];
        yield 'a tab' => ["/a\tb", null];
        yield 'delete' => ["/a\x7Fb", null];
        yield 'relative' => ['courses', null];
        yield 'empty' => ['', null];
        yield 'nothing' => [null, null];
        yield 'trailing line break' => ["/a\n", null];
    }

    #[DataProvider('targets')]
    public function testOnlyALocalPathIsATarget(?string $target, ?string $expected): void
    {
        self::assertSame($expected, SamlLogin::localPath($target));
    }
}
