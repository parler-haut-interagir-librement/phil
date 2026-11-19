<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle;

use LogicException;
use Override;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\DoctrineBehaviours\PersonalFieldPolicy;
use Phil\DoctrineBehaviours\PublicationWorkflow;
use Phil\DoctrineBehavioursBundle\Infrastructure\DependencyInjection\BehaviourServices;
use Phil\DoctrineBehavioursBundle\Infrastructure\DependencyInjection\SuspendBlameDuringErasurePass;
use Phil\DoctrineBehavioursBundle\Infrastructure\Security\SecurityVersionRestoreGuard;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/doctrine-behaviours` in a Symfony application: the listeners of the behaviours switched on, the blame of the
 * authenticated user, the tree reader, the positions, the translations, the versions, and the filters, enabled on the default entity
 * manager. With `phil/privacy-bundle` installed, the blame, the owner and the versions answer its access and erasure
 * requests, and no name is blamed while its erasers run.
 *
 * @phpstan-import-type BehavioursConfig from BehaviourServices
 */
final class PhilDoctrineBehavioursBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_doctrine_behaviours';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('timestamps')->info('Stamp Timestampable entities.')->defaultTrue()->end()
                ->arrayNode('blame')
                    ->info('Blame Blameable entities on the authenticated user.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('user_method')
                            ->info('A public method of the user class naming them in the blame, such as a UUID getter; getUserIdentifier() when null.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue($this->isNoMethodName(...))
                                ->thenInvalid('The user method of the blame is the name of a method, %s is not.')
                            ->end()
                        ->end()
                        ->booleanNode('suspend_during_erasure')
                            ->info('Write no name in the blame while an eraser of phil/privacy-bundle runs, so that its flushes do not put a name back.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('soft_delete')
                    ->canBeDisabled()
                    ->children()
                        ->booleanNode('filter')->info('Add the phil_soft_deleted filter to the default entity manager, enabled.')->defaultTrue()->end()
                    ->end()
                ->end()
                ->booleanNode('tree')->info('Keep the paths of TreeNode entities, and register Tree.')->defaultTrue()->end()
                ->arrayNode('deactivation')
                    ->info('Stamp the deactivation of Deactivatable entities.')
                    ->canBeDisabled()
                    ->children()
                        ->booleanNode('filter')->info('Add the phil_deactivated filter to the default entity manager, enabled.')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('owner')
                    ->info('Give Ownable entities the authenticated user as their owner.')
                    ->canBeEnabled()
                    ->validate()
                        ->ifTrue(static fn (array $owner): bool => true === $owner['enabled'] && null === $owner['user_method'])
                        ->then(static function (): never {
                            throw new InvalidConfigurationException('phil_doctrine_behaviours.owner.user_method is required: name a public method of the user class that gives a stable identifier, such as a UUID getter. getUserIdentifier() is not assumed, since it is often an e-mail address.');
                        })
                    ->end()
                    ->children()
                        ->scalarNode('user_method')
                            ->info('A public method of the user class giving a stable identifier, such as a UUID getter; required.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue($this->isNoMethodName(...))
                                ->thenInvalid('The user method of the owner is the name of a method, %s is not.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('publication')
                    ->info('Stamp the dates of Publishable entities as the publication workflow moves them; needs symfony/workflow.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('workflow')
                            ->info('The name of the publication workflow.')
                            ->defaultValue(PublicationWorkflow::NAME)
                            ->validate()
                                ->ifTrue(static fn (mixed $name): bool => !\is_string($name) || 1 !== preg_match('/^[a-z][a-z0-9_]*$/D', $name))
                                ->thenInvalid('The name of the publication workflow is lowercase letters, digits and underscores, %s is not.')
                            ->end()
                        ->end()
                        ->booleanNode('define_workflow')->info('Declare the workflow under that name; false when the application declares its own.')->defaultTrue()->end()
                    ->end()
                ->end()
                ->booleanNode('position')->info('Send Positionable entities with no position to the end of their group, and register Positions.')->defaultTrue()->end()
                ->booleanNode('translation')->info('Give each Translation entity its unique index on its record and locale, and register Translations.')->defaultTrue()->end()
                ->arrayNode('slug')
                    ->info('Give Sluggable entities a unique slug; needs phil/slug-bundle.')
                    ->canBeEnabled()
                ->end()
                ->arrayNode('versions')
                    ->info('Freeze versions of Versionable entities, and fork the next edit.')
                    ->canBeEnabled()
                    ->validate()
                        ->ifTrue(static fn (array $versions): bool => true === $versions['enabled'] && PersonalFieldPolicy::Encrypt->value === $versions['personal_fields'] && null === $versions['cipher'])
                        ->then(static function (): never {
                            throw new InvalidConfigurationException('phil_doctrine_behaviours.versions.cipher is required when personal_fields is "encrypt": name the service of a VersionCipher that seals the personal fields.');
                        })
                    ->end()
                    ->children()
                        ->enumNode('personal_fields')
                            ->info('What a frozen version keeps of the #[PersonalData] fields of its record: exclude leaves them out, encrypt seals them with the cipher, keep keeps them until an erasure redacts them.')
                            ->values(array_map(static fn (PersonalFieldPolicy $policy): string => $policy->value, PersonalFieldPolicy::cases()))
                            ->defaultValue(PersonalFieldPolicy::Exclude->value)
                        ->end()
                        ->scalarNode('cipher')
                            ->info('The service of a VersionCipher, required when personal_fields is encrypt.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('restore_right')
                            ->info('The Symfony Security attribute needed to go back to a past version, with the record as subject.')
                            ->defaultValue(SecurityVersionRestoreGuard::DEFAULT_RIGHT)
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('dated_identifier')
                    ->info('Give DatedIdentifiable entities a UUID v7; needs symfony/uid.')
                    ->canBeEnabled()
                ->end()
                ->arrayNode('privacy')
                    ->info('Answer the access and erasure requests of phil/privacy-bundle for the blame and the owner, each when it is on; only when phil/privacy-bundle is installed.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('blame_replacement')
                            ->info("The pseudonym that takes an erased name's place in the blame, naming nobody; null leaves the blame empty.")
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $name): bool => null !== $name && (!\is_string($name) || '' === $name || \strlen($name) > 255 || 1 === preg_match('/[\x00-\x1F\x7F]/', $name)))
                                ->thenInvalid('The pseudonym of the blame is a string of 1 to 255 bytes without control characters, %s is not.')
                            ->end()
                        ->end()
                        ->scalarNode('owner_replacement')
                            ->info("The owner identifier that takes an erased owner's place, naming nobody; null leaves the records without an owner.")
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $owner): bool => null !== $owner && (!\is_string($owner) || '' === $owner || \strlen($owner) > OwnerId::MAX_LENGTH || str_contains($owner, '@')))
                                ->thenInvalid('The pseudonym of the owner is an owner identifier, 1 to 255 bytes without an "@", %s is not.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SuspendBlameDuringErasurePass());
    }

    /**
     * Adds to DoctrineBundle and FrameworkBundle what the behaviours switched on need.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var BehavioursConfig $config */
        $config = new Processor()->processConfiguration(new Configuration($this, $builder, $this->extensionAlias), $builder->getExtensionConfig($this->extensionAlias));

        if ($config['slug']['enabled'] && !$builder->hasExtension('phil_slug')) {
            throw new LogicException('The slug behaviour takes its sluggers from phil/slug-bundle: run "composer require phil/slug-bundle" and register it, or set phil_doctrine_behaviours.slug to false.');
        }

        $doctrine = BehaviourServices::doctrine($config);

        if ([] !== $doctrine && $builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', $doctrine);
        }

        if ($config['publication']['enabled'] && $config['publication']['define_workflow'] && $builder->hasExtension('framework')) {
            $builder->prependExtensionConfig('framework', BehaviourServices::workflow($config['publication']['workflow']));
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var BehavioursConfig $config */
        BehaviourServices::register($builder, $config);
    }

    private function isNoMethodName(mixed $method): bool
    {
        return null !== $method && (!\is_string($method) || 1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $method));
    }
}
