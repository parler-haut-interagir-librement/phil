<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle;

use Override;
use Phil\HelpdeskBundle\Domain\Exception\InvalidQueue;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;
use Phil\HelpdeskBundle\Infrastructure\DependencyInjection\HelpdeskServices;
use Phil\HelpdeskBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\HelpdeskBundle\Infrastructure\Http\HelpdeskRequest;
use Phil\HelpdeskBundle\Infrastructure\Notifications\NotifyAssignee;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A help desk for an application, on phil/conversation-bundle: each request is a conversation, opened by its requester,
 * with a queue, a status that moves through the workflow `phil_helpdesk_ticket`, and an assignee; the agents of each
 * queue are named by role; canned replies are templates of the application. The requesters and the agents have their
 * pages, drawn with the recipes of the kit, the agents' grafted on the dashboards of EasyAdmin when it is registered;
 * the table is kept through DBAL. The notification type of an assignment is declared to phil/notifications-bundle
 * when it is installed, and the requests take part in the export and the erasure of phil/privacy-bundle.
 *
 * @phpstan-import-type Config from HelpdeskServices
 */
final class PhilHelpdeskBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_helpdesk';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')
                    ->info('The DBAL connection of the table.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->arrayNode('queues')
                    ->info('The queues, by name, such as "billing": the translation key of their label and the roles of their agents.')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->validate()->ifTrue($this->hasInvalidName(...))->thenInvalid('The name of a queue holds 1 to 64 lowercase letters, digits, dashes and underscores: %s.')->end()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->info('The translation key of the label, in the domain "phil_helpdesk".')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('roles')->info('The roles whose holders work the queue; any one of them is enough.')->isRequired()->requiresAtLeastOneElement()->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('queue_access')
                    ->info('The QueueAccess service saying who works a queue; the roles of the queues, through Symfony Security, when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('canned_replies')
                    ->info('The canned replies, by identifier: their label, the Twig template of their text, and the queues they are offered in, every queue when none.')
                    ->useAttributeAsKey('id')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->info('The label the page shows.')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('template')->info('The Twig template of the text, given "ticket" and "locale".')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('queues')->info('The queues the reply is offered in; every queue when empty.')->scalarPrototype()->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('canned_reply_source')
                    ->info('A CannedReplies service that replaces the canned replies of the configuration, such as an adapter to the content templates of the application.')
                    ->defaultNull()
                ->end()
                ->scalarNode('layout')
                    ->info('The Twig template the pages of the requesters extend, with the blocks "title" and "main".')
                    ->cannotBeEmpty()
                    ->defaultValue('@PhilHelpdesk/layout.html.twig')
                ->end()
                ->scalarNode('agent_layout')
                    ->info('The Twig template the pages of the agents extend outside EasyAdmin, with the blocks "title" and "main".')
                    ->cannotBeEmpty()
                    ->defaultValue('@PhilHelpdesk/layout.html.twig')
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('notifications')
                    ->info('The notification of an agent a request is assigned to, with phil/notifications-bundle registered.')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('type')->info('The notification type, declared to the notifications bundle.')->cannotBeEmpty()->defaultValue(NotifyAssignee::TYPE)->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [HelpdeskRequest::TOKEN_ID]]]);
        }

        if ($builder->hasExtension('phil_notifications')) {
            $container->extension('phil_notifications', ['types' => [$this->notificationType($builder) => ['channels' => ['site', 'email']]]]);
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
        HelpdeskServices::register($builder, $config, \is_array($bundles) ? array_map(strval(...), array_keys($bundles)) : []);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    /**
     * @param array<array-key, mixed> $queues
     */
    private function hasInvalidName(array $queues): bool
    {
        foreach (array_keys($queues) as $name) {
            try {
                QueueName::fromString((string) $name);
            } catch (InvalidQueue) {
                return true;
            }
        }

        return false;
    }

    /**
     * The notification type the application's configuration names, the last one given, or the default.
     */
    private function notificationType(ContainerBuilder $builder): string
    {
        $type = NotifyAssignee::TYPE;

        foreach ($builder->getExtensionConfig('phil_helpdesk') as $config) {
            $notifications = \is_array($config) ? ($config['notifications'] ?? null) : null;
            $named = \is_array($notifications) ? ($notifications['type'] ?? null) : null;

            if (\is_string($named)) {
                $type = $named;
            }
        }

        return $type;
    }
}
