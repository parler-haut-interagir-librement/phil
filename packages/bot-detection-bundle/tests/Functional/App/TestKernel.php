<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Tests\Functional\App;

use Override;
use Phil\BotDetectionBundle\Application\VerdictHandler;
use Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy;
use Phil\BotDetectionBundle\Tests\Unit\Application\Fixture\InMemoryRobotVerdictCounter;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * An application as small as the bundle needs: FrameworkBundle, the bundle under an empty `phil_bot_detection`
 * block — or under the block a test hands the constructor —, and two surfaces wired the way
 * `doc/configuration.md` shows: a sign-up form under `robotOrUnknown()` and a payment under `robotOnly()`, each
 * with a verdict handler and an in-memory counter of its own.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $botDetectionConfig the `phil_bot_detection` block
     */
    public function __construct(string $environment, bool $debug, private readonly array $botDetectionConfig = [])
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
        return sys_get_temp_dir().'/phil-bot-detection-bundle-test/'.$this->configurationHash().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-bot-detection-bundle-test/'.$this->configurationHash().'/log';
    }

    private function configurationHash(): string
    {
        return hash('xxh128', __DIR__.json_encode($this->botDetectionConfig, \JSON_THROW_ON_ERROR));
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

        $container->extension('phil_bot_detection', $this->botDetectionConfig);

        $services = $container->services();
        $services->set('app.bot.policy.robot_only', FrictionPolicy::class)->factory([FrictionPolicy::class, 'robotOnly']);
        $services->set('app.bot.policy.robot_or_unknown', FrictionPolicy::class)->factory([FrictionPolicy::class, 'robotOrUnknown']);

        foreach (['sign_up' => 'robot_or_unknown', 'payment' => 'robot_only'] as $surface => $policy) {
            // One counter per surface: increment() takes no argument, so the split is in the wiring.
            $services->set('app.bot.counter.'.$surface, InMemoryRobotVerdictCounter::class)->public();
            $services->set('app.bot.handler.'.$surface, VerdictHandler::class)
                ->args([service('app.bot.policy.'.$policy), service('app.bot.counter.'.$surface)])
                ->public();
        }
    }
}
