<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional\App;

use Override;
use Phil\CommerceBundle\Application\Port\PaymentAttemptStateStore;
use Phil\CommerceBundle\Domain\Service\PaymentAttemptLimiter;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * An application as small as the bundle needs: FrameworkBundle, the bundle under the `phil_commerce` block a test
 * hands the constructor — the GST, QST and HST of goods and digital goods by default —, a sellable resolver and a
 * price resolver that autoconfiguration plugs into the order modifier, as a module would, and the payment attempt
 * limiter with an in-memory store, built from parameters of the application as `doc/installation.md` shows.
 * DoctrineBundle on an in-memory SQLite database stores orders. A held stock plugs into the deferred sales.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const array RATES = ['receipts' => ['issuer' => ['name' => 'Le Magasin', 'tax_numbers' => ['GST' => '123456789 RT0001']]], 'tax_rates' => [
        'goods' => ['QC' => ['GST' => '5', 'QST' => '9.975'], 'ON' => ['HST' => '13']],
        'digital' => ['QC' => ['GST' => '5', 'QST' => '9.975']],
    ]];

    /**
     * @param array<string, mixed> $commerceConfig the `phil_commerce` block
     */
    public function __construct(string $environment, bool $debug, private readonly array $commerceConfig = self::RATES)
    {
        parent::__construct($environment, $debug);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        // One container per configuration: the block is compiled into it.
        return sys_get_temp_dir().'/phil-commerce-bundle-test/'.$this->configurationHash().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-commerce-bundle-test/'.$this->configurationHash().'/log';
    }

    /**
     * The database the storage tests run on: SQLite in memory, or the server `PHIL_COMMERCE_TEST_DATABASE_URL` names,
     * such as the MySQL 8.4 or PostgreSQL 17 of `composer test:services`.
     */
    public static function databaseUrl(): string
    {
        $url = getenv('PHIL_COMMERCE_TEST_DATABASE_URL');

        return \is_string($url) && '' !== $url ? $url : 'sqlite:///:memory:';
    }

    private function configurationHash(): string
    {
        return hash('xxh128', __DIR__.self::databaseUrl().json_encode($this->commerceConfig, \JSON_THROW_ON_ERROR));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        // Nothing the tests do is meant to be logged; the default logger would print it.
        $container->services()->set('logger', NullLogger::class);

        $container->extension('doctrine', ['dbal' => ['url' => self::databaseUrl()], 'orm' => []]);

        $container->extension('phil_commerce', $this->commerceConfig);

        $container->parameters()
            ->set('app.payment_attempts.budget', 3)
            ->set('app.payment_attempts.penalty_step', 30)
            ->set('app.payment_attempts.window', 900);

        $services = $container->services();
        $services->defaults()->autoconfigure();
        $services->set(InMemorySellables::class);
        $services->set(InMemoryOptions::class);
        $services->set(InMemoryDrawer::class);
        $services->set(WholesalePrices::class);
        $services->set(TestGateway::class);
        $services->set(HeldStock::class)->public();
        $services->set(HeardEvents::class)->public();
        $services->set('app.payment_attempt_policy', PaymentAttemptPolicy::class)
            ->factory([PaymentAttemptPolicy::class, 'of'])
            ->args([param('app.payment_attempts.budget'), param('app.payment_attempts.penalty_step'), param('app.payment_attempts.window')]);
        $services->set(PaymentAttemptLimiter::class)->args([service('app.payment_attempt_policy')])->public();
        $services->set(InMemoryPaymentAttemptStateStore::class)->public();
        $services->alias(PaymentAttemptStateStore::class, InMemoryPaymentAttemptStateStore::class)->public();
    }
}
