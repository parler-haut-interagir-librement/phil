<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of a container, written as text: what each one is, who may read it, how it is tagged and what it is
 * given, down to the services built inside its arguments.
 */
final class DefinitionExporter
{
    public static function export(ContainerBuilder $container): string
    {
        $definitions = $container->getDefinitions();
        ksort($definitions);
        $text = '';

        foreach ($definitions as $id => $definition) {
            $text .= $id."\n".self::describe($definition, '  ')."\n";
        }

        return $text;
    }

    private static function describe(Definition $definition, string $indent): string
    {
        $lines = [$indent.'class: '.($definition->getClass() ?? '(none)')];
        $lines[] = $indent.'public: '.($definition->isPublic() ? 'yes' : 'no');
        $factory = $definition->getFactory();

        if (null !== $factory) {
            $lines[] = $indent.'factory: '.self::value($factory, $indent.'  ');
        }

        $tags = $definition->getTags();
        ksort($tags);

        foreach ($tags as $name => $attributes) {
            $lines[] = $indent.'tag '.$name.': '.self::value($attributes, $indent.'  ');
        }

        if ($definition->isAutoconfigured()) {
            $lines[] = $indent.'autoconfigured';
        }

        foreach ($definition->getArguments() as $position => $argument) {
            $lines[] = $indent.'argument '.$position.': '.self::value($argument, $indent.'  ');
        }

        foreach ($definition->getMethodCalls() as $call) {
            $lines[] = $indent.'call: '.self::value($call, $indent.'  ');
        }

        return implode("\n", $lines);
    }

    private static function value(mixed $value, string $indent): string
    {
        if ($value instanceof Reference) {
            return '@'.$value.(ContainerBuilder::NULL_ON_INVALID_REFERENCE === $value->getInvalidBehavior() ? ' (null on invalid)' : '');
        }

        if ($value instanceof Definition) {
            return "inline\n".self::describe($value, $indent.'  ');
        }

        if ($value instanceof TaggedIteratorArgument) {
            return 'tagged '.$value->getTag();
        }

        if ($value instanceof IteratorArgument || $value instanceof ServiceClosureArgument) {
            return $value::class.' '.self::value($value->getValues(), $indent);
        }

        if (\is_array($value)) {
            $parts = [];

            foreach ($value as $key => $item) {
                $parts[] = $indent.'  '.var_export($key, true).' => '.self::value($item, $indent.'  ');
            }

            return [] === $parts ? '[]' : "[\n".implode("\n", $parts)."\n".$indent.']';
        }

        return var_export($value, true);
    }
}
