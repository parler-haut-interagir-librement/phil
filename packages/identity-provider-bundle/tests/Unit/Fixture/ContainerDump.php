<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use UnitEnum;

/**
 * What the extension registered, one line per service: the class, the visibility, the arguments, the tags and the calls,
 * for the tests to compare with what the wiring is meant to be.
 */
final class ContainerDump
{
    public static function of(ContainerBuilder $container): string
    {
        $lines = [];
        $definitions = $container->getDefinitions();
        ksort($definitions);

        foreach ($definitions as $id => $definition) {
            $lines[] = $id.' = '.self::definition($definition);
        }

        $aliases = $container->getAliases();
        ksort($aliases);

        foreach ($aliases as $id => $alias) {
            $lines[] = $id.' => '.$alias.($alias->isPublic() ? ' public' : '');
        }

        $parameters = $container->getParameterBag()->all();
        ksort($parameters);

        foreach ($parameters as $name => $value) {
            $lines[] = '%'.$name.' = '.self::value($value);
        }

        return implode("\n", $lines)."\n";
    }

    private static function definition(Definition $definition): string
    {
        $parts = [(string) $definition->getClass(), $definition->isPublic() ? 'public' : 'private'];
        $parts[] = '('.implode(', ', array_map(self::value(...), $definition->getArguments())).')';

        $tags = $definition->getTags();
        ksort($tags);

        foreach ($tags as $name => $attributes) {
            foreach ((array) $attributes as $attribute) {
                $parts[] = 'tag '.$name.' '.self::value($attribute);
            }
        }

        if (null !== $definition->getFactory()) {
            $parts[] = 'factory '.self::value($definition->getFactory());
        }

        if ($definition->isAutowired()) {
            $parts[] = 'autowired';
        }

        if ($definition->isAutoconfigured()) {
            $parts[] = 'autoconfigured';
        }

        return implode(' ', $parts);
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            $value instanceof Reference => '@'.(ContainerBuilder::EXCEPTION_ON_INVALID_REFERENCE === $value->getInvalidBehavior() ? '' : '?').$value,
            $value instanceof Definition => 'new '.self::definition($value),
            $value instanceof TaggedIteratorArgument => 'tagged('.$value->getTag().')',
            $value instanceof IteratorArgument, $value instanceof ServiceLocatorArgument => $value::class.self::value($value->getValues()),
            $value instanceof UnitEnum => $value::class.'::'.$value->name,
            \is_array($value) => '['.implode(', ', array_map(static fn (int|string $key, mixed $item): string => (array_is_list($value) ? '' : $key.' => ').self::value($item), array_keys($value), $value)).']',
            \is_object($value) => 'object '.$value::class,
            default => var_export($value, true),
        };
    }
}
