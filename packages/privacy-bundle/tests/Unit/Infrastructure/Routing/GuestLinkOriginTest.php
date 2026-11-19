<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Routing;

use Closure;
use DateTimeImmutable;
use LogicException;
use Override;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use Phil\PrivacyBundle\Infrastructure\Mailer\MailerGuestLinkSender;
use Phil\PrivacyBundle\Infrastructure\Routing\GuestLinkOrigin;
use Phil\PrivacyBundle\Infrastructure\Routing\PrivacyRoutes;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The link of a request without an account, asked for by a request that names another host — `attacker.example` —:
 * on `public_origin` whatever the `Host` header said, and the boot refused in the `prod` environment when neither it
 * nor `framework.trusted_hosts` is set.
 */
#[CoversClass(GuestLinkOrigin::class)]
#[CoversClass(MailerGuestLinkSender::class)]
#[CoversClass(PhilPrivacyBundle::class)]
final class GuestLinkOriginTest extends TestCase
{
    /**
     * @var list<RawMessage>
     */
    private array $sent = [];

    #[Override]
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
    }

    public function testTheMailedLinkTakesTheOriginWhateverTheRequestNamed(): void
    {
        $context = new RequestContext()->fromRequest(Request::create('http://attacker.example:8080/privacy-choices/request', 'POST'));
        $urls = $this->urls($context);

        new MailerGuestLinkSender($this->transport(), $this->twig(), $urls, 'privacy@example.org', new GuestLinkOrigin($urls, 'https://Example.org'))
            ->send($this->request(), 'the-token');

        $email = $this->sent[0] ?? null;
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('https://example.org/privacy-choices/request/'.str_repeat('a', 32).'?token=the-token', $email->getTextBody());
        self::assertSame($context, $urls->getContext());
        self::assertSame('attacker.example', $context->getHost());
        self::assertSame(8080, $context->getHttpPort());
    }

    public function testWithoutOriginTheLinkIsTheRoutersOwn(): void
    {
        $urls = $this->urls(new RequestContext(host: 'www.example.org'));

        new MailerGuestLinkSender($this->transport(), $this->twig(), $urls, 'privacy@example.org', new GuestLinkOrigin($urls))->send($this->request(), 't');

        $email = $this->sent[0] ?? null;
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('http://www.example.org/privacy-choices/request/'.str_repeat('a', 32).'?token=t', $email->getTextBody());
    }

    public function testThePortAndTheBasePathAreKept(): void
    {
        $context = new RequestContext('/app', host: 'attacker.example');
        $context->setParameter('_locale', 'fr');

        $urls = $this->urls($context, '/{_locale}/confirm/{id}');
        $generate = static fn (): string => $urls->generate(PrivacyRoutes::GUEST_CONFIRM, ['id' => 'a'], UrlGenerator::ABSOLUTE_URL);

        self::assertSame('https://example.org:8443/app/fr/confirm/a', new GuestLinkOrigin($urls, 'https://example.org:8443')->link($generate));
        self::assertSame('http://example.org:8080/app/fr/confirm/a', new GuestLinkOrigin($urls, 'http://example.org:8080')->link($generate));
        self::assertSame('http://example.org/app/fr/confirm/a', new GuestLinkOrigin($urls, 'http://example.org')->link($generate));
        self::assertSame('https://example.org/app/fr/confirm/a', new GuestLinkOrigin($urls, 'https://example.org')->link($generate));
    }

    public function testTheContextIsGivenBackWhenTheLinkFails(): void
    {
        $context = new RequestContext(host: 'www.example.org');
        $urls = $this->urls($context);

        try {
            new GuestLinkOrigin($urls, 'https://example.org')->link(static fn (): string => throw new LogicException('no route'));
            self::fail('The failure is hidden.');
        } catch (LogicException) {
            self::assertSame($context, $urls->getContext());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notOrigins(): iterable
    {
        yield 'path' => ['https://example.org/app', 'is an origin — a scheme, a host and a port — without path, query or fragment, such as https://example.com.'];
        yield 'query' => ['https://example.org/?a=b', 'is an origin'];
        yield 'fragment' => ['https://example.org/#a', 'is an origin'];
        yield 'ip address' => ['https://192.0.2.1', 'is the canonical address of the site, such as https://example.com: '];
        yield 'localhost' => ['https://localhost', 'is the canonical address of the site'];
    }

    #[DataProvider('notOrigins')]
    public function testAnOriginIsASchemeAHostAndAPort(string $origin, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"phil_privacy.public_origin" '.$message);
        GuestLinkOrigin::origin($origin, false);
    }

    public function testLocalhostInDebugMode(): void
    {
        self::assertSame('http://localhost:8000/', GuestLinkOrigin::origin('http://localhost:8000', true)?->toString());
        self::assertNull(GuestLinkOrigin::origin(null, false));
    }

    public function testTheBootIsRefusedInProductionWithNeitherOriginNorTrustedHosts(): void
    {
        GuestLinkOrigin::assertBound(null, 'dev', []);
        GuestLinkOrigin::assertBound(null, 'prod', ['{^example\.org$}i']);
        GuestLinkOrigin::assertBound('https://example.org', 'prod', []);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The links of the requests without an account would take the host of the request: set "phil_privacy.public_origin" to the canonical address of the site, or list the hosts it answers in framework.trusted_hosts.');
        GuestLinkOrigin::assertBound(null, 'prod', []);
    }

    public function testTheBundleChecksWhenItBoots(): void
    {
        $this->boot('prod', 'https://example.org');
        $this->boot('prod', null, mailedLinks: false);
        $this->boot('prod', null, mailedLinks: null);
        Request::setTrustedHosts(['^example\.org$']);
        $this->boot('prod', null);
        Request::setTrustedHosts([]);
        $this->boot('dev', '');
        new PhilPrivacyBundle()->boot();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('framework.trusted_hosts');
        $this->boot('prod', '');
    }

    public function testTheBundleReadsTheOriginWhenItBoots(): void
    {
        $this->boot('dev', 'http://localhost:8000', debug: true);

        $this->expectException(InvalidConfigurationException::class);
        $this->boot('dev', 'http://localhost:8000');
    }

    private function boot(string $environment, ?string $origin, ?bool $mailedLinks = true, bool $debug = false): void
    {
        $container = new Container();

        if (null !== $mailedLinks) {
            $container->setParameter(PhilPrivacyBundle::MAILED_LINKS, $mailedLinks);
        }

        $container->setParameter(GuestLinkOrigin::OPTION, $origin);
        $container->setParameter('kernel.environment', $environment);
        $container->setParameter('kernel.debug', $debug);

        $bundle = new PhilPrivacyBundle();
        $bundle->setContainer($container);
        $bundle->boot();
    }

    private function urls(RequestContext $context, string $path = '/privacy-choices/request/{id}'): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(PrivacyRoutes::GUEST_CONFIRM, new Route($path));

        return new UrlGenerator($routes, $context);
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader([MailerGuestLinkSender::TEMPLATE => '{% if false %}{% block subject %}Subject{% endblock %}{% endif %}{{ link }}']));
    }

    private function request(): GuestRequest
    {
        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');

        return new GuestRequest(str_repeat('a', 32), GuestRequestKind::Erasure, 'eloise@example.org', hash('sha256', 'x'), $now, $now->modify('+1 hour'));
    }

    private function transport(): TransportInterface
    {
        return new readonly class(function (RawMessage $message): void {
            $this->sent[] = $message;
        }) implements TransportInterface {
            /**
             * @param Closure(RawMessage): void $record
             */
            public function __construct(private Closure $record)
            {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                ($this->record)($message);

                return null;
            }

            public function __toString(): string
            {
                return 'memory://';
            }
        };
    }
}
