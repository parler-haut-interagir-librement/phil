<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle;

use Doctrine\DBAL\Types\Type;
use Override;
use Phil\RecurrenceBundle\Application\RuleDescription;
use Phil\RecurrenceBundle\Infrastructure\Doctrine\RecurrenceRuleColumnType;
use Phil\RecurrenceBundle\Infrastructure\Form\RecurrenceRuleType;
use Phil\RecurrenceBundle\Infrastructure\Twig\RecurrenceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;

/**
 * `phil/recurrence` in a Symfony application: the rule form, the column type `phil_rrule`, and rules in Twig.
 */
final class PhilRecurrenceBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_recurrence';

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && class_exists(Type::class)) {
            $container->extension('doctrine', ['dbal' => ['types' => [RecurrenceRuleColumnType::NAME => RecurrenceRuleColumnType::class]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services->set(RecurrenceRuleType::class)->tag('form.type');
        $services->set(RuleDescription::class)->args([new Reference('translator')])->public();

        if (class_exists(AsTwigFilter::class)) {
            $services->set(RecurrenceExtension::class)
                ->args([new Reference(RuleDescription::class)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
