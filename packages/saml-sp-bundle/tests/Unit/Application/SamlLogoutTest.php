<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit\Application;

use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Binding\HttpRedirect;
use Phil\SamlSp\Exception\IdentityProviderRefused;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Message\LogoutProcessor;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\PendingRequest;
use Phil\SamlSp\Message\ReceivedMessage;
use Phil\SamlSp\Metadata\MetadataLoader;
use Phil\SamlSp\Store\InMemoryPendingRequests;
use Phil\SamlSpBundle\Application\Event\SamlLogoutReceived;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\IdentityProviderSettings;
use Phil\SamlSpBundle\Application\LogoutOutcome;
use Phil\SamlSpBundle\Application\SamlLogout;
use Phil\SamlSpBundle\Application\ServiceProviderSettings;
use Phil\SamlSpBundle\Tests\Fixtures\FixedEndpoints;
use Phil\SamlSpBundle\Tests\Fixtures\NoFetcher;
use Phil\SamlSpBundle\Tests\Fixtures\RecordingDispatcher;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SamlLogout::class)]
#[CoversClass(LogoutOutcome::class)]
#[CoversClass(SamlLogoutReceived::class)]
final class SamlLogoutTest extends TestCase
{
    private MockClock $clock;

    private InMemoryPendingRequests $requests;

    private RecordingDispatcher $events;

    protected function setUp(): void
    {
        $this->clock = new MockClock();
        $this->requests = new InMemoryPendingRequests();
        $this->events = new RecordingDispatcher();
    }

    public function testTheServiceProviderSignsThePersonOut(): void
    {
        $logout = $this->logout(Saml::providers($this->clock));
        $outbound = $logout->start('acme', 'ada@example.org', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', '_session');
        self::assertNotNull($outbound);
        $id = (string) Saml::query($outbound->url, 'RelayState');

        self::assertStringStartsWith(Saml::IDP_SLO.'?SAMLRequest=', $outbound->url);
        self::assertNotNull(Saml::query($outbound->url, 'Signature'));
        $xml = Saml::inflate($outbound->url);
        self::assertStringContainsString('ID="'.$id.'"', $xml);
        self::assertStringContainsString('<samlp:SessionIndex>_session</samlp:SessionIndex>', $xml);
        $pending = $this->requests->take($id);
        self::assertNotNull($pending);
        self::assertSame('/bye', $pending->target);
        self::assertSame('acme', $pending->identityProvider);
        $this->requests->save($pending);

        $outcome = $logout->receive('acme', new ReceivedMessage(Saml::logoutResponse($this->clock->now(), $id), Binding::Redirect, $id, true));
        self::assertSame('/bye', $outcome->target);
        self::assertNull($outcome->request);
        self::assertNull($outcome->response);
    }

    public function testNoLogoutWithoutAServiceOrAKey(): void
    {
        $quiet = $this->logout(Saml::providers($this->clock, [Saml::settings(logout: false)]));
        self::assertNull($quiet->start('acme', 'ada', null, null));

        $unsigned = $this->logout(new IdentityProviders([Saml::settings()], new ServiceProviderSettings(null, null, [], null, false), new MetadataLoader(new NoFetcher(), $this->clock), new FixedEndpoints()));
        self::assertNull($unsigned->start('acme', 'ada', null, null));

        $noService = $this->logout(Saml::providers($this->clock, [new IdentityProviderSettings('acme', null, Saml::metadata(false))]));
        self::assertNull($noService->start('acme', 'ada', null, null));
        self::assertNull($this->requests->take('anything'));
    }

    public function testAResponseToAnotherRequestIsRefused(): void
    {
        $logout = $this->logout(Saml::providers($this->clock, [Saml::settings(), Saml::settings('other')]));
        $this->requests->save(new PendingRequest('_mine', 'other', $this->clock->now(), '/elsewhere'));

        try {
            $logout->receive('acme', new ReceivedMessage(Saml::logoutResponse($this->clock->now(), '_mine'), Binding::Redirect, '_mine', true));
            self::fail('A response to the request of another identity provider was accepted.');
        } catch (InvalidMessage $invalidMessage) {
            self::assertSame('The logout response does not answer the logout request of this browser.', $invalidMessage->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The logout response does not answer the logout request of this browser.');
        $logout->receive('acme', new ReceivedMessage(Saml::logoutResponse($this->clock->now(), '_mine'), Binding::Redirect, null, true));
    }

    public function testAPartialLogoutIsRefused(): void
    {
        $logout = $this->logout(Saml::providers($this->clock));
        $this->requests->save(new PendingRequest('_mine', 'acme', $this->clock->now()));

        $this->expectException(IdentityProviderRefused::class);
        $logout->receive('acme', new ReceivedMessage(Saml::logoutResponse($this->clock->now(), '_mine', 'urn:oasis:names:tc:SAML:2.0:status:Responder'), Binding::Redirect, '_mine', true));
    }

    public function testTheDefaultTargetOfAResponse(): void
    {
        $logout = $this->logout(Saml::providers($this->clock));
        $this->requests->save(new PendingRequest('_mine', 'acme', $this->clock->now()));

        self::assertSame('/bye', $logout->receive('acme', new ReceivedMessage(Saml::logoutResponse($this->clock->now(), '_mine'), Binding::Redirect, '_mine', true))->target);
    }

    public function testTheTargetOfTheRequestWinsOverTheDefaultOne(): void
    {
        $logout = $this->logout(Saml::providers($this->clock));
        $this->requests->save(new PendingRequest('_mine', 'acme', $this->clock->now(), '/goodbye'));

        self::assertSame('/goodbye', $logout->receive('acme', new ReceivedMessage(Saml::logoutResponse($this->clock->now(), '_mine'), Binding::Redirect, '_mine', true))->target);
    }

    public function testTheIdentityProviderSignsThePersonOut(): void
    {
        $logout = $this->logout(Saml::providers($this->clock));
        $outcome = $logout->receive('acme', new ReceivedMessage(Saml::logoutRequest($this->clock->now()), Binding::Post, 'relay', false, 'SAMLRequest'));

        self::assertNotNull($outcome->request);
        self::assertSame('ada.lovelace@example.org', $outcome->request->nameId);
        self::assertNull($outcome->target);
        self::assertNotNull($outcome->response);
        self::assertSame('relay', Saml::query($outcome->response->url, 'RelayState'));
        $answer = Saml::inflate($outcome->response->url, 'SAMLResponse');
        self::assertStringContainsString('InResponseTo="'.$outcome->request->id.'"', $answer);
        self::assertTrue(HttpRedirect::receive((string) parse_url($outcome->response->url, \PHP_URL_QUERY), [Saml::pair('sp')->certificate])->signatureVerified);
        self::assertSame([SamlLogoutReceived::class], $this->events->classes());
        $event = $this->events->events[0];
        self::assertInstanceOf(SamlLogoutReceived::class, $event);
        self::assertSame('acme', $event->identityProvider);
        self::assertSame($outcome->request, $event->request);
    }

    private function logout(IdentityProviders $providers): SamlLogout
    {
        return new SamlLogout($providers, new MessageFactory($this->clock), new LogoutProcessor($this->clock), $this->requests, $this->clock, $this->events, '/bye');
    }

    public function testTheDefaultLogoutTarget(): void
    {
        $logout = new SamlLogout(Saml::providers($this->clock), new MessageFactory($this->clock), new LogoutProcessor($this->clock), $this->requests, $this->clock, $this->events);
        $outbound = $logout->start('acme', 'ada', null, null);
        self::assertNotNull($outbound);

        self::assertSame('/', $this->requests->take((string) Saml::query($outbound->url, 'RelayState'))?->target);
        self::assertSame(Url::fromString(Saml::IDP_SLO)->host, parse_url($outbound->url, \PHP_URL_HOST));
    }
}
