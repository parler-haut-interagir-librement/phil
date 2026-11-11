<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App;

use Override;
use Phil\DddBundle\Tests\Functional\App\Membership\CountMembersHandler;
use Phil\DddBundle\Tests\Functional\App\Membership\FindMemberEmailHandler;
use Phil\DddBundle\Tests\Functional\App\Membership\MemberRegistry;
use Phil\DddBundle\Tests\Functional\App\Membership\Membership;
use Phil\DddBundle\Tests\Functional\App\Membership\RegisterMemberHandler;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with Messenger and two buses of its own,
 * `write.bus` and `read.bus` — named otherwise than the bundle's defaults, so that the configuration is what joins
 * them —, the bundle (`config/bundles.php`), and the handlers of a small membership context, registered with
 * autoconfiguration and no tag, and a service that receives both ports by autowiring.
 *
 * The buses carry no transaction middleware: the application gives the command bus `doctrine_transaction` when it
 * has Doctrine, and this one has none.
 *
 * The `phil_ddd` configuration is the kernel's argument, so that a test can boot the same application on a
 * configuration the bundle refuses; each configuration compiles a container of its own.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string COMMAND_BUS = 'write.bus';

    public const string QUERY_BUS = 'read.bus';

    public const array CONFIGURATION = ['command_bus' => self::COMMAND_BUS, 'query_bus' => self::QUERY_BUS];

    /**
     * @param array<string, mixed> $configuration the `phil_ddd` configuration
     */
    public function __construct(string $environment = 'test', bool $debug = true, private readonly array $configuration = self::CONFIGURATION)
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
        // One container per configuration: it is compiled into it.
        return sys_get_temp_dir().'/phil-ddd-bundle-test/'.$this->fingerprint().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-ddd-bundle-test/'.$this->fingerprint().'/log';
    }

    private function fingerprint(): string
    {
        return hash('xxh128', __DIR__.json_encode($this->configuration, \JSON_THROW_ON_ERROR));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'messenger' => [
                'default_bus' => self::COMMAND_BUS,
                'buses' => [self::COMMAND_BUS => null, self::QUERY_BUS => null],
            ],
        ]);

        $services = $container->services();

        // A failing handler is logged by Messenger; the default logger would print it.
        $services->set('logger', NullLogger::class);

        $services->defaults()->autowire()->autoconfigure();
        $services->set(MemberRegistry::class)->public();
        $services->set(Membership::class)->public();
        $services->set(RegisterMemberHandler::class);
        $services->set(CountMembersHandler::class);
        $services->set(FindMemberEmailHandler::class);

        $container->extension('phil_ddd', $this->configuration);
    }
}
