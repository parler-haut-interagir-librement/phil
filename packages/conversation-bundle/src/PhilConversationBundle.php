<?php

declare(strict_types=1);

namespace Phil\ConversationBundle;

use Override;
use Phil\ConversationBundle\Application\ConversationLimits;
use Phil\ConversationBundle\Infrastructure\DependencyInjection\ConversationServices;
use Phil\ConversationBundle\Infrastructure\DependencyInjection\EncryptionPass;
use Phil\ConversationBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\ConversationBundle\Infrastructure\Http\ConversationRequest;
use Phil\ConversationBundle\Infrastructure\Mercure\MercureMessagePublisher;
use Phil\ConversationBundle\Infrastructure\Notifications\NotifyParticipants;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\HubInterface;

/**
 * Private messages for an application: conversations between two members or in groups, their participants named by
 * stable identifiers, their subjects and bodies sealed at rest through Symfony's KeyManagement component and kept
 * through DBAL, the reception settings of each member, read and unread, and the pages that show them with the recipes
 * of the kit. The notification type is declared to phil/notifications-bundle when it is installed, the export and the
 * erasure handed to phil/privacy-bundle when it is, and with AssetMapper the Stimulus controller of the real time is
 * served without a build step.
 *
 * @phpstan-import-type Config from ConversationServices
 */
final class PhilConversationBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    /** The service of the Mercure hub, by default: the interface MercureBundle aliases to its default hub. */
    public const string HUB = HubInterface::class;

    #[Override]
    protected string $extensionAlias = 'phil_conversation';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')
                    ->info('The DBAL connection of the tables.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->arrayNode('encryption')
                    ->info("How the subjects and bodies are sealed at rest, through Symfony's KeyManagement component; required, the conversations are never kept in the clear.")
                    ->isRequired()
                    ->children()
                        ->scalarNode('key')->info('The master key of the key management service that seals the new texts; the texts sealed under a previous key still open while the service holds it.')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('kms')->info('The service of a client of the KeyManagement component, such as "key_management.<client>"; null for its default client.')->defaultNull()->end()
                    ->end()
                ->end()
                ->scalarNode('current_participant')
                    ->info("The CurrentParticipant service naming who reads and writes; Symfony Security's user identifier when null, refused when it is an email address.")
                    ->defaultNull()
                ->end()
                ->scalarNode('directory')
                    ->info('The ParticipantDirectory service naming the participants on the pages; their identifiers when null.')
                    ->defaultNull()
                ->end()
                ->scalarNode('contacts')
                    ->info('The ContactRelation service saying who are contacts, for the members who receive messages from their contacts only; nobody when null.')
                    ->defaultNull()
                ->end()
                ->scalarNode('layout')
                    ->info('The Twig template the pages extend, with the blocks "title" and "main".')
                    ->cannotBeEmpty()
                    ->defaultValue('@PhilConversation/layout.html.twig')
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('limits')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('body_length')->info('How many characters a message holds at most.')->min(1)->max(1_000_000)->defaultValue(ConversationLimits::BODY_LENGTH)->end()
                        ->integerNode('subject_length')->info('How many characters a subject holds at most.')->min(1)->max(1000)->defaultValue(ConversationLimits::SUBJECT_LENGTH)->end()
                        ->integerNode('group_size')->info('How many participants a group holds at most, its author included.')->min(2)->max(1000)->defaultValue(ConversationLimits::GROUP_SIZE)->end()
                        ->integerNode('page_size')->info('How many messages or conversations a page shows.')->min(1)->max(200)->defaultValue(ConversationLimits::PAGE_SIZE)->end()
                    ->end()
                ->end()
                ->arrayNode('notifications')
                    ->info('The notification of each new message to its recipients, with phil/notifications-bundle registered.')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('type')->info('The notification type, declared to the notifications bundle.')->cannotBeEmpty()->defaultValue(NotifyParticipants::TYPE)->end()
                    ->end()
                ->end()
                ->arrayNode('realtime')
                    ->info('The Mercure update that tells the open pages a message came.')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('hub')->info('The service of the Mercure hub.')->cannotBeEmpty()->defaultValue(self::HUB)->end()
                        ->scalarNode('topic_prefix')->info("What comes before a participant's identifier in their topic.")->cannotBeEmpty()->defaultValue(MercureMessagePublisher::PREFIX)->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [ConversationRequest::TOKEN_ID]]]);
        }

        if ($builder->hasExtension('phil_notifications')) {
            $container->extension('phil_notifications', ['types' => [$this->notificationType($builder) => ['channels' => ['site', 'email'], 'collapse' => true]]]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/conversation-bundle']]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];

        /** @var Config $config */
        ConversationServices::register($builder, $config, \is_array($bundles) ? array_map(strval(...), array_keys($bundles)) : []);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new EncryptionPass());
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    /**
     * The notification type the application's configuration names, the last one given, or the default.
     */
    private function notificationType(ContainerBuilder $builder): string
    {
        $type = NotifyParticipants::TYPE;

        foreach ($builder->getExtensionConfig('phil_conversation') as $config) {
            $notifications = \is_array($config) ? ($config['notifications'] ?? null) : null;
            $named = \is_array($notifications) ? ($notifications['type'] ?? null) : null;

            if (\is_string($named)) {
                $type = $named;
            }
        }

        return $type;
    }
}
