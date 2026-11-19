<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Infrastructure\DependencyInjection\KmsPass;
use Phil\UserSecretsBundle\Infrastructure\DependencyInjection\RefreshLockPass;
use Phil\UserSecretsBundle\Infrastructure\DependencyInjection\UserSecretsServices;
use Phil\UserSecretsBundle\Infrastructure\Privacy\UserSecretsPrivacy;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The credentials people entrust, for an application:
 *
 * ```yaml
 * key_management:
 *     clients:
 *         local: 'sodium://default?keys[k2026]=%env(USER_SECRETS_KEY_2026)%'
 * phil_user_secrets:
 *     current_key: k2026
 * ```
 *
 * Secrets are sealed through Symfony's KeyManagement component. The configuration names the current master key and,
 * when it is not the component's default client, the KMS that holds it, where secrets are kept, how early an OAuth
 * token is refreshed and under which lock, and whether events are logged. Token refreshers, token revokers and
 * auditors are found by autoconfiguration; the table is mapped for Doctrine; with `phil/privacy-bundle`, a person's
 * secrets are exported and erased with their account.
 */
final class PhilUserSecretsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_user_secrets';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('current_key')
                    ->info('The identifier of the master key of the KMS that seals every new value, such as a key of a "sodium://" DSN, an alias or an ARN.')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('kms')
                    ->info('The service of a client of Symfony\'s KeyManagement component that seals the values, such as "key_management.<client>"; null for the default client of the component.')
                    ->defaultNull()
                    ->cannotBeEmpty()
                ->end()
                ->enumNode('store')
                    ->info('Where secrets are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the table.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->integerNode('refresh_leeway')
                    ->info('How many seconds before its expiry an OAuth token is already refreshed.')
                    ->min(0)
                    ->defaultValue(60)
                ->end()
                ->scalarNode('lock_factory')
                    ->info('The lock factory under which one process at a time refreshes an OAuth token: null for FrameworkBundle\'s "lock.factory" when it is defined, false for none, or the service of another factory, which must be defined.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => true === $value || '' === $value)
                        ->thenInvalid('"lock_factory" is null, false or the service of a lock factory.')
                    ->end()
                ->end()
                ->integerNode('refresh_lock_ttl')
                    ->info('How many seconds at most the refresh lock is held, so that a process that dies does not keep it.')
                    ->min(1)
                    ->defaultValue(30)
                ->end()
                ->booleanNode('log_events')
                    ->info('Whether every event is also written to the "logger" service, at info level.')
                    ->defaultFalse()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KmsPass());
        $container->addCompilerPass(new RefreshLockPass());
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilUserSecretsBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\UserSecretsBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        UserSecretsServices::register($builder, $config);

        // The privacy module, when `phil/privacy-bundle` is installed: a person's secrets leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(UserSecretsPrivacy::class, UserSecretsPrivacy::class)
                ->setArguments([new Reference(UserSecrets::class)])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }
}
