<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle;

use DateTimeZone;
use Override;
use Phil\ShiftsBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\ShiftsBundle\Infrastructure\DependencyInjection\ShiftsServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Volunteer shifts on phil/shifts in a Symfony application: the shifts, their rosters and the swap board stored
 * through Doctrine DBAL, the volunteers' and the coordinators' pages in Twig with the form of a shift, the components
 * `PhilShifts:ShiftCard` and `PhilShifts:MyShifts`, the allocation through Messenger, voters for volunteering and
 * coordinating, the import of a plan, the guardians of volunteers who are minors, the unannounced absences, and the
 * volunteers in the export and erasure of phil/privacy-bundle.
 *
 * ```yaml
 * phil_shifts:
 *     time_zone: America/Toronto
 *     rest_minutes: 30
 *     roles: { volunteer: ROLE_VOLUNTEER, coordinator: ROLE_COORDINATOR }
 *     minors: { birth_dates: App\Volunteer\BirthDates }
 *     absences: { threshold: 3 }
 * ```
 */
final class PhilShiftsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_shifts';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')->info('The Doctrine connection of the tables.')->cannotBeEmpty()->defaultValue('default')->end()
                ->scalarNode('person')->info('The service giving the person signed in and their roles, a CurrentPerson; the user of Symfony Security when null.')->defaultNull()->end()
                ->scalarNode('allocator')->info('The service allocating the places among the interested, an InterestAllocator; the FairAllocator when null.')->defaultNull()->end()
                ->booleanNode('pages')->info('Whether the bundle registers its pages and components, with Twig.')->defaultTrue()->end()
                ->scalarNode('time_zone')
                    ->info('The time zone the pages show the shifts in and the form reads them in.')
                    ->defaultValue('UTC')
                    ->validate()
                        ->ifTrue(static fn (mixed $zone): bool => !\is_string($zone) || !\in_array($zone, DateTimeZone::listIdentifiers(), true) && 'UTC' !== $zone)
                        ->thenInvalid('The time zone %s is not an identifier of the IANA database, such as America/Toronto.')
                    ->end()
                ->end()
                ->integerNode('rest_minutes')->info('The minutes a volunteer rests between two fixed shifts they hold, 0 to 1440.')->min(0)->max(1440)->defaultValue(0)->end()
                ->arrayNode('roles')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('volunteer')->info('The role a person needs to volunteer; any person signed in when null.')->defaultNull()->end()
                        ->scalarNode('coordinator')->info('The role of the coordinators, who plan shifts, assign volunteers and approve swaps.')->cannotBeEmpty()->defaultValue('ROLE_SHIFTS_COORDINATOR')->end()
                    ->end()
                ->end()
                ->arrayNode('minors')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('birth_dates')->info('The service giving the birth dates of the volunteers, a BirthDates; nobody is a minor when null.')->defaultNull()->end()
                        ->integerNode('age_of_majority')->info('The age from which a volunteer joins a shift without the consent of a guardian, 1 to 30.')->min(1)->max(30)->defaultValue(18)->end()
                    ->end()
                ->end()
                ->arrayNode('absences')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('threshold')->info('The unannounced absences from which a volunteer no longer joins a shift by themselves, 1 to 100; never when null.')->min(1)->max(100)->defaultNull()->end()
                        ->integerNode('window_days')->info('The days over which the absences count, 1 to 3650.')->min(1)->max(3650)->defaultValue(365)->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{connection: string, person: ?string, allocator: ?string, pages: bool, time_zone: string, rest_minutes: int, roles: array{volunteer: ?string, coordinator: string}, minors: array{birth_dates: ?string, age_of_majority: int}, absences: array{threshold: ?int, window_days: int}} $config */
        ShiftsServices::register($builder, $config);
    }
}
