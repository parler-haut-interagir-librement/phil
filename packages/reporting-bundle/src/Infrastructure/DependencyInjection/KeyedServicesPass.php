<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\ReportingBundle\Application\Contract\MetricSource;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Contract\ReportDelivery;
use Phil\ReportingBundle\Application\Contract\ReportFormat;
use Phil\ReportingBundle\Application\Contract\SeriesSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * The `key` of every tagged source, report, format and delivery, read from its class's static `key()` when its tag
 * names none, so that the locators find each service by the key it declares.
 */
final class KeyedServicesPass implements CompilerPassInterface
{
    /** @var array<string, class-string> */
    public const array TAGS = [
        ReportingServices::METRIC_SOURCE_TAG => MetricSource::class,
        ReportingServices::SERIES_SOURCE_TAG => SeriesSource::class,
        ReportingServices::REPORT_TAG => ReportDefinition::class,
        ReportingServices::FORMAT_TAG => ReportFormat::class,
        ReportingServices::DELIVERY_TAG => ReportDelivery::class,
    ];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach (self::TAGS as $tag => $interface) {
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $id) {
                $definition = $container->getDefinition($id);
                $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);

                if (!\is_string($class) || !is_a($class, $interface, true)) {
                    throw new InvalidArgumentException(\sprintf('The service "%s" is tagged "%s" but does not implement %s.', $id, $tag, $interface));
                }

                /** @var array<string, list<array<string, mixed>>> $tags */
                $tags = $definition->getTags();

                foreach ($tags[$tag] as $i => $attributes) {
                    $tags[$tag][$i]['key'] ??= $class::key();
                }

                $definition->setTags($tags);
            }
        }
    }
}
