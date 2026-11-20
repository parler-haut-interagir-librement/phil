<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle;

use Override;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\DurationInput;
use Phil\IncidentModeBundle\Infrastructure\Audit\AuditIncidents;
use Phil\IncidentModeBundle\Infrastructure\DependencyInjection\IncidentModePass;
use Phil\IncidentModeBundle\Infrastructure\DependencyInjection\IncidentModeServices;
use Phil\IncidentModeBundle\Infrastructure\Http\AdminRequest;
use Phil\IncidentModeBundle\Infrastructure\Http\IncidentAdminController;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The incident mode of phil/incident-mode in a Symfony application: its table on a DBAL connection of the
 * application's choosing, the presets of the configuration, the console commands, an administration page, the
 * automatic lifting by a delayed Messenger message and a recurring sweep of Symfony Scheduler, the public notice of
 * what is suspended and until when, and the checkpoints of the controllers, the routes and the forms — established
 * members spared through the port `EstablishedMembers` —; the incidents written to phil/audit-bundle and answered to
 * phil/privacy-bundle when they are registered.
 *
 * ```yaml
 * phil_incident_mode:
 *     spare_established: [new-posts, new-comments]
 *     presets:
 *         suspend-listings: { label: 'Suspend new listings', restrictions: [new-listings], proposed: 4h, longest: 2d }
 * ```
 *
 * @phpstan-import-type Config from IncidentModeServices
 */
final class PhilIncidentModeBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_incident_mode';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $children
            ->scalarNode('connection')->info('The DBAL connection of the table phil_incident_activation.')->cannotBeEmpty()->defaultValue('default')->end()
            ->booleanNode('built_in_presets')->info('Whether the five built-in presets are offered, before the presets below; when false, only the presets below are.')->defaultTrue()->end()
            ->arrayNode('without_presets')->info('The built-in presets left out, by name.')->scalarPrototype()->cannotBeEmpty()->end()->end()
            ->arrayNode('spare_established')->info('The restrictions an incident leaves established members free from, as the port EstablishedMembers names them; none when empty.')->scalarPrototype()->cannotBeEmpty()->end()->end()
            ->scalarNode('established_members')->info('The EstablishedMembers service that says who is an established member; nobody is when null.')->defaultNull()->end()
            ->scalarNode('current_member')->info("The CurrentMember service that names the member of the request; Symfony Security's user identifier when null.")->defaultNull()->end()
            ->scalarNode('layout')->info('The Twig template the pages extend, with the blocks "title" and "main".')->cannotBeEmpty()->defaultValue(IncidentModeServices::DEFAULT_LAYOUT)->end()
            ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')->defaultNull()->end()
            ->scalarNode('timezone')->info('The time zone the ends are written in for the visitors; the default time zone of PHP when null.')->defaultNull()
                ->validate()->ifTrue(static fn (mixed $zone): bool => null !== $zone && !\in_array($zone, timezone_identifiers_list(), true))->thenInvalid('%s is not a time zone.')->end()
            ->end();

        $this->presets($children);
        $this->checkpoints($children);
        $this->admin($children);
        $this->lifting($children);

        $children
            ->arrayNode('audit')
                ->info('The incidents written to the trail of phil/audit-bundle, when it is registered.')
                ->addDefaultsIfNotSet()
                ->canBeDisabled()
                ->children()
                    ->scalarNode('segment')->info('The segment of the trail.')->cannotBeEmpty()->defaultValue(AuditIncidents::DEFAULT_SEGMENT)->end()
                ->end()
            ->end()
            ->booleanNode('privacy')->info('Whether the incidents a member started or lifted answer the access and erasure requests of phil/privacy-bundle, when it is registered.')->defaultTrue()->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [AdminRequest::TOKEN_ID]]]);
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
        IncidentModeServices::register($builder, $config, \is_array($bundles) ? array_keys($bundles) : []);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new IncidentModePass());
    }

    private function presets(NodeBuilder $children): void
    {
        $refused = static function (mixed $text): bool {
            try {
                DurationInput::parse(\is_scalar($text) ? (string) $text : '');
            } catch (InvalidIncident) {
                return true;
            }

            return false;
        };

        $children
            ->arrayNode('presets')
                ->info("The application's presets, by name, after the built-in ones; one of the same name replaces the built-in preset in its place.")
                ->normalizeKeys(false)
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('label')->info('The label of the preset, in the language of the administration.')->isRequired()->cannotBeEmpty()->end()
                        ->arrayNode('restrictions')->info("The restrictions it suspends, at least one: built-in names or the application's own.")->isRequired()->requiresAtLeastOneElement()->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->scalarNode('proposed')->info('The duration an incident proposes, such as 90m, 2h or 1d.')->isRequired()
                            ->beforeNormalization()->ifTrue(is_int(...))->then(strval(...))->end()
                            ->validate()->ifTrue($refused)->thenInvalid('%s is not a duration such as 90m, 2h or 1d, from one minute to ninety days.')->end()
                        ->end()
                        ->scalarNode('longest')->info('The longest an incident of the preset may last, extensions included.')->isRequired()
                            ->beforeNormalization()->ifTrue(is_int(...))->then(strval(...))->end()
                            ->validate()->ifTrue($refused)->thenInvalid('%s is not a duration such as 90m, 2h or 1d, from one minute to ninety days.')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function checkpoints(NodeBuilder $children): void
    {
        $children
            ->arrayNode('checkpoints')
                ->info('The refusal of a controller, a route or a form while an incident suspends what it does.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('status')->info('The HTTP status of a refusal, always with a Retry-After header.')->values([503, 429, 423, 403])->defaultValue(Checkpoint::DEFAULT_STATUS)->end()
                ->end()
            ->end();
    }

    private function admin(NodeBuilder $children): void
    {
        $children
            ->arrayNode('admin')
                ->info('The administration page, mounted from the routes of the bundle behind the firewall.')
                ->addDefaultsIfNotSet()
                ->canBeDisabled()
                ->children()
                    ->scalarNode('role')->info('The role that may start, extend and lift an incident.')->cannotBeEmpty()->defaultValue('ROLE_ADMIN')->end()
                    ->integerNode('page_size')->info('The incidents of the journal on one page.')->min(1)->max(200)->defaultValue(IncidentAdminController::DEFAULT_PAGE_SIZE)->end()
                ->end()
            ->end();
    }

    private function lifting(NodeBuilder $children): void
    {
        $children
            ->arrayNode('lifting')
                ->info('The automatic lifting at the end: a delayed Messenger message for each incident, and a recurring sweep of Symfony Scheduler for the messages a transport lost.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('messenger')->info('Whether each incident sends its delayed message, when the bus exists; route Phil\IncidentMode\Messenger\LiftIncident to a transport that supports delays.')->defaultTrue()->end()
                    ->scalarNode('bus')->info('The message bus of the delayed messages.')->cannotBeEmpty()->defaultValue('messenger.default_bus')->end()
                    ->arrayNode('schedule')
                        ->info('The recurring sweep, a schedule of Symfony Scheduler consumed from the transport "scheduler_<name>".')
                        ->addDefaultsIfNotSet()
                        ->canBeDisabled()
                        ->children()
                            ->scalarNode('name')->info('The name of the schedule.')->cannotBeEmpty()->defaultValue(IncidentModeServices::DEFAULT_SCHEDULE_NAME)->end()
                            ->scalarNode('frequency')->info('How often the sweep runs, as RecurringMessage::every() reads it.')->cannotBeEmpty()->defaultValue(IncidentModeServices::DEFAULT_SCHEDULE_FREQUENCY)->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
