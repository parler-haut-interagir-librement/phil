<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsConfiguration;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

#[CoversClass(RightsConfiguration::class)]
final class RightsConfigurationTest extends TestCase
{
    public function testTheGuestRequestLimitsDefaultToFiveAnHourPerClientAndThreeADayPerAddress(): void
    {
        $guests = $this->process([])['guest_requests'];

        self::assertIsArray($guests);
        self::assertSame(['limit' => 5, 'interval' => '1 hour'], $guests['per_client']);
        self::assertSame(['limit' => 3, 'interval' => '1 day'], $guests['per_address']);
    }

    public function testALimitAllowsAtLeastOneRequest(): void
    {
        foreach (['per_client', 'per_address'] as $limit) {
            $guests = $this->process(['guest_requests' => [$limit => ['limit' => 1]]])['guest_requests'];
            self::assertIsArray($guests);
            self::assertIsArray($guests[$limit]);
            self::assertSame(1, $guests[$limit]['limit']);

            try {
                $this->process(['guest_requests' => [$limit => ['limit' => 0]]]);
                self::fail($limit);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_privacy.guest_requests.'.$limit.'.limit', $invalidConfigurationException->getMessage());
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function process(array $config): array
    {
        $extension = new PhilPrivacyBundle('php://memory')->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
