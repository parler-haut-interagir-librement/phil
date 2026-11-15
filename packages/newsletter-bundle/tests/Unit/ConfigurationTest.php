<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit;

use Phil\NewsletterBundle\PhilNewsletterBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

#[CoversClass(PhilNewsletterBundle::class)]
final class ConfigurationTest extends TestCase
{
    public function testTheDefaults(): void
    {
        self::assertSame([
            'enabled' => true,
            'secret' => '%kernel.secret%',
            'connection' => 'doctrine.dbal.default_connection',
            'check_mail_domain' => true,
            'site' => 'default',
            'sites' => [],
            'default_profile' => 'default',
            'profiles' => [],
            'clicks' => ['minimum_seconds' => 10],
            'bounces' => [
                'soft_threshold' => 3,
                'verp' => ['enabled' => false, 'local_part' => 'bounces', 'domain' => 'example.com', 'separator' => '+'],
                'imap' => ['enabled' => false, 'host' => '', 'port' => 993, 'user' => '', 'password' => '', 'folder' => 'INBOX', 'delete' => false],
                'remote_event_consumer' => null,
            ],
            'rate_limiter_storage' => 'cache.rate_limiter',
            'webhooks' => [],
            'preview_role' => 'ROLE_ADMIN',
        ], $this->process([]));
    }

    public function testTheDefaultsOfAProfile(): void
    {
        $profiles = $this->process(['profiles' => ['news' => ['from' => 'news@example.com']]])['profiles'];
        self::assertIsArray($profiles);

        self::assertSame([
            'from' => 'news@example.com',
            'from_name' => '',
            'reply_to' => null,
            'transport' => null,
            'unsubscribe_mailbox' => null,
            'window' => ['days' => [1, 2, 3, 4, 5, 6, 7], 'from' => '00:00', 'until' => '24:00', 'timezone' => 'UTC'],
            'daily_cap' => null,
            'rate' => 120,
            'domain_rates' => [],
        ], $profiles['news'] ?? null);
    }

    /**
     * @param array<array-key, mixed> $config
     * @param array<array-key, mixed> $below
     */
    #[DataProvider('limits')]
    public function testEachNumberAcceptsItsMinimumAndRefusesWhatIsBelow(array $config, array $below): void
    {
        $this->process($config);

        $this->expectException(InvalidConfigurationException::class);
        $this->process($below);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, array<array-key, mixed>}>
     */
    public static function limits(): iterable
    {
        yield 'a daily ceiling' => [['profiles' => ['n' => ['from' => 'a@example.com', 'daily_cap' => 1]]], ['profiles' => ['n' => ['from' => 'a@example.com', 'daily_cap' => 0]]]];
        yield 'a pace' => [['profiles' => ['n' => ['from' => 'a@example.com', 'rate' => 1]]], ['profiles' => ['n' => ['from' => 'a@example.com', 'rate' => 0]]]];
        yield 'a pace of one domain' => [['profiles' => ['n' => ['from' => 'a@example.com', 'domain_rates' => ['gmail.com' => 1]]]], ['profiles' => ['n' => ['from' => 'a@example.com', 'domain_rates' => ['gmail.com' => 0]]]]];
        yield 'the seconds of a click' => [['clicks' => ['minimum_seconds' => 0]], ['clicks' => ['minimum_seconds' => -1]]];
        yield 'the soft bounces' => [['bounces' => ['soft_threshold' => 1]], ['bounces' => ['soft_threshold' => 0]]];
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function process(array $config): array
    {
        $extension = new PhilNewsletterBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
