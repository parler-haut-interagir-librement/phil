<?php

declare(strict_types=1);

namespace Phil\AuditViewer;

use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Override;
use Phil\AuditBundle\Application\AuditLog;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\ChainStatus;
use Phil\AuditBundle\Application\Contract\ActorResolver;
use Phil\AuditBundle\Application\Contract\PayloadReader;
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditHistoryActions;
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditViewerController;
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The audit log of `phil/audit-bundle` read in an EasyAdmin back office: the log with its filters, the history of a
 * record, the state of the hash chain and a CSV export that is itself recorded, behind a role of their own.
 *
 * ```yaml
 * phil_audit_viewer:
 *     role: ROLE_AUDIT_VIEWER          # reads the log
 *     export_role: ROLE_AUDIT_EXPORT   # exports it; the viewer's role when null
 *     history_role: ROLE_ADMIN         # reads the history of one record, besides the viewer's role
 *     access_policy: App\Audit\Policy  # an AuditAccessPolicy that narrows the roles, record by record
 *     history_action: true             # a "History" action on the CRUD pages of Auditable entities
 *     export_segment: audit            # the segment each export is recorded in
 *     export_limit: 100000             # the most rows one export reads and writes
 *     page_size: 50
 *     timezone: Europe/Paris           # the time zone instants are shown and filtered in; PHP's default when null
 * ```
 */
final class PhilAuditViewerBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_audit_viewer';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('role')
                    ->info('The role that reads the audit log.')
                    ->defaultValue('ROLE_AUDIT_VIEWER')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('export_role')
                    ->info("The role that exports it as CSV; null, the viewer's role.")
                    ->defaultNull()
                ->end()
                ->scalarNode('history_role')
                    ->info("A role that reads the history of one record, besides the viewer's role; null, the viewer's role only.")
                    ->defaultNull()
                ->end()
                ->scalarNode('access_policy')
                    ->info('The id of an AuditAccessPolicy service the voter asks once a role holds: it narrows the roles, never widens them; null, the roles decide.')
                    ->defaultNull()
                ->end()
                ->booleanNode('history_action')
                    ->info('Whether the CRUD pages of entities that implement Auditable get a "History" action to their history page.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('export_segment')
                    ->info('The segment of the trail each export is recorded in.')
                    ->defaultValue('audit')
                    ->validate()
                        ->ifTrue(static fn (mixed $segment): bool => !\is_string($segment) || 1 !== preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $segment))
                        ->thenInvalid('A segment is 1 to 64 lower-case letters, digits, ".", "_" or "-"; %s is not.')
                    ->end()
                ->end()
                ->integerNode('export_limit')
                    ->info('The most rows one export reads, and so writes.')
                    ->defaultValue(100000)
                    ->min(1)
                ->end()
                ->integerNode('page_size')
                    ->info('The rows of a page of the log or of a history.')
                    ->defaultValue(50)
                    ->min(1)
                    ->max(500)
                ->end()
                ->scalarNode('timezone')
                    ->info("The time zone instants are shown and filtered in; null, PHP's default.")
                    ->defaultNull()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{role: string, export_role: ?string, history_role: ?string, access_policy: ?string, history_action: bool, export_segment: string, export_limit: int, page_size: int, timezone: ?string} $config */
        $services = $container->services();

        $services->set(AuditViewerVoter::class)
            ->args([
                new Reference('security.access.decision_manager'),
                $config['role'],
                $config['export_role'] ?? $config['role'],
                $config['history_role'],
                null === $config['access_policy'] ? null : new Reference($config['access_policy']),
            ])
            ->tag('security.voter');

        if ($config['history_action']) {
            $services->set(AuditHistoryActions::class)
                ->args([new Reference('router'), new Reference('security.authorization_checker')])
                ->tag(EasyAdminBundle::TAG_ACTIONS_EXTENSION);
        }

        $services->set(AuditViewerController::class)
            ->args([
                new Reference('twig'),
                new Reference(AuditLog::class),
                new Reference(ChainStatus::class),
                new Reference('security.authorization_checker'),
                new Reference('security.csrf.token_manager'),
                new Reference('clock'),
                $config['timezone'] ?? date_default_timezone_get(),
                $config['page_size'],
                $config['export_segment'],
                $config['export_limit'],
                $this->optional(AuditTrail::class),
                $this->optional(ActorResolver::class),
                $this->optional(PayloadReader::class),
            ])
            ->tag('controller.service_arguments')
            ->tag(EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER)
            ->public();
    }

    private function optional(string $id): Reference
    {
        return new Reference($id, ContainerInterface::NULL_ON_INVALID_REFERENCE);
    }
}
