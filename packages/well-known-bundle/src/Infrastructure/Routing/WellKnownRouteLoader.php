<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Routing;

use Override;
use Phil\WellKnownBundle\Infrastructure\Http\WellKnownController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes of the documents served, imported by the application with the `phil_well_known` type: one exact route per
 * document, `GET` and `HEAD`, stateless, so that an address the bundle does not serve stays free for the application and
 * a document that is turned off is a `404`.
 *
 * The routes are cached with the container's list of them, and loaded again when it changes.
 */
final class WellKnownRouteLoader extends Loader
{
    public const string TYPE = 'phil_well_known';

    public const string PARAMETER = 'phil_well_known.routes';

    /**
     * @param array<string, string> $routes the path of each document, keyed by its route name
     */
    public function __construct(
        private readonly array $routes = [],
    ) {
        parent::__construct();
    }

    #[Override]
    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $collection = new RouteCollection();

        foreach ($this->routes as $name => $path) {
            $collection->add($name, new Route($path, ['_controller' => WellKnownController::class, WellKnownController::DOCUMENT => $name, '_stateless' => true], methods: ['GET', 'HEAD']));
        }

        $collection->addResource(new ContainerParametersResource([self::PARAMETER => $this->routes]));

        return $collection;
    }

    #[Override]
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }
}
