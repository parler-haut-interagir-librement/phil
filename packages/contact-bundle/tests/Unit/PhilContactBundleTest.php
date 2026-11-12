<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\Contact\JSContactReader;
use Phil\ContactBundle\Infrastructure\Doctrine\ContactCardColumnType;
use Phil\ContactBundle\Infrastructure\Twig\ContactExtension;
use Phil\ContactBundle\PhilContactBundle;
use Phil\StructuredData\Contract\NodeCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilContactBundle::class)]
final class PhilContactBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilContact(): void
    {
        self::assertSame('phil_contact', $this->extension()->getAlias());
        self::assertSame('@PhilContact/form/theme.html.twig', PhilContactBundle::FORM_THEME);
    }

    public function testTheTwigExtensionIsRegistered(): void
    {
        $container = new ContainerBuilder();
        $this->extension()->load([], $container);

        self::assertSame(['service_container', JSContactReader::class, ContactExtension::class], array_keys($container->getDefinitions()));
        self::assertSame([65_536, 16, 100, null], $container->getDefinition(JSContactReader::class)->getArguments());
        self::assertTrue($container->getDefinition(ContactExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(ContactExtension::class)->hasTag('twig.runtime'));
        self::assertEquals([new Reference(NodeCollector::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(ContactExtension::class)->getArguments());
    }

    public function testTheBoundsOfTheJSContactReaderAreConfigured(): void
    {
        $container = new ContainerBuilder();
        $this->extension()->load([['jscontact' => ['max_bytes' => 1_024, 'max_depth' => 2, 'max_entries' => 1, 'region' => 'CA']]], $container);

        self::assertSame([1_024, 2, 1, 'CA'], $container->getDefinition(JSContactReader::class)->getArguments());
    }

    public function testTheSmallestBoundsAreTaken(): void
    {
        $container = new ContainerBuilder();
        $this->extension()->load([['jscontact' => ['max_bytes' => 1, 'max_depth' => 2, 'max_entries' => 1]]], $container);

        self::assertSame([1, 2, 1, null], $container->getDefinition(JSContactReader::class)->getArguments());
    }

    public function testBoundsThatReadNothingAreRefused(): void
    {
        foreach ([['max_bytes' => 0], ['max_depth' => 1], ['max_entries' => 0]] as $bounds) {
            try {
                $this->extension()->load([['jscontact' => $bounds]], new ContainerBuilder());
                self::fail('Bounds that read nothing were taken.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_contact.jscontact.'.array_key_first($bounds), $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheColumnTypeAndTheFormThemeArePrepended(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension(new TwigExtension());

        $extension = $this->extension();
        $container->registerExtension($extension);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['dbal' => ['types' => [ContactCardColumnType::NAME => ContactCardColumnType::class]]]], $container->getExtensionConfig('doctrine'));
        self::assertSame([['form_themes' => [PhilContactBundle::FORM_THEME]]], $container->getExtensionConfig('twig'));
    }

    public function testWithoutDoctrineNorTwigNothingIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertFalse($container->hasExtension('doctrine'));
        self::assertFalse($container->hasExtension('twig'));
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilContactBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
