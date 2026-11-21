<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\NotificationsBundle\Infrastructure\Http\DigestLinks;
use Phil\NotificationsBundle\Infrastructure\Http\DigestUnsubscribeController;
use Phil\NotificationsBundle\Infrastructure\Http\Routes;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Logs;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingUnsubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader as TwigLoader;

#[CoversClass(DigestLinks::class)]
#[CoversClass(DigestUnsubscribeController::class)]
final class DigestUnsubscribeTest extends TestCase
{
    private MockClock $clock;

    private UriSigner $signer;

    private RecordingUnsubscriber $unsubscriber;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->signer = new UriSigner('a secret of the tests', clock: $this->clock);
        $this->unsubscriber = new RecordingUnsubscriber();
    }

    public function testTheLinksAreAbsoluteAndTheUnsubscriptionSignedForAYear(): void
    {
        $links = new DigestLinks($this->router(), $this->signer, 'app_settings');

        self::assertSame('https://example.test/settings', $links->preferences());
        $url = $links->unsubscribe('member:42');
        self::assertIsString($url);
        self::assertSame('https://example.test/digest/unsubscribe/member:42?_expiration=1825668000&_hash='.substr($url, -43), $url);
        self::assertTrue($this->signer->check($url));

        $plain = new DigestLinks($this->router());
        self::assertNull($plain->preferences());
        self::assertNull($plain->unsubscribe('42'));
    }

    public function testWithoutItsRouteTheDigestLeavesWithoutTheLinkAndAnErrorIsLogged(): void
    {
        $logs = new Logs();

        self::assertNull(new DigestLinks(new UrlGenerator(new RouteCollection(), new RequestContext()), $this->signer, null, $logs)->unsubscribe('42'));
        self::assertNull(new DigestLinks(new UrlGenerator(new RouteCollection(), new RequestContext()), $this->signer)->unsubscribe('42'));
        self::assertSame(['error: A digest left without its link to unsubscribe: the application does not import the route phil_notifications_digest_unsubscribe, from digest_routes.php.'], $logs->records);
    }

    public function testAGetChangesNothingAndShowsTheButton(): void
    {
        $url = $this->signedUrl();

        foreach (['GET', 'HEAD'] as $method) {
            $response = $this->controller()($this->request($url, $method), '42');

            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
            self::assertSame('confirm|'.htmlspecialchars($url, \ENT_QUOTES), $response->getContent());
        }

        self::assertSame([], $this->unsubscriber->unsubscribed);
    }

    public function testAPostStopsTheDigestWithoutTokenNorConfirmation(): void
    {
        $response = $this->controller()($this->request($this->signedUrl(), 'POST', ['List-Unsubscribe' => 'One-Click']), '42');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('done|', $response->getContent());
        self::assertSame(['42'], $this->unsubscriber->unsubscribed);
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function testALinkNotSignedAlteredOrExpiredIsRefused(): void
    {
        $url = $this->signedUrl();
        $controller = $this->controller();

        foreach (['https://example.test/digest/unsubscribe/42', str_replace('/42?', '/43?', $url)] as $refused) {
            $response = $controller($this->request($refused, 'POST'), '43');
            self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
            self::assertSame('refused|', $response->getContent());
        }

        $this->clock->modify('+1 year +1 second');
        self::assertSame(Response::HTTP_FORBIDDEN, $controller($this->request($url, 'POST'), '42')->getStatusCode());
        self::assertSame([], $this->unsubscriber->unsubscribed);
    }

    public function testWithoutTwigALineOfText(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['digest.unsubscribe.done' => 'Done.', 'digest.unsubscribe.refused' => 'Refused.'], 'en', 'phil_notifications');

        $controller = new DigestUnsubscribeController($this->signer, $this->unsubscriber, $translator);

        $response = $controller($this->request($this->signedUrl(), 'POST'), '42');
        self::assertSame('Done.', $response->getContent());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('Refused.', $controller($this->request('https://example.test/x', 'POST'), '42')->getContent());
    }

    private function signedUrl(): string
    {
        return (string) new DigestLinks($this->router(), $this->signer)->unsubscribe('42');
    }

    /**
     * @param array<string, string> $body
     */
    private function request(string $url, string $method, array $body = []): Request
    {
        return Request::create($url, $method, $body);
    }

    private function controller(): DigestUnsubscribeController
    {
        $twig = new Environment(new TwigLoader(['page.html.twig' => '{{ state }}|{{ action }}']));

        return new DigestUnsubscribeController($this->signer, $this->unsubscriber, new Translator('en'), $twig, 'page.html.twig');
    }

    private function router(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('app_settings', new Route('/settings'));
        $routes->add(Routes::DIGEST_UNSUBSCRIBE, new Route('/digest/unsubscribe/{recipient}'));

        return new UrlGenerator($routes, RequestContext::fromUri('https://example.test'));
    }
}
