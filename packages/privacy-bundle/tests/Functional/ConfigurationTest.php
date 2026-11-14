<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional;

use Override;
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\PrivacyBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The application booted on other configurations: none at all, which wires the processor with empty lists, and
 * configurations the bundle refuses, on which the kernel does not start, rather than the first log line failing.
 */
#[CoversClass(PhilPrivacyBundle::class)]
final class ConfigurationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FunctionalTestCase::skipWithoutEventDispatcher();
    }

    public function testAMalformedTokenPathPrefixStopsTheKernel(): void
    {
        $this->assertRefused(['log_redaction' => ['token_path_prefixes' => ['p']]], 'phil_privacy.log_redaction: "p" is not a token path prefix');
    }

    public function testAStarInsideADenylistKeyStopsTheKernel(): void
    {
        $this->assertRefused(['log_redaction' => ['denylist' => ['se*ed']]], 'phil_privacy.log_redaction: "se*ed" is not a denylist key');
    }

    public function testAShortOptionOfTwoLettersStopsTheKernel(): void
    {
        $this->assertRefused(['log_redaction' => ['short_options' => ['pw' => 'password']]], 'phil_privacy.log_redaction: "pw" is not a short option');
    }

    public function testAnUnknownKeyStopsTheKernel(): void
    {
        $this->assertRefused(['log_redation' => []], 'Unrecognized option "log_redation" under "phil_privacy"');
    }

    /**
     * Without a `phil_privacy` key, the processor is still wired, with empty lists.
     */
    public function testTheKernelBootsWithoutAnyConfiguration(): void
    {
        self::bootKernel(['configuration' => []]);

        $processor = self::getContainer()->get(RedactingProcessor::class);

        self::assertInstanceOf(RedactingProcessor::class, $processor);
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        $configuration = $options['configuration'] ?? TestKernel::CONFIGURATION;
        self::assertIsArray($configuration);

        /** @var array<string, mixed> $configuration */
        return new TestKernel('test', true, $configuration);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function assertRefused(array $configuration, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        self::bootKernel(['configuration' => $configuration]);
    }
}
