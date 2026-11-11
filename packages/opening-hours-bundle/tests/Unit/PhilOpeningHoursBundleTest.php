<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\OpeningHoursBundle\Infrastructure\Doctrine\OpeningHoursColumnType;
use Phil\OpeningHoursBundle\Infrastructure\Form\OpeningHoursType;
use Phil\OpeningHoursBundle\Infrastructure\Twig\OpeningHoursExtension;
use Phil\OpeningHoursBundle\PhilOpeningHoursBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilOpeningHoursBundle::class)]
final class PhilOpeningHoursBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilOpeningHours(): void
    {
        self::assertSame('phil_opening_hours', $this->extension()->getAlias());
    }

    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([]);

        self::assertSame(['service_container', OpeningHoursType::class, OpeningHoursExtension::class], array_keys($container->getDefinitions()));
        self::assertSame(['UTC'], $container->getDefinition(OpeningHoursType::class)->getArguments());
        self::assertTrue($container->getDefinition(OpeningHoursType::class)->hasTag('form.type'));
        self::assertTrue($container->getDefinition(OpeningHoursExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(OpeningHoursExtension::class)->hasTag('twig.runtime'));

        $clock = $container->getDefinition(OpeningHoursExtension::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $clock);
        self::assertSame(['clock', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE], [(string) $clock, $clock->getInvalidBehavior()]);
    }

    public function testTheDefaultTimeZoneReachesTheForm(): void
    {
        self::assertSame(['America/Toronto'], $this->load([['default_timezone' => 'America/Toronto']])->getDefinition(OpeningHoursType::class)->getArguments());
    }

    public function testTheDefaultTimeZoneIsAnIanaIdentifier(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The default time zone is an IANA identifier: "Mars\\/Olympus".');

        $this->load([['default_timezone' => 'Mars/Olympus']]);
    }

    public function testTheColumnTypeIsPrependedToDoctrine(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());

        $extension = $this->extension();
        $container->registerExtension($extension);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);
        self::assertSame([['dbal' => ['types' => [OpeningHoursColumnType::NAME => OpeningHoursColumnType::class]]]], $container->getExtensionConfig('doctrine'));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertFalse($bare->hasExtension('doctrine'));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilOpeningHoursBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
