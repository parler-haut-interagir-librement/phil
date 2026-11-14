<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Routing;

use Override;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Mounts the pages under the configured prefix, in front of the application's routes, so that the rights of a member
 * exist on every site without a line of routing and a catch-all route cannot hide them.
 *
 * An application that imports `@PhilPrivacyBundle/config/routes.php` itself keeps its own mount: the pages are added
 * only when none of the application's routes has their name.
 */
final readonly class MountingRouteLoader implements LoaderInterface
{
    public function __construct(
        private LoaderInterface $inner,
        private string $prefix,
        private bool $preferences,
    ) {
    }

    #[Override]
    public function load(mixed $resource, ?string $type = null): mixed
    {
        $loaded = $this->inner->load($resource, $type);

        if (!$loaded instanceof RouteCollection || $loaded->get(PrivacyRoutes::CONSENT) instanceof Route) {
            return $loaded;
        }

        $routes = PrivacyRoutes::collection($this->preferences);
        $routes->addPrefix($this->prefix);
        $routes->addCollection($loaded);

        return $routes;
    }

    #[Override]
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $this->inner->supports($resource, $type);
    }

    #[Override]
    public function getResolver(): LoaderResolverInterface
    {
        return $this->inner->getResolver();
    }

    #[Override]
    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->inner->setResolver($resolver);
    }
}
