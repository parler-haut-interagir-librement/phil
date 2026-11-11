<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\RecurrenceBundle\Application\RuleDescription;
use Phil\RecurrenceBundle\Infrastructure\Doctrine\RecurrenceRuleColumnType;
use Phil\RecurrenceBundle\Infrastructure\Form\RecurrenceRuleType;
use Phil\RecurrenceBundle\Infrastructure\Twig\RecurrenceExtension;
use Phil\RecurrenceBundle\PhilRecurrenceBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilRecurrenceBundle::class)]
final class PhilRecurrenceBundleTest extends TestCase
{
    public function testTheServicesAreRegistered(): void
    {
        $extension = new PhilRecurrenceBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $container = new ContainerBuilder();
        $extension->load([], $container);

        self::assertSame('phil_recurrence', $extension->getAlias());
        self::assertSame(['service_container', RecurrenceRuleType::class, RuleDescription::class, RecurrenceExtension::class], array_keys($container->getDefinitions()));
        self::assertTrue($container->getDefinition(RecurrenceRuleType::class)->hasTag('form.type'));
        self::assertEquals([new Reference('translator')], $container->getDefinition(RuleDescription::class)->getArguments());
        self::assertTrue($container->getDefinition(RuleDescription::class)->isPublic());
        self::assertEquals([new Reference(RuleDescription::class)], $container->getDefinition(RecurrenceExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(RecurrenceExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(RecurrenceExtension::class)->hasTag('twig.runtime'));
    }

    public function testTheColumnTypeIsPrependedToDoctrine(): void
    {
        $extension = new PhilRecurrenceBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['dbal' => ['types' => [RecurrenceRuleColumnType::NAME => RecurrenceRuleColumnType::class]]]], $container->getExtensionConfig('doctrine'));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertFalse($bare->hasExtension('doctrine'));
    }
}
