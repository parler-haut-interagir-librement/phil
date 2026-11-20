<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

/**
 * An application as small as the module needs: FrameworkBundle, DoctrineBundle on an in-memory SQLite database,
 * the core of commerce and the module — or the module without the core, when a test asks for it, or with AssetMapper
 * and StimulusBundle in a project of the test's own.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $pos        the module's configuration
     * @param string|null          $projectDir a project with AssetMapper and StimulusBundle, or none
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly bool $withCore = true,
        private readonly array $pos = [],
        private readonly ?string $projectDir = null,
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * Replaces the trait's own, which reads `config/bundles.php` only.
     */
    private function getBundlesPath(): string
    {
        $bundles = match (true) {
            !$this->withCore => 'bundles-without-core.php',
            null !== $this->projectDir => 'bundles-with-assets.php',
            default => 'bundles.php',
        };

        return __DIR__.'/config/'.$bundles;
    }

    #[Override]
    public function getProjectDir(): string
    {
        return $this->projectDir ?? __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->varDir().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->varDir().'/log';
    }

    private function varDir(): string
    {
        $variant = ($this->withCore ? 'core' : 'alone').'-'.substr(hash('xxh128', serialize([$this->pos, $this->projectDir])), 0, 12);

        return sys_get_temp_dir().'/phil-commerce-pos-bundle-test/'.$variant;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret, long enough',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        // Nothing the tests do is meant to be logged; the default logger would print it.
        $container->services()->set('logger', NullLogger::class);
        // The rate limiter a test names to enable the cashier switch.
        $container->services()->set('test.pin_limiter', RateLimiterFactory::class)
            ->args([['id' => 'pin', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '15 minutes'], inline_service(InMemoryStorage::class)]);

        if ($this->withCore) {
            $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        }

        if ([] !== $this->pos) {
            $container->extension('phil_commerce_pos', $this->pos);
        }
    }
}
