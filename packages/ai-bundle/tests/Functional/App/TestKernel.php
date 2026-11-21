<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Functional\App;

use Override;
use Phil\UsageMeter\UsageMeter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with its translator, DoctrineBundle on SQLite, the
 * secrets bundle in memory and the bundle, Symfony AI's mock platform, and a usage meter of 40 tokens and
 * 1 000 micros a month, each task reserving 11 tokens.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-ai-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-ai-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true],
            'default_locale' => 'en', 'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('key_management', ['clients' => ['local' => 'sodium://?keys[k1]='.rtrim(strtr(base64_encode(str_repeat('k', 32)), '+/', '-_'), '=')]]);
        $container->extension('phil_user_secrets', ['current_key' => 'k1', 'store' => 'memory']);
        $container->extension('phil_ai', [
            'platforms' => ['main' => ['platform' => 'app.platform', 'user_key_factory' => Platforms::class, 'secret' => 'main-key']],
            'prices' => ['model-a' => ['input' => 3_000_000, 'output' => 15_000_000]],
            'tasks' => ['draft.rewrite' => ['platform' => 'main', 'model' => 'model-a', 'reserve' => 11]],
            'usage_meter' => UsageMeter::class,
            'quota' => ['tokens_meter' => 'ai_tokens', 'budget_meter' => 'ai_budget'],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('clock', MockClock::class)->args(['2026-11-06 10:00:00 UTC'])->public();
        $services->set('app.platform', PlatformInterface::class)->factory(Platforms::application(...));
        $services->set(Platforms::class);
        $services->set(UsageMeter::class)->factory(Quotas::meter(...))->args([new ReferenceConfigurator('clock')])->public();
    }
}
