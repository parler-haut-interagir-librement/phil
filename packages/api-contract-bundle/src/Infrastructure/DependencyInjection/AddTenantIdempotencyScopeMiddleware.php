<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Phil\ApiContractBundle\Infrastructure\Messenger\TenantIdempotencyScopeMiddleware;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Attribute\AsMessageMiddleware;

/**
 * Adds `TenantIdempotencyScopeMiddleware` to the Messenger buses `idempotency.messenger.buses` names, when
 * `idempotency.messenger.tenant_scope` registered it. When the option names no bus, the bundle autoconfigures the
 * middleware instead, and its `#[AsMessageMiddleware]` puts it on every bus; this pass then does nothing.
 *
 * The middleware is tagged `messenger.middleware` for each bus named, with the `before` and `after` of its
 * `#[AsMessageMiddleware]` — before the `IdempotencyMiddleware` of `phil/idempotency`, after the `TenantMiddleware` of
 * `phil/tenancy-bundle` — and Messenger places it among the bus's middleware. A bus whose configuration already lists
 * it, or for which it is already tagged, keeps it where it is: it is never added twice.
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
        $named = \is_array($named) ? array_filter($named, \is_string(...)) : [];

        $middleware = $this->middleware($container);

        foreach ($named as $bus) {
            if (!isset($middleware[$bus])) {
                throw new LogicException(\sprintf('The bus "%s" that phil_api_contract.idempotency.messenger.buses names is not a Messenger bus of the application; its buses are "%s".', $bus, implode('", "', array_keys($middleware))));
            }
        }

        $definition = $container->getDefinition(TenantIdempotencyScopeMiddleware::class);
        $tagged = array_column($definition->getTag(self::MIDDLEWARE_TAG), 'bus');
        $order = $this->order();

        foreach ($named as $bus) {
            if (\in_array(TenantIdempotencyScopeMiddleware::class, $middleware[$bus], true) || \in_array($bus, $tagged, true)) {
                continue;
            }

            $definition->addTag(self::MIDDLEWARE_TAG, ['bus' => $bus] + $order);
        }
    }

    /**
     * The `before` and `after` of the middleware's `#[AsMessageMiddleware]`, as lists.
     *
     * @return array{before: list<string>, after: list<string>}
     */
    private function order(): array
    {
        $attribute = new ReflectionClass(TenantIdempotencyScopeMiddleware::class)->getAttributes(AsMessageMiddleware::class)[0]->newInstance();

        return ['before' => (array) $attribute->before, 'after' => (array) $attribute->after];
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
