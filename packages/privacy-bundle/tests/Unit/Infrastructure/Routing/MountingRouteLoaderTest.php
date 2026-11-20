<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Routing;

use Override;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentController;
use Phil\PrivacyBundle\Infrastructure\Http\ErasureProgressController;
use Phil\PrivacyBundle\Infrastructure\Http\GuestRequestController;
use Phil\PrivacyBundle\Infrastructure\Http\PreferencesController;
use Phil\PrivacyBundle\Infrastructure\Routing\MountingRouteLoader;
use Phil\PrivacyBundle\Infrastructure\Routing\PrivacyRoutes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(MountingRouteLoader::class)]
#[CoversClass(PrivacyRoutes::class)]
final class MountingRouteLoaderTest extends TestCase
{
    public function testThePagesComeFirstUnderTheirPrefix(): void
    {
        $routes = $this->loader($this->application())->load('kernel');

        self::assertInstanceOf(RouteCollection::class, $routes);
        self::assertSame([PrivacyRoutes::CONSENT, PrivacyRoutes::CONSENT_DECIDE, PrivacyRoutes::COOKIES, PrivacyRoutes::GUEST_REQUEST, PrivacyRoutes::GUEST_CONFIRM, PrivacyRoutes::ERASURE_PROGRESS, PrivacyRoutes::PREFERENCES, PrivacyRoutes::EXPORT, PrivacyRoutes::EMAIL_TRACKING, PrivacyRoutes::ERASURE, PrivacyRoutes::ERASURE_CANCEL, 'app'], array_keys($routes->all()));
        self::assertSame('/prefix/consent', $routes->get(PrivacyRoutes::CONSENT)?->getPath());
        self::assertSame('/app', $routes->get('app')?->getPath());
    }

    public function testWithoutAFirewallOnlyTheConsentIsMounted(): void
    {
        $routes = $this->loader($this->application(), false)->load('kernel');

        self::assertInstanceOf(RouteCollection::class, $routes);
        self::assertSame([PrivacyRoutes::CONSENT, PrivacyRoutes::CONSENT_DECIDE, PrivacyRoutes::COOKIES, PrivacyRoutes::GUEST_REQUEST, PrivacyRoutes::GUEST_CONFIRM, PrivacyRoutes::ERASURE_PROGRESS, 'app'], array_keys($routes->all()));
    }

    public function testAnApplicationThatMountedThePagesKeepsItsOwnMount(): void
    {
        $application = $this->application();
        $application->add(PrivacyRoutes::CONSENT, new Route('/mine'));

        $routes = $this->loader($application)->load('kernel');

        self::assertSame($application, $routes);
    }

    public function testAnythingElseThanRoutesIsReturnedAsItIs(): void
    {
        self::assertSame('other', $this->loader('other')->load('kernel'));
    }

    public function testEachRouteAnswersItsMethods(): void
    {
        $routes = PrivacyRoutes::collection();

        self::assertSame(['GET'], $routes->get(PrivacyRoutes::CONSENT)?->getMethods());
        self::assertSame(['POST'], $routes->get(PrivacyRoutes::CONSENT_DECIDE)?->getMethods());
        self::assertSame(['GET'], $routes->get(PrivacyRoutes::PREFERENCES)?->getMethods());
        self::assertSame(['POST'], $routes->get(PrivacyRoutes::EXPORT)?->getMethods());
        self::assertSame(['GET', 'POST'], $routes->get(PrivacyRoutes::ERASURE)?->getMethods());
        self::assertSame(['POST'], $routes->get(PrivacyRoutes::ERASURE_CANCEL)?->getMethods());
        self::assertSame(['POST'], $routes->get(PrivacyRoutes::EMAIL_TRACKING)?->getMethods());
        self::assertSame('/email-tracking', $routes->get(PrivacyRoutes::EMAIL_TRACKING)->getPath());
        self::assertSame([PreferencesController::class, 'emailTracking'], $routes->get(PrivacyRoutes::EMAIL_TRACKING)->getDefault('_controller'));
        self::assertSame('/erasure/cancel', $routes->get(PrivacyRoutes::ERASURE_CANCEL)->getPath());
        self::assertSame([ConsentController::class, 'page'], $routes->get(PrivacyRoutes::CONSENT)->getDefault('_controller'));
        self::assertSame([ConsentController::class, 'decide'], $routes->get(PrivacyRoutes::CONSENT_DECIDE)->getDefault('_controller'));
        self::assertSame([PreferencesController::class, 'cancel'], $routes->get(PrivacyRoutes::ERASURE_CANCEL)->getDefault('_controller'));
        self::assertSame(['GET'], $routes->get(PrivacyRoutes::COOKIES)?->getMethods());
        self::assertSame([ConsentController::class, 'cookies'], $routes->get(PrivacyRoutes::COOKIES)->getDefault('_controller'));
        self::assertSame(['GET', 'POST'], $routes->get(PrivacyRoutes::GUEST_REQUEST)?->getMethods());
        self::assertSame([GuestRequestController::class, 'request'], $routes->get(PrivacyRoutes::GUEST_REQUEST)->getDefault('_controller'));
        self::assertSame(['GET', 'POST'], $routes->get(PrivacyRoutes::GUEST_CONFIRM)?->getMethods());
        self::assertSame('/request/{id}', $routes->get(PrivacyRoutes::GUEST_CONFIRM)->getPath());
        self::assertSame('[0-9a-f]{32}', $routes->get(PrivacyRoutes::GUEST_CONFIRM)->getRequirement('id'));
        self::assertSame([GuestRequestController::class, 'confirm'], $routes->get(PrivacyRoutes::GUEST_CONFIRM)->getDefault('_controller'));
        $progress = $routes->get(PrivacyRoutes::ERASURE_PROGRESS);
        self::assertSame(['GET'], $progress?->getMethods());
        self::assertSame('/erasure/requests/{id}.{_format}', $progress->getPath());
        self::assertSame('[0-9a-f]{32}', $progress->getRequirement('id'));
        self::assertSame('html|json', $progress->getRequirement('_format'));
        self::assertSame('html', $progress->getDefault('_format'));
        self::assertSame([ErasureProgressController::class, 'progress'], $progress->getDefault('_controller'));
    }

    public function testTheRoutesFileGivesTheWholeCollection(): void
    {
        $routes = require __DIR__.'/../../../../config/routes.php';

        self::assertInstanceOf(RouteCollection::class, $routes);
        self::assertCount(11, $routes);
    }

    public function testSupportsAndTheResolverAreTheInnerLoaders(): void
    {
        $resolver = new LoaderResolver();
        $inner = $this->inner(null);
        $loader = new MountingRouteLoader($inner, '/p', true);

        $loader->setResolver($resolver);

        self::assertSame($resolver, $loader->getResolver());
        self::assertSame($resolver, $inner->getResolver());
        self::assertTrue($loader->supports('kernel', 'service'));
        self::assertFalse($loader->supports('other'));
    }

    private function application(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('app', new Route('/app'));

        return $routes;
    }

    private function loader(mixed $loaded, bool $preferences = true): MountingRouteLoader
    {
        return new MountingRouteLoader($this->inner($loaded), '/prefix', $preferences);
    }

    private function inner(mixed $loaded): Loader
    {
        return new class($loaded) extends Loader {
            public function __construct(private readonly mixed $loaded)
            {
                parent::__construct();
            }

            #[Override]
            public function load(mixed $resource, ?string $type = null): mixed
            {
                return $this->loaded;
            }

            #[Override]
            public function supports(mixed $resource, ?string $type = null): bool
            {
                return 'kernel' === $resource && 'service' === $type;
            }
        };
    }
}
