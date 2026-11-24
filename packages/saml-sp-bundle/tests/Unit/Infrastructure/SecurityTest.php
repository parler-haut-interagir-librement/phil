<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit\Infrastructure;

use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Message\LogoutProcessor;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Store\InMemoryPendingRequests;
use Phil\SamlSp\Store\InMemoryReplayGuard;
use Phil\SamlSpBundle\Application\Event\SamlLoginSucceeded;
use Phil\SamlSpBundle\Application\Event\SamlResponseRefused;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\IdentityProviderSettings;
use Phil\SamlSpBundle\Application\SamlLogin;
use Phil\SamlSpBundle\Application\SamlLogout;
use Phil\SamlSpBundle\Application\SamlUser;
use Phil\SamlSpBundle\Application\SamlUsers;
use Phil\SamlSpBundle\Infrastructure\Routing\RoutedEndpoints;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAttributes;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator;
use Phil\SamlSpBundle\Infrastructure\Security\SamlUserProvider;
use Phil\SamlSpBundle\Infrastructure\Security\SingleLogoutListener;
use Phil\SamlSpBundle\Tests\Fixtures\RecordingDispatcher;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[CoversClass(SamlAuthenticator::class)]
#[CoversClass(SamlUserProvider::class)]
#[CoversClass(SingleLogoutListener::class)]
#[CoversClass(SamlResponseRefused::class)]
final class SecurityTest extends TestCase
{
    private MockClock $clock;

    private InMemoryPendingRequests $requests;

    private RecordingDispatcher $events;

    private IdentityProviders $providers;

    protected function setUp(): void
    {
        $this->clock = new MockClock();
        $this->requests = new InMemoryPendingRequests();
        $this->events = new RecordingDispatcher();
        $this->providers = Saml::providers($this->clock, [Saml::settings(), Saml::settings('quiet', logout: false)]);
    }

    public function testWhatTheAuthenticatorSupports(): void
    {
        $authenticator = $this->authenticator();

        self::assertTrue($authenticator->supports($this->request()));
        self::assertFalse($authenticator->supports($this->request(route: RoutedEndpoints::SLO)));
        self::assertFalse($authenticator->supports($this->request(method: 'GET')));
        self::assertFalse($authenticator->supports($this->request(fields: [])));
    }

    public function testAPersonIsSignedIn(): void
    {
        $login = $this->login();
        $start = $login->start('acme', '/courses', true);
        $id = (string) Saml::query($start->outbound->url, 'RelayState');
        $authenticator = $this->authenticator($login);
        $request = $this->request(fields: ['SAMLResponse' => Saml::response($id, $this->clock->now()), 'RelayState' => $id], cookie: $start->browserToken);

        $passport = $authenticator->authenticate($request);
        self::assertSame('acme|'.Saml::OBJECT_ID, $passport->getUser()->getUserIdentifier());
        self::assertSame('acme', $passport->getAttribute(SamlAttributes::IDENTITY_PROVIDER));
        self::assertSame('ada.lovelace@example.org', $passport->getAttribute(SamlAttributes::NAME_ID));
        self::assertSame('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', $passport->getAttribute(SamlAttributes::NAME_ID_FORMAT));
        self::assertIsString($passport->getAttribute(SamlAttributes::SESSION_INDEX));
        self::assertSame('/courses', $passport->getAttribute(SamlAttributes::TARGET));
        self::assertSame([SamlLoginSucceeded::class], $this->events->classes());

        $token = $authenticator->createToken($passport, 'main');
        self::assertInstanceOf(PostAuthenticationToken::class, $token);
        self::assertSame('main', $token->getFirewallName());
        self::assertSame(['ROLE_USER', 'ROLE_TEACHER', 'ROLE_ADMIN'], $token->getRoleNames());

        foreach (SamlAttributes::ALL as $name) {
            self::assertSame($passport->getAttribute($name), $token->getAttribute($name));
        }

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/courses', $response->headers->get('Location'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $cleared = $response->headers->getCookies()[0];
        self::assertSame(SamlAuthenticator::COOKIE, $cleared->getName());
        self::assertTrue($cleared->isCleared());
        self::assertTrue($cleared->isSecure());
        self::assertTrue($cleared->isHttpOnly());
        self::assertSame('none', $cleared->getSameSite());
    }

    public function testTheRolesOfTheTokenAreAList(): void
    {
        $user = new InMemoryUser('a', null, [3 => 'ROLE_A', 7 => 'ROLE_B']);
        $token = $this->authenticator()->createToken(new SelfValidatingPassport(new UserBadge('a', static fn (): InMemoryUser => $user)), 'main');

        self::assertSame(['ROLE_A', 'ROLE_B'], $token->getRoleNames());
    }

    public function testTheCookieOverPlainHttp(): void
    {
        $response = new SamlAuthenticator($this->login(), new SamlUsers($this->providers, $this->events), $this->events, false)->onAuthenticationSuccess($this->request(), new PostAuthenticationToken(new InMemoryUser('a', null), 'main', []), 'main');

        self::assertSame('/', $response->headers->get('Location'));
        self::assertFalse($response->headers->getCookies()[0]->isSecure());
        self::assertSame('lax', $response->headers->getCookies()[0]->getSameSite());
    }

    public function testARefusedResponse(): void
    {
        $authenticator = $this->authenticator();

        try {
            $authenticator->authenticate($this->request(fields: ['SAMLResponse' => ['not', 'text'], 'RelayState' => ['x']]));
            self::fail('The response was accepted.');
        } catch (CustomUserMessageAuthenticationException $customUserMessageAuthenticationException) {
            self::assertSame('The sign-in through the identity provider was refused.', $customUserMessageAuthenticationException->getMessageKey());
            self::assertNotNull($customUserMessageAuthenticationException->getPrevious());
            self::assertSame(0, $customUserMessageAuthenticationException->getCode());
        }

        $event = $this->events->events[0] ?? null;
        self::assertInstanceOf(SamlResponseRefused::class, $event);
        self::assertSame('acme', $event->identityProvider);
        self::assertSame('A posted message is base64 of 262144 bytes at most.', $event->reason);
        self::assertSame(InvalidMessage::class, $event->exception);

        $failure = $authenticator->onAuthenticationFailure($this->request(), new AuthenticationException());
        self::assertSame(Response::HTTP_FORBIDDEN, $failure->getStatusCode());
        self::assertSame('The sign-in through the identity provider was refused.', $failure->getContent());
        self::assertSame('text/plain; charset=UTF-8', $failure->headers->get('Content-Type'));
        self::assertSame('no-store, private', $failure->headers->get('Cache-Control'));
    }

    public function testTheUserProvider(): void
    {
        $provider = new SamlUserProvider();
        $user = new SamlUser('acme|1', ['ROLE_USER'], 'acme');

        self::assertSame($user, $provider->refreshUser($user));
        self::assertTrue($provider->supportsClass(SamlUser::class));
        self::assertFalse($provider->supportsClass(InMemoryUser::class));

        try {
            $provider->refreshUser(new InMemoryUser('a', null));
            self::fail('Another user was refreshed.');
        } catch (UnsupportedUserException $unsupportedUserException) {
            self::assertSame('The SAML user provider refreshes no "Symfony\Component\Security\Core\User\InMemoryUser".', $unsupportedUserException->getMessage());
        }

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('acme|1');
    }

    public function testTheLogoutGoesOnToTheIdentityProvider(): void
    {
        $event = new LogoutEvent(new Request(), $this->token());
        $event->setResponse(new Response('local'));
        $this->listener()->onLogout($event);

        $location = (string) $event->getResponse()?->headers->get('Location');
        self::assertStringStartsWith(Saml::IDP_SLO.'?SAMLRequest=', $location);
        $xml = Saml::inflate($location);
        self::assertStringContainsString('<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">ada@example.org</saml:NameID>', $xml);
        self::assertStringContainsString('<samlp:SessionIndex>_s</samlp:SessionIndex>', $xml);
        $listener = new ReflectionMethod(SingleLogoutListener::class, 'onLogout')->getAttributes(AsEventListener::class)[0]->newInstance();
        self::assertSame(LogoutEvent::class, $listener->event);
        self::assertSame(32, $listener->priority);
    }

    public function testALogoutThatStaysLocal(): void
    {
        foreach ([null, $this->token(['phil_saml_sp.identity_provider' => 'quiet']), $this->token(['phil_saml_sp.identity_provider' => 'gone']), $this->token(['phil_saml_sp.name_id' => null]), $this->token(['phil_saml_sp.identity_provider' => 3])] as $token) {
            $event = new LogoutEvent(new Request(), $token);
            $event->setResponse(new Response('local'));
            $this->listener()->onLogout($event);

            self::assertSame('local', $event->getResponse()?->getContent());
        }

        self::assertSame([], $this->events->events);
    }

    public function testALogoutThatFailsStaysLocal(): void
    {
        $providers = Saml::providers($this->clock, [new IdentityProviderSettings('acme', 'https://idp.example.org/metadata', null)]);
        $listener = new SingleLogoutListener(new SamlLogout($providers, new MessageFactory($this->clock), new LogoutProcessor($this->clock), $this->requests, $this->clock, $this->events), $providers, $this->events);
        $event = new LogoutEvent(new Request(), $this->token());
        $event->setResponse(new Response('local'));

        $listener->onLogout($event);

        self::assertSame('local', $event->getResponse()?->getContent());
        self::assertInstanceOf(SamlResponseRefused::class, $this->events->events[0] ?? null);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function token(array $attributes = []): PostAuthenticationToken
    {
        $token = new PostAuthenticationToken(new InMemoryUser('a', null), 'main', []);

        foreach ($attributes + [SamlAttributes::IDENTITY_PROVIDER => 'acme', SamlAttributes::NAME_ID => 'ada@example.org', SamlAttributes::NAME_ID_FORMAT => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', SamlAttributes::SESSION_INDEX => '_s'] as $name => $value) {
            if (null !== $value) {
                $token->setAttribute($name, $value);
            }
        }

        return $token;
    }

    private function listener(): SingleLogoutListener
    {
        return new SingleLogoutListener(new SamlLogout($this->providers, new MessageFactory($this->clock), new LogoutProcessor($this->clock), $this->requests, $this->clock, $this->events), $this->providers, $this->events);
    }

    private function login(): SamlLogin
    {
        return new SamlLogin($this->providers, new MessageFactory($this->clock), new ResponseProcessor($this->clock, new InMemoryReplayGuard($this->clock)), $this->requests, $this->clock);
    }

    private function authenticator(?SamlLogin $login = null): SamlAuthenticator
    {
        return new SamlAuthenticator($login ?? $this->login(), new SamlUsers($this->providers, $this->events), $this->events);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function request(string $route = RoutedEndpoints::ACS, string $method = 'POST', array $fields = ['SAMLResponse' => 'x'], ?string $cookie = null): Request
    {
        $request = Request::create('https://app.example.com/saml/acme/acs', $method, $fields, null === $cookie ? [] : [SamlAuthenticator::COOKIE => $cookie]);
        $request->attributes->set('_route', $route);
        $request->attributes->set('idp', 'acme');

        return $request;
    }
}
