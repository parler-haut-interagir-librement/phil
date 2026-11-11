<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Tests\Functional;

use Phil\BotDetectionBundle\PhilBotDetectionBundle;
use Phil\BotDetectionBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;

/**
 * The bundle registered in an application: its extension is known under `phil_bot_detection`, an empty block is the
 * whole valid configuration, and any key under it fails the container build.
 */
#[CoversClass(PhilBotDetectionBundle::class)]
final class BundleRegistrationTest extends FunctionalTestCase
{
    public function testTheKernelBootsWithTheBundleUnderItsAlias(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $bundle = $kernel->getBundle('PhilBotDetectionBundle');

        self::assertSame('Phil\\BotDetectionBundle', $bundle->getNamespace());
        self::assertSame('phil_bot_detection', $bundle->getContainerExtension()?->getAlias());
    }

    /**
     * The framework's console finds the extension by its alias, and its reference has no key yet.
     */
    public function testTheConfigurationReferenceIsEmpty(): void
    {
        $tester = $this->command('config:dump-reference');

        self::assertSame(Command::SUCCESS, $tester->execute(['name' => 'phil_bot_detection']));
        self::assertMatchesRegularExpression('/^phil_bot_detection:\s+\[\]$/m', $tester->getDisplay());
    }

    public function testAnUnknownKeyFailsTheContainerBuild(): void
    {
        $kernel = new TestKernel('test', true, ['foo' => true]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Unrecognized option "foo" under "phil_bot_detection". Available options are "".');

        $kernel->boot();
    }
}
