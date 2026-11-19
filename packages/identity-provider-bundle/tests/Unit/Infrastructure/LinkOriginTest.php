<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use ArrayObject;
use LogicException;
use Override;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\IdentityProviderServices;
use Phil\IdentityProviderBundle\Infrastructure\Routing\RouterLinkOrigin;
use Phil\IdentityProviderBundle\PhilIdentityProviderBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The origin of the links sent by email: `public_origin` read by `phil/url`, the router's context put on it for one
 * link and given back, and the boot refused in the `prod` environment when neither it nor `framework.trusted_hosts` is
 * set.
 */
#[CoversClass(RouterLinkOrigin::class)]
#[CoversClass(PhilIdentityProviderBundle::class)]
final class LinkOriginTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
    }

    public function testALinkTakesTheOriginWhateverTheRequestNamed(): void
    {
        $context = new RequestContext('/app', 'POST', 'attacker.example', 'http', 8080, 8443, '/forgot-password', 'a=b');
        $context->setParameter('_locale', 'fr');

        $urls = $this->urls($context);
        $origin = new RouterLinkOrigin($urls, 'https://Accounts.Example.com');

        self::assertSame('https://accounts.example.com/app/fr/reset?account=a', $origin->link(static fn (): string => $urls->generate('reset', ['account' => 'a'], UrlGeneratorInterface::ABSOLUTE_URL)));
        self::assertSame($context, $urls->getContext(), 'The context of the request is given back.');
        self::assertSame('attacker.example', $context->getHost());
        self::assertSame('POST', $context->getMethod());
    }

    public function testThePortOfTheOriginIsKept(): void
    {
        $urls = $this->urls(new RequestContext());

        self::assertSame('https://example.com:8443/fr/reset?account=a', new RouterLinkOrigin($urls, 'https://example.com:8443')->link(static fn (): string => $urls->generate('reset', ['account' => 'a', '_locale' => 'fr'], UrlGeneratorInterface::ABSOLUTE_URL)));
        self::assertSame('http://example.com:8080/fr/reset?account=a', new RouterLinkOrigin($urls, 'http://example.com:8080')->link(static fn (): string => $urls->generate('reset', ['account' => 'a', '_locale' => 'fr'], UrlGeneratorInterface::ABSOLUTE_URL)));
        self::assertSame('http://example.com/fr/reset?account=a', new RouterLinkOrigin($urls, 'http://example.com')->link(static fn (): string => $urls->generate('reset', ['account' => 'a', '_locale' => 'fr'], UrlGeneratorInterface::ABSOLUTE_URL)));
    }

    public function testTheContextHoldsBothPortsWhateverTheScheme(): void
    {
        $urls = $this->urls(new RequestContext());
        $seen = new ArrayObject();
        $generate = static function () use ($urls, $seen): string {
            $seen[] = $urls->getContext()->getHttpPort().'/'.$urls->getContext()->getHttpsPort();

            return 'https://example.com/';
        };

        new RouterLinkOrigin($urls, 'https://example.com:8443')->link($generate);
        new RouterLinkOrigin($urls, 'https://example.com')->link($generate);
        new RouterLinkOrigin($urls, 'http://example.com:8080')->link($generate);
        new RouterLinkOrigin($urls, 'http://example.com')->link($generate);

        self::assertSame(['80/8443', '80/443', '8080/443', '80/443'], $seen->getArrayCopy());
    }

    public function testLocalhostIsRefusedUnlessAllowed(): void
    {
        $urls = $this->urls(new RequestContext());
        $generate = static fn (): string => $urls->generate('reset', ['_locale' => 'fr'], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertSame('http://localhost:8000/fr/reset', new RouterLinkOrigin($urls, 'http://localhost:8000', true)->link($generate));

        $this->expectException(InvalidConfigurationException::class);
        new RouterLinkOrigin($urls, 'http://localhost:8000')->link($generate);
    }

    public function testTheContextIsGivenBackWhenTheLinkFails(): void
    {
        $context = new RequestContext(host: 'www.example.com');
        $urls = $this->urls($context);

        try {
            new RouterLinkOrigin($urls, 'https://example.com')->link(static fn (): string => throw new LogicException('no route'));
            self::fail('The failure is hidden.');
        } catch (LogicException) {
            self::assertSame($context, $urls->getContext());
        }
    }

    public function testWithoutOriginTheLinkIsTheRoutersOwn(): void
    {
        $urls = $this->urls(new RequestContext(host: 'www.example.com'));

        self::assertSame('http://www.example.com/fr/reset', new RouterLinkOrigin($urls)->link(static fn (): string => $urls->generate('reset', ['_locale' => 'fr'], UrlGeneratorInterface::ABSOLUTE_URL)));
    }

    public function testLocalhostOnlyInDebugMode(): void
    {
        self::assertSame('https://localhost/', RouterLinkOrigin::origin('https://localhost', true)?->toString());
        self::assertNull(RouterLinkOrigin::origin(null, false));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"phil_identity_provider.public_origin" is the canonical address of the site, such as https://example.com: ');
        RouterLinkOrigin::origin('https://localhost', false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notOrigins(): iterable
    {
        yield 'path' => ['https://example.com/app'];
        yield 'query' => ['https://example.com/?a=b'];
        yield 'fragment' => ['https://example.com/#a'];
    }

    #[DataProvider('notOrigins')]
    public function testAnOriginHasNoPathQueryOrFragment(string $origin): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"app.origin" is an origin — a scheme, a host and a port — without path, query or fragment, such as https://example.com.');
        RouterLinkOrigin::origin($origin, false, 'app.origin');
    }

    public function testAnIpAddressIsNoOrigin(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        RouterLinkOrigin::origin('https://192.0.2.1', true);
    }

    public function testTheBootIsRefusedInProductionWithNeitherOriginNorTrustedHosts(): void
    {
        RouterLinkOrigin::assertBound(null, 'test', []);
        RouterLinkOrigin::assertBound(null, 'prod', ['{^example\.com$}i']);
        RouterLinkOrigin::assertBound('https://example.com', 'prod', []);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The links sent by email would take the host of the request: set "phil_identity_provider.public_origin" to the canonical address of the site, or list the hosts it answers in framework.trusted_hosts.');
        RouterLinkOrigin::assertBound(null, 'prod', []);
    }

    public function testTheBundleChecksWhenItBoots(): void
    {
        $this->boot('prod', 'https://example.com');
        $this->boot('prod', null, enabled: false);
        Request::setTrustedHosts(['^example\.com$']);
        $this->boot('prod', null);
        Request::setTrustedHosts([]);
        $this->boot('dev', '');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('framework.trusted_hosts');
        $this->boot('prod', '');
    }

    public function testTheBundleReadsTheOriginWhenItBoots(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('without path, query or fragment');
        $this->boot('dev', 'https://example.com/app');
    }

    public function testTheBundleAllowsLocalhostOnlyInDebugMode(): void
    {
        $this->boot('dev', 'http://localhost:8000', debug: true);

        $this->expectException(InvalidConfigurationException::class);
        $this->boot('dev', 'http://localhost:8000');
    }

    #[DoesNotPerformAssertions]
    public function testTheBundleWithoutContainerChecksNothing(): void
    {
        new PhilIdentityProviderBundle()->boot();
    }

    private function boot(string $environment, ?string $origin, bool $enabled = true, bool $debug = false): void
    {
        $container = new Container();
        $container->setParameter('phil_identity_provider.enabled', $enabled);
        $container->setParameter(IdentityProviderServices::PUBLIC_ORIGIN, $origin);
        $container->setParameter('kernel.environment', $environment);
        $container->setParameter('kernel.debug', $debug);

        $bundle = new PhilIdentityProviderBundle();
        $bundle->setContainer($container);
        $bundle->boot();
    }

    private function urls(RequestContext $context): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('reset', new Route('/{_locale}/reset'));

        return new UrlGenerator($routes, $context);
    }
}
