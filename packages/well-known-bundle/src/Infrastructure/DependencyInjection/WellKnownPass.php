<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\WellKnownPath;
use Phil\WellKnownBundle\Infrastructure\Http\WellKnownController;
use Phil\WellKnownBundle\Infrastructure\Routing\WellKnownRouteLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gathers the documents tagged `phil_well_known.document` — the bundle's and those of other bundles and the application
 * — once every bundle has loaded: their paths and route names checked, none twice, each class a `WellKnownDocument`.
 * The controller gets them by route name; the route loader and the `phil_well_known.routes` parameter, their paths.
 */
final class WellKnownPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(WellKnownController::class)) {
            return;
        }

        $routes = [];
        $documents = [];

        foreach ($container->findTaggedServiceIds(WellKnownServices::DOCUMENT_TAG) as $id => $tags) {
            $this->checkClass($container, $id);

            foreach ($tags as $tag) {
                $path = $this->path($id, $tag);

                if (\in_array($path->path, $routes, true) || isset($routes[$path->route])) {
                    throw new InvalidArgumentException(\sprintf('The well-known document "%s" is served at "%s", by the route "%s": another document already is.', $id, $path->path, $path->route));
                }

                $routes[$path->route] = $path->path;
                $documents[$path->route] = new Reference($id);
            }
        }

        asort($routes);
        $container->getDefinition(WellKnownController::class)->replaceArgument(0, ServiceLocatorTagPass::register($container, $documents));
        $container->getDefinition(WellKnownRouteLoader::class)->replaceArgument(0, $routes);
        $container->setParameter(WellKnownRouteLoader::PARAMETER, $routes);
    }

    private function path(string $id, mixed $tag): WellKnownPath
    {
        $path = \is_array($tag) ? $tag['path'] ?? null : null;
        $route = \is_array($tag) ? $tag['route'] ?? null : null;

        if (!\is_string($path) || (null !== $route && !\is_string($route))) {
            throw new InvalidArgumentException(\sprintf('The tag "%s" of the service "%s" needs a "path", and its "route" is a string.', WellKnownServices::DOCUMENT_TAG, $id));
        }

        try {
            return WellKnownPath::of($path, $route);
        } catch (InvalidValue $invalidValue) {
            throw new InvalidArgumentException(\sprintf('The well-known document "%s": %s', $id, $invalidValue->getMessage()), $invalidValue->getCode(), previous: $invalidValue);
        }
    }

    private function checkClass(ContainerBuilder $container, string $id): void
    {
        $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass());

        if (!\is_string($class) || !is_subclass_of($class, WellKnownDocument::class)) {
            throw new InvalidArgumentException(\sprintf('The well-known document "%s" is a "%s": it implements "%s".', $id, \is_string($class) ? $class : get_debug_type($class), WellKnownDocument::class));
        }
    }
}
