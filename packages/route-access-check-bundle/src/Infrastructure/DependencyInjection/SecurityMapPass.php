<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\AttributesRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IpsRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PortRequestMatcher;

/**
 * Reads the firewalls and the access_control entries from the services SecurityBundle builds out of `security.yaml`
 * — the firewall map, the firewall configurations and the access map — once every configuration file is merged and
 * normalised, so that the check sees exactly what the application runs. The request matchers of symfony/http-foundation
 * are read back into plain values; any other matcher is kept as its service id, and the library treats it as
 * conditional.
 *
 * It also maps the controller services whose id is not their class, so that the check finds their attributes. Both
 * become parameters of the container; nothing is read when the check is not registered.
 */
final class SecurityMapPass implements CompilerPassInterface
{
    public const string SECURITY_MAP = 'phil_route_access_check.security_map';

    public const string CONTROLLER_CLASSES = 'phil_route_access_check.controller_classes';

    private const string FIREWALL_CONTEXT = 'security.firewall.map.context.';

    private const string FIREWALL_CONFIG = 'security.firewall.map.config.';

    /** @var array<class-string, string> */
    private const array SINGLE_VALUES = [
        PathRequestMatcher::class => 'path',
        HostRequestMatcher::class => 'host',
        PortRequestMatcher::class => 'port',
    ];

    /** @var array<class-string, string> */
    private const array LIST_VALUES = [
        MethodRequestMatcher::class => 'methods',
        IpsRequestMatcher::class => 'ips',
    ];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(RouteAccessChecker::class)) {
            return;
        }

        $container->setParameter(self::SECURITY_MAP, [
            'firewalls' => $this->firewalls($container),
            'access_control' => $this->accessControl($container),
        ]);
        $container->setParameter(self::CONTROLLER_CLASSES, $this->controllerClasses($container));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function firewalls(ContainerBuilder $container): array
    {
        if (!$container->hasDefinition('security.firewall.map')) {
            return [];
        }

        $map = $container->getDefinition('security.firewall.map')->getArguments()[1] ?? null;

        if (!$map instanceof IteratorArgument) {
            return [];
        }

        $firewalls = [];

        foreach ($map->getValues() as $context => $matcher) {
            $name = str_starts_with((string) $context, self::FIREWALL_CONTEXT) ? substr((string) $context, \strlen(self::FIREWALL_CONTEXT)) : (string) $context;
            $firewall = ['name' => $name, 'security' => $this->securityOf($container, $name)];

            if ($matcher instanceof Reference) {
                $firewall['request'] = $this->request($container, (string) $matcher);
            }

            $firewalls[] = $firewall;
        }

        return $firewalls;
    }

    private function securityOf(ContainerBuilder $container, string $name): bool
    {
        if (!$container->hasDefinition(self::FIREWALL_CONFIG.$name)) {
            return true;
        }

        try {
            return false !== $container->getDefinition(self::FIREWALL_CONFIG.$name)->getArgument(3);
        } catch (OutOfBoundsException) {
            return true;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accessControl(ContainerBuilder $container): array
    {
        if (!$container->hasDefinition('security.access_map')) {
            return [];
        }

        $entries = [];

        /** @var list<array{string, array<array-key, mixed>}> $calls */
        $calls = $container->getDefinition('security.access_map')->getMethodCalls();

        foreach ($calls as [$method, $arguments]) {
            if ('add' !== $method || !($arguments[0] ?? null) instanceof Reference) {
                continue;
            }

            $entry = ['request' => $this->request($container, (string) $arguments[0]), 'roles' => []];

            foreach (\is_array($arguments[1] ?? null) ? $arguments[1] : [] as $role) {
                if ($role instanceof Reference) {
                    $entry['allow_if'] = $this->expression($container, (string) $role);
                } elseif (\is_string($role)) {
                    $entry['roles'][] = $this->resolve($container, $role);
                }
            }

            $channel = $arguments[2] ?? null;
            $entry['requires_channel'] = \is_string($channel) ? $channel : null;
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(ContainerBuilder $container, string $id): array
    {
        $definition = $container->hasDefinition($id) || $container->hasAlias($id) ? $container->findDefinition($id) : null;

        if (!$definition instanceof Definition || ChainRequestMatcher::class !== $definition->getClass()) {
            return ['matcher' => $id];
        }

        $request = [];

        $parts = $definition->getArguments()[0] ?? [];

        foreach (\is_array($parts) ? $parts : [] as $part) {
            $matcher = $part instanceof Reference && $container->hasDefinition((string) $part) ? $container->getDefinition((string) $part) : null;
            $class = $matcher?->getClass();
            $value = $this->resolve($container, $matcher?->getArguments()[0] ?? null);

            if (isset(self::SINGLE_VALUES[$class ?? ''])) {
                $request[self::SINGLE_VALUES[$class]] = $value;
            } elseif (isset(self::LIST_VALUES[$class ?? ''])) {
                $request[self::LIST_VALUES[$class]] = \is_string($value) ? preg_split('/\s*,\s*/', $value) : $value;
            } elseif (AttributesRequestMatcher::class === $class) {
                $request['attributes'] = $value;
            } else {
                return ['matcher' => $id];
            }
        }

        return $request;
    }

    private function expression(ContainerBuilder $container, string $id): string
    {
        $expression = $container->hasDefinition($id) ? ($container->getDefinition($id)->getArguments()[0] ?? null) : null;

        return \is_string($expression) ? $expression : 'an expression';
    }

    /**
     * The parameters resolved; an environment variable, whose value is only known at runtime, kept as `%env(NAME)%`.
     */
    private function resolve(ContainerBuilder $container, mixed $value): mixed
    {
        return $container->resolveEnvPlaceholders($container->getParameterBag()->resolveValue($value));
    }

    /**
     * @return array<string, string>
     */
    private function controllerClasses(ContainerBuilder $container): array
    {
        $classes = [];

        foreach (array_keys($container->findTaggedServiceIds('controller.service_arguments')) as $id) {
            $class = $this->resolve($container, $container->getDefinition($id)->getClass());

            if (\is_string($class) && $class !== $id) {
                $classes[$id] = $class;
            }
        }

        return $classes;
    }
}
