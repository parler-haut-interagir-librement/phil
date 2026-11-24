<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit\Infrastructure;

use Phil\SamlSp\Binding\HttpRedirect;
use Phil\SamlSp\Exception\MetadataUnavailable;
use Phil\SamlSp\Message\LogoutProcessor;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\PendingRequest;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSp\Signature\Signer;
use Phil\SamlSp\Store\InMemoryPendingRequests;
use Phil\SamlSp\Store\InMemoryReplayGuard;
use Phil\SamlSpBundle\Application\Event\SamlLogoutReceived;
use Phil\SamlSpBundle\Application\Event\SamlResponseRefused;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\IdentityProviderSettings;
use Phil\SamlSpBundle\Application\SamlLogin;
use Phil\SamlSpBundle\Application\SamlLogout;
use Phil\SamlSpBundle\Infrastructure\Controller\SamlController;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAttributes;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator;
use Phil\SamlSpBundle\Tests\Fixtures\RecordingDispatcher;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

#[CoversClass(SamlController::class)]
final class ControllerTest extends TestCase
{
    private MockClock $clock;

    private InMemoryPendingRequests $requests;

    private RecordingDispatcher $events;

    private TokenStorage $tokens;

    protected function setUp(): void
    {
        $this->clock = new MockClock();
        $this->requests = new InMemoryPendingRequests();
        $this->events = new RecordingDispatcher();
        $this->tokens = new TokenStorage();
    }

    public function testTheMetadata(): void
    {
        $response = $this->controller()->metadata('acme');

        self::assertSame('application/samlmetadata+xml', $response->headers->get('Content-Type'));
        self::assertSame('inline; filename="acme-metadata.xml"', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('WantAssertionsSigned="true"', (string) $response->getContent());

        $relaxed = $this->controller(Saml::providers($this->clock, [new IdentityProviderSettings('acme', null, Saml::metadata(), wantAssertionsSigned: false)]))->metadata('acme');
        self::assertStringContainsString('WantAssertionsSigned="false"', (string) $relaxed->getContent());
    }

    public function testAnUnknownIdentityProvider(): void
    {
        foreach ([$this->controller()->metadata(...), $this->controller()->assertionConsumerService(...)] as $action) {
            try {
                $action('other');
                self::fail('An unknown identity provider was served.');
            } catch (NotFoundHttpException $exception) {
                self::assertSame('No identity provider is configured under "other".', $exception->getMessage());
            }
        }

        $this->expectException(NotFoundHttpException::class);
        $this->controller()->login('other', new Request());
    }

    public function testAnUnknownIdentityProviderAtTheLogoutService(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->singleLogout('other', new Request());
    }

    public function testASignInStarts(): void
    {
        $response = $this->controller()->login('acme', Request::create('/saml/acme/login', 'GET', ['target' => '/courses']));
        $location = (string) $response->headers->get('Location');
        $id = (string) Saml::query($location, 'RelayState');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/courses', $this->requests->take($id)?->target);
        $cookie = $response->headers->getCookies()[0];
        self::assertSame(SamlAuthenticator::COOKIE, $cookie->getName());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $cookie->getValue());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertFalse($cookie->isRaw());
        self::assertSame('none', $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
        self::assertEqualsWithDelta(time() + 300, $cookie->getExpiresTime(), 5);
    }

    public function testASignInWithoutBindingOrOverPlainHttp(): void
    {
        $unbound = $this->controller(bind: false)->login('acme', Request::create('/saml/acme/login', 'GET', ['target' => ['a']]));
        self::assertSame([], $unbound->headers->getCookies());
        self::assertNull($this->requests->take((string) Saml::query((string) $unbound->headers->get('Location'), 'RelayState'))?->target);

        $plain = $this->controller(secure: false)->login('acme', new Request());
        self::assertFalse($plain->headers->getCookies()[0]->isSecure());
        self::assertSame('lax', $plain->headers->getCookies()[0]->getSameSite());
    }

    public function testTheDefaultsOfTheController(): void
    {
        $providers = Saml::providers($this->clock);
        $factory = new MessageFactory($this->clock);
        $controller = new SamlController(
            $providers,
            new SamlLogin($providers, $factory, new ResponseProcessor($this->clock, new InMemoryReplayGuard($this->clock)), $this->requests, $this->clock),
            new SamlLogout($providers, $factory, new LogoutProcessor($this->clock), $this->requests, $this->clock, $this->events),
            $this->events,
            new SecurityPolicy(),
        );

        $before = time();
        $cookie = $controller->login('acme', new Request())->headers->getCookies()[0];
        $after = time();

        self::assertTrue($cookie->isSecure());
        self::assertSame('none', $cookie->getSameSite());
        self::assertGreaterThanOrEqual($before + 600, $cookie->getExpiresTime());
        self::assertLessThanOrEqual($after + 600, $cookie->getExpiresTime());
    }

    public function testASignInThatCannotStart(): void
    {
        $response = $this->controller(Saml::providers($this->clock, [new IdentityProviderSettings('acme', 'https://idp.example.org/metadata', null)]))->login('acme', new Request());

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('The identity provider cannot be reached or its message was refused.', $response->getContent());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        $event = $this->events->events[0] ?? null;
        self::assertInstanceOf(SamlResponseRefused::class, $event);
        self::assertSame(MetadataUnavailable::class, $event->exception);
    }

    public function testTheAssertionConsumerServiceWithoutTheAuthenticator(): void
    {
        $response = $this->controller()->assertionConsumerService('acme');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('The firewall of this address does not run the SAML authenticator of phil/saml-sp-bundle.', $response->getContent());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testTheIdentityProviderSignsThisBrowserOut(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('kept', 'no');

        $this->tokens->setToken($this->token('acme', 'ada.lovelace@example.org'));
        $request = Request::create('/saml/acme/slo', 'POST', ['SAMLRequest' => base64_encode(Saml::logoutRequest($this->clock->now())), 'RelayState' => 'r']);
        $request->setSession($session);

        $response = $this->controller()->singleLogout('acme', $request);

        self::assertStringStartsWith(Saml::IDP_SLO.'?SAMLResponse=', (string) $response->headers->get('Location'));
        self::assertSame('r', Saml::query((string) $response->headers->get('Location'), 'RelayState'));
        self::assertNull($this->tokens->getToken());
        self::assertNull($session->get('kept'));
        self::assertSame([SamlLogoutReceived::class], $this->events->classes());
    }

    public function testTheIdentityProviderSignsOutSomeoneElse(): void
    {
        foreach ([$this->token('acme', 'grace@example.org'), $this->token('other', 'ada.lovelace@example.org'), new PostAuthenticationToken(new InMemoryUser('a', null), 'main', [])] as $token) {
            $this->tokens->setToken($token);
            $this->controller()->singleLogout('acme', Request::create('/saml/acme/slo', 'POST', ['SAMLRequest' => base64_encode(Saml::logoutRequest($this->clock->now()))]));

            self::assertSame($token, $this->tokens->getToken());
        }
    }

    public function testALogoutRequestWithoutASessionOrATokenStorage(): void
    {
        $this->tokens->setToken($this->token('acme', 'ada.lovelace@example.org'));
        $this->controller()->singleLogout('acme', Request::create('/saml/acme/slo', 'POST', ['SAMLRequest' => base64_encode(Saml::logoutRequest($this->clock->now()))]));
        self::assertNull($this->tokens->getToken());

        $response = $this->controller(tokens: false)->singleLogout('acme', Request::create('/saml/acme/slo', 'POST', ['SAMLRequest' => base64_encode(Saml::logoutRequest($this->clock->now()))]));
        self::assertSame(302, $response->getStatusCode());
    }

    public function testTheResponseToTheLogoutOfTheServiceProvider(): void
    {
        $this->requests->save(new PendingRequest('_mine', 'acme', $this->clock->now(), '/signed-out'));
        $url = HttpRedirect::url(Url::fromString(Saml::BASE.'/slo'), 'SAMLResponse', Saml::logoutResponse($this->clock->now(), '_mine'), '_mine', Saml::pair('entra')->privateKey);
        $request = Request::create('/saml/acme/slo?'.parse_url($url, \PHP_URL_QUERY));

        $response = $this->controller()->singleLogout('acme', $request);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/signed-out', $response->headers->get('Location'));
    }

    public function testAPostedLogoutResponse(): void
    {
        $this->requests->save(new PendingRequest('_mine', 'acme', $this->clock->now()));
        $document = Saml::load(Saml::logoutResponse($this->clock->now(), '_mine'));
        new Signer(Saml::pair('entra'))->sign($document->documentElement ?? self::fail());

        $response = $this->controller()->singleLogout('acme', Request::create('/saml/acme/slo', 'POST', ['SAMLResponse' => base64_encode((string) $document->saveXML()), 'RelayState' => '_mine']));
        self::assertSame('/', $response->headers->get('Location'));
    }

    public function testARefusedLogoutMessage(): void
    {
        foreach ([Request::create('/saml/acme/slo', 'POST', ['SAMLRequest' => base64_encode(Saml::logoutRequest($this->clock->now(), sign: false))]), Request::create('/saml/acme/slo', 'POST', ['SAMLResponse' => ['x']]), Request::create('/saml/acme/slo?nothing=1')] as $request) {
            $response = $this->controller()->singleLogout('acme', $request);

            self::assertSame(400, $response->getStatusCode());
        }

        self::assertCount(3, $this->events->events);
        self::assertContainsOnlyInstancesOf(SamlResponseRefused::class, $this->events->events);
    }

    private function token(string $idp, string $nameId): PostAuthenticationToken
    {
        $token = new PostAuthenticationToken(new InMemoryUser('a', null), 'main', []);
        $token->setAttribute(SamlAttributes::IDENTITY_PROVIDER, $idp);
        $token->setAttribute(SamlAttributes::NAME_ID, $nameId);

        return $token;
    }

    private function controller(?IdentityProviders $providers = null, bool $bind = true, bool $secure = true, bool $tokens = true): SamlController
    {
        $providers ??= Saml::providers($this->clock);
        $factory = new MessageFactory($this->clock);

        return new SamlController(
            $providers,
            new SamlLogin($providers, $factory, new ResponseProcessor($this->clock, new InMemoryReplayGuard($this->clock)), $this->requests, $this->clock),
            new SamlLogout($providers, $factory, new LogoutProcessor($this->clock), $this->requests, $this->clock, $this->events),
            $this->events,
            new SecurityPolicy(),
            $tokens ? $this->tokens : null,
            $bind,
            $secure,
            300,
        );
    }
}
