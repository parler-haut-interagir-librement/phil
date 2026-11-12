<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Infrastructure\Http\RateLimitCacheWarmer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects the limiters and the key strategies, and fails the container build on what a limiter would only discover
 * on a request: a cache pool, a storage or a lock factory that is not defined, two limiters of one name, a key
 * strategy nothing declares, a form limited by a limiter nothing declares. The rules of the routes and controllers are
 * checked by `RateLimitCacheWarmer`, once the routes can be read.
 *
 * A limiter is any service tagged `phil_rate_limit.limiter` with a `name`, and optionally the `key` and `amount` its
 * rules take by default and `observe`, true for a limiter whose refusals are reported but not enforced. A key strategy is a service tagged `phil_rate_limit.key_resolver`, under its `strategy` or
 * its id; a service id a limiter or a form names as its key joins them when it implements `KeyResolver`.
 */
final class LimitersPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(Limiters::class)) {
            return;
        }

        $this->requireServices($container);
        [$limiters, $defaults] = $this->limiters($container);

        $container->getDefinition(Limiters::class)
            ->setArguments([ServiceLocatorTagPass::register($container, $limiters), $defaults]);

        $resolvers = $this->resolvers($container, [...array_column($defaults, 'key'), ...$this->formKeys($container, $defaults)]);

        $container->getDefinition(KeyResolvers::class)
            ->setArguments([ServiceLocatorTagPass::register($container, $resolvers), array_keys($resolvers)]);

        $container->getDefinition(RateLimitCacheWarmer::class)->replaceArgument(3, $this->controllerClasses($container));
    }

    /**
     * The class of each controller service whose id is not a class: what a route naming that id runs.
     *
     * @return array<string, string>
     */
    private function controllerClasses(ContainerBuilder $container): array
    {
        $classes = [];

        foreach (array_keys($container->findTaggedServiceIds('controller.service_arguments')) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass());

            if (\is_string($class) && $class !== $id) {
                $classes[$id] = $class;
            }
        }

        return $classes;
    }

    /**
     * The tagged limiters, by name, and the key strategy and amount of each.
     *
     * @return array{array<string, Reference>, array<string, array{key: string, amount: int, observe: bool}>}
     */
    private function limiters(ContainerBuilder $container): array
    {
        $limiters = [];
        $defaults = [];

        /** @var array<string, list<array<string, mixed>>> $tagged */
        $tagged = $container->findTaggedServiceIds(RateLimitServices::LIMITER_TAG);

        foreach ($tagged as $id => $tags) {
            foreach ($tags as $tag) {
                $name = $tag['name'] ?? null;

                if (!\is_string($name) || 1 !== preg_match(RateLimitServices::NAME_PATTERN, $name)) {
                    throw new LogicException(\sprintf('The service "%s" is tagged "%s" without a name of 1 to 64 letters, digits, "_", "." and "-".', $id, RateLimitServices::LIMITER_TAG));
                }

                if (isset($limiters[$name])) {
                    throw new LogicException(\sprintf('Two limiters are named "%s": the services "%s" and "%s".', $name, (string) $limiters[$name], $id));
                }

                $key = $tag['key'] ?? 'ip';
                $amount = $tag['amount'] ?? 1;
                $observe = $tag['observe'] ?? false;

                if (!\is_string($key) || !\is_int($amount) || $amount < 1 || !\is_bool($observe)) {
                    throw new LogicException(\sprintf('The limiter "%s" takes a key strategy by name, an amount from one and observe as a boolean.', $name));
                }

                $this->requireImplementation($container, $id, Limiter::class);
                $limiters[$name] = new Reference($id);
                $defaults[$name] = ['key' => $key, 'amount' => $amount, 'observe' => $observe];
            }
        }

        return [$limiters, $defaults];
    }

    /**
     * The key strategies the form rules take, each form limited by a declared limiter.
     *
     * @param array<string, array{key: string, amount: int, observe: bool}> $defaults
     *
     * @return list<string>
     */
    private function formKeys(ContainerBuilder $container, array $defaults): array
    {
        /** @var array<string, list<array{limiter: string, amount: int|null, key: string|null}>> $forms */
        $forms = $container->getParameter(RateLimitServices::FORMS_PARAMETER);
        $container->getParameterBag()->remove(RateLimitServices::FORMS_PARAMETER);
        $keys = [];

        foreach ($forms as $form => $rules) {
            foreach ($rules as $rule) {
                $default = $defaults[$rule['limiter']] ?? throw new LogicException(\sprintf('The form "%s" is limited by "%s", which no limiter is named.', $form, $rule['limiter']));
                $keys[] = $rule['key'] ?? $default['key'];
            }
        }

        return $keys;
    }

    /**
     * The tagged key strategies, and the services the limiters and forms name as their key.
     *
     * @param list<string> $named
     *
     * @return array<string, Reference>
     */
    private function resolvers(ContainerBuilder $container, array $named): array
    {
        $resolvers = [];

        /** @var array<string, list<array{strategy?: string}>> $strategies */
        $strategies = $container->findTaggedServiceIds(RateLimitServices::KEY_TAG);

        foreach ($strategies as $id => $tags) {
            $resolvers[$tags[0]['strategy'] ?? $id] = new Reference($id);
        }

        foreach ($named as $key) {
            if (isset($resolvers[$key])) {
                continue;
            }

            if (!$container->has($key)) {
                throw new LogicException(\sprintf('The key strategy "%s" is neither ip, user, user_or_ip, ip_route nor a service.', $key));
            }

            $this->requireImplementation($container, $key, KeyResolver::class);
            $resolvers[$key] = new Reference($key);
        }

        return $resolvers;
    }

    /**
     * The cache pools, storages and lock factories of the limiters, each defined.
     */
    private function requireServices(ContainerBuilder $container): void
    {
        /** @var array<string, string> $required */
        $required = $container->getParameter(RateLimitServices::REQUIRED_PARAMETER);
        $container->getParameterBag()->remove(RateLimitServices::REQUIRED_PARAMETER);

        foreach ($required as $id => $by) {
            if (!$container->has($id)) {
                throw new LogicException(\sprintf('%s needs the service "%s", which is not defined: declare it, or name another in the configuration.', $by, $id));
            }
        }
    }

    /**
     * @param class-string $interface
     */
    private function requireImplementation(ContainerBuilder $container, string $id, string $interface): void
    {
        $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass());

        if (\is_string($class) && class_exists($class) && !is_subclass_of($class, $interface)) {
            throw new LogicException(\sprintf('The service "%s" is a %s, which does not implement %s.', $id, $class, $interface));
        }
    }
}
