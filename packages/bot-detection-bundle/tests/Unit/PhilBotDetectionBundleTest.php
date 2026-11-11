<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Tests\Unit;

use Phil\BotDetectionBundle\PhilBotDetectionBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(PhilBotDetectionBundle::class)]
final class PhilBotDetectionBundleTest extends TestCase
{
    public function testItsExtensionAliasIsPhilBotDetection(): void
    {
        $extension = new PhilBotDetectionBundle()->getContainerExtension();

        self::assertNotNull($extension);
        self::assertSame('phil_bot_detection', $extension->getAlias());
    }

    /**
     * A skeleton declares nothing: loading it adds no service beyond what the container always holds.
     */
    public function testItDeclaresNoServiceYet(): void
    {
        $container = new ContainerBuilder();
        $before = $container->getServiceIds();
        $extension = new PhilBotDetectionBundle()->getContainerExtension();
        self::assertNotNull($extension);

        $extension->load([], $container);

        self::assertSame($before, $container->getServiceIds());
    }
}
