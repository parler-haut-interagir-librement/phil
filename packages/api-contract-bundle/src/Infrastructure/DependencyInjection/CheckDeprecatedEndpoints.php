<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Phil\ApiContract\Exception\InvalidContractValue;
use Phil\ApiContractBundle\Infrastructure\Http\DeprecatedEndpoint;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Builds every `#[DeprecatedEndpoint]` of the controller services — tagged `controller.service_arguments`, as
 * autoconfiguration tags them — when the container is compiled: a date that cannot be read or a sunset before the
 * deprecation fails the build, naming the controller, instead of answering `500` on each request to the endpoint.
 */
final readonly class CheckDeprecatedEndpoints implements CompilerPassInterface
{
    public const string CONTROLLER_TAG = 'controller.service_arguments';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(self::CONTROLLER_TAG)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass() ?? $id);
            $reflection = \is_string($class) ? $container->getReflectionClass($class, false) : null;

            if (!$reflection instanceof ReflectionClass) {
                continue;
            }

            $this->check($reflection->getAttributes(DeprecatedEndpoint::class), $reflection->getName());

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $this->check($method->getAttributes(DeprecatedEndpoint::class), $reflection->getName().'::'.$method->getName().'()');
            }
        }
    }

    /**
     * @param list<ReflectionAttribute<DeprecatedEndpoint>> $attributes
     */
    private function check(array $attributes, string $where): void
    {
        foreach ($attributes as $attribute) {
            try {
                $attribute->newInstance();
            } catch (InvalidContractValue $invalidContractValue) {
                throw new LogicException(\sprintf('The #[DeprecatedEndpoint] of %s cannot be sent: %s', $where, $invalidContractValue->getMessage()), 0, $invalidContractValue);
            }
        }
    }
}
