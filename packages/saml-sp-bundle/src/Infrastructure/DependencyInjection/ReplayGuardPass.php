<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\SamlSp\Contract\ReplayGuard;
use Phil\SamlSp\Store\CacheReplayGuard;
use Phil\SamlSp\Store\DbalReplayGuard;
use Phil\SamlSpBundle\Infrastructure\Console\PurgeAssertionsCommand;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Chooses the store of the accepted assertions once every bundle has defined its services: `DbalReplayGuard` on the
 * Doctrine DBAL connection named by `replay_guard.connection` when it exists — `doctrine.dbal.<name>_connection` —,
 * `CacheReplayGuard` on `cache_pool` otherwise, or the one `replay_guard.store` names.
 *
 * In the prod environment, it refuses a cache pool that stays on each server — the APCu, array, filesystem or system
 * adapter, `cache.app` by default —, as `PoolLocality` tells: an assertion accepted by one server could be posted again
 * to another, which would accept it too. `single_server` lifts the refusal.
 *
 * An application that defines `ReplayGuard` itself, as a service or an alias, keeps its own guard, unchecked. The bundle
 * notes its choices in a hidden parameter while it loads its configuration; the pass reads and removes it.
 *
 * @phpstan-type Choice array{store: 'auto'|'doctrine'|'cache', connection: string, table: string, pool: string, clock: string, single_server: bool}
 */
final readonly class ReplayGuardPass implements CompilerPassInterface
{
    /** The hidden parameter of the choices of the bundle, removed by the pass. */
    public const string PARAMETER = '.phil_saml_sp.replay_guard';

    public function __construct(
        private PoolLocality $locality = new PoolLocality(),
    ) {
    }

    /**
     * @throws InvalidConfigurationException when the store asked for is missing, or the pool is local to each server
     *                                       in production
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }

        /** @var Choice $choice */
        $choice = $container->getParameter(self::PARAMETER);
        $container->getParameterBag()->remove(self::PARAMETER);

        if ($container->has(ReplayGuard::class)) {
            return;
        }

        $connection = \sprintf('doctrine.dbal.%s_connection', $choice['connection']);

        if ('cache' !== $choice['store'] && $container->has($connection)) {
            $container->register(DbalReplayGuard::class, DbalReplayGuard::class)->setArguments([new Reference($connection), new Reference($choice['clock']), $choice['table']]);
            $container->setAlias(ReplayGuard::class, DbalReplayGuard::class);

            if (class_exists(Command::class)) {
                $container->register(PurgeAssertionsCommand::class, PurgeAssertionsCommand::class)
                    ->setArguments([new Reference(DbalReplayGuard::class), new Reference($choice['clock'])])
                    ->addTag('console.command', ['command' => PurgeAssertionsCommand::NAME]);
            }

            return;
        }

        if ('doctrine' === $choice['store']) {
            throw new InvalidConfigurationException(\sprintf('phil_saml_sp.replay_guard.store: there is no Doctrine DBAL connection "%s" (the service "%s"). Configure it with doctrine/doctrine-bundle, name another one in "replay_guard.connection", or set "replay_guard.store" to "cache".', $choice['connection'], $connection));
        }

        $container->register(CacheReplayGuard::class, CacheReplayGuard::class)->setArguments([new Reference($choice['pool'])]);
        $container->setAlias(ReplayGuard::class, CacheReplayGuard::class);

        if ($choice['single_server'] || 'prod' !== $container->getParameter('kernel.environment') || !$this->locality->isLocal($container, $choice['pool'])) {
            return;
        }

        throw new InvalidConfigurationException(\sprintf('phil_saml_sp.cache_pool: the pool "%s" keeps the accepted assertions on each server, so an assertion posted again to another server would be accepted again. Configure a Doctrine DBAL connection for "replay_guard", name a cache pool on a shared store — Redis, Memcached, a database —, or set "single_server" to true on a single server.', $choice['pool']));
    }
}
