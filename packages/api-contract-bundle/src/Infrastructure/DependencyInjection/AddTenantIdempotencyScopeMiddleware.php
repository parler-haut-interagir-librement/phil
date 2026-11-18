<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Phil\ApiContractBundle\Infrastructure\Messenger\TenantIdempotencyScopeMiddleware;
use Phil\Idempotency\Messenger\IdempotencyMiddleware;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds `TenantIdempotencyScopeMiddleware` to the Messenger buses, when `idempotency.messenger.tenant_scope` registered
 * it: on the buses `idempotency.messenger.buses` names, or, when it names none, on every bus whose middleware holds the
 * `IdempotencyMiddleware` of `phil/idempotency`.
 *
 * The middleware is tagged `messenger.middleware` for each bus, before `IdempotencyMiddleware` and after the
 * `TenantMiddleware` of `phil/tenancy-bundle`, and Messenger places it among the bus's middleware. A bus whose
 * configuration already lists it, or for which it is already tagged, keeps it where it is: it is never added twice.
 *
 * The pass runs before Messenger's, which reads the middleware of each bus from its `<bus>.middleware` parameter.
 *
 * @internal
 */
final readonly class AddTenantIdempotencyScopeMiddleware implements CompilerPassInterface
{
    /**
     * The buses the configuration names; a build parameter, gone from the compiled container.
     */
    public const string BUSES = '.phil_api_contract.idempotency.messenger.buses';

    public const string MIDDLEWARE_TAG = 'messenger.middleware';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(TenantIdempotencyScopeMiddleware::class)) {
            return;
        }

        $named = $container->hasParameter(self::BUSES) ? $container->getParameter(self::BUSES) : [];
        $named = \is_array($named) ? array_values(array_filter($named, \is_string(...))) : [];

        $middleware = $this->middleware($container);

        foreach ($named as $bus) {
            if (!isset($middleware[$bus])) {
                throw new LogicException(\sprintf('The bus "%s" that phil_api_contract.idempotency.messenger.buses names is not a Messenger bus of the application; its buses are "%s".', $bus, implode('", "', array_keys($middleware))));
            }
        }

        $definition = $container->getDefinition(TenantIdempotencyScopeMiddleware::class);
        $tagged = array_column($definition->getTag(self::MIDDLEWARE_TAG), 'bus');

        foreach ($middleware as $bus => $classes) {
            $wanted = [] === $named ? \in_array(IdempotencyMiddleware::class, $classes, true) : \in_array($bus, $named, true);

            if (!$wanted || \in_array(TenantIdempotencyScopeMiddleware::class, $classes, true) || \in_array($bus, $tagged, true)) {
                continue;
            }

            $definition->addTag(self::MIDDLEWARE_TAG, ['bus' => $bus, 'before' => [IdempotencyMiddleware::class], 'after' => [TenantMiddleware::class]]);
        }
    }

    /**
     * The buses whose middleware Messenger reads from their parameter, each with the classes of its middleware, and the
     * identifiers of those that are not services.
     *
     * @return array<string, list<string>>
     */
    private function middleware(ContainerBuilder $container): array
    {
        $buses = [];

        foreach (array_keys($container->findTaggedServiceIds('messenger.bus')) as $bus) {
            $items = $container->hasParameter($bus.'.middleware') ? $container->getParameter($bus.'.middleware') : null;

            if (!\is_array($items)) {
                continue;
            }

            $buses[$bus] = [];

            foreach ($items as $item) {
                $id = \is_array($item) && \is_string($item['id'] ?? null) ? $item['id'] : null;

                if (null !== $id) {
                    $buses[$bus][] = $this->classOf($container, $id);
                }
            }
        }

        return $buses;
    }

    /**
     * The class of a middleware as Messenger finds it — the service `messenger.middleware.<id>`, else `<id>`, through
     * its aliases — or the identifier itself when no such service exists.
     */
    private function classOf(ContainerBuilder $container, string $id): string
    {
        $service = $container->has('messenger.middleware.'.$id) ? 'messenger.middleware.'.$id : $id;

        if (!$container->has($service)) {
            return $id;
        }

        $class = $container->getParameterBag()->resolveValue($container->findDefinition($service)->getClass() ?? $service);

        return \is_string($class) ? $class : $id;
    }
}
