<?php

declare(strict_types=1);

namespace Phil\SurveyBundle;

use Override;
use Phil\SurveyBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\SurveyBundle\Infrastructure\DependencyInjection\RespondentPass;
use Phil\SurveyBundle\Infrastructure\DependencyInjection\SurveyServices;
use Phil\SurveyBundle\Infrastructure\Http\SurveyGuard;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Questionnaires with sections and conditional logic, on phil/forms-bundle, phil/form-logic and phil/poll: ranking and
 * matrix questions, endings and profiles chosen by the logic, redirects to allowed origins only, cross quotas kept by
 * atomic counters, anonymous responses by default, results withheld below a minimum, a retention per questionnaire,
 * templates and a JSON exchange format.
 *
 * ```yaml
 * phil_survey:
 *     redirect_origins: ['https://example.org']
 * ```
 */
final class PhilSurveyBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_survey';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new RespondentPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')->info('The Doctrine connection of the tables.')->cannotBeEmpty()->defaultValue('default')->end()
                ->scalarNode('secret')->info('The key of the digests that mark participations, 16 bytes or more.')->cannotBeEmpty()->defaultValue('%kernel.secret%')->end()
                ->arrayNode('redirect_origins')->info('The origins an ending may redirect to, such as "https://example.org"; none by default.')->scalarPrototype()->end()->end()
                ->booleanNode('allow_localhost')->info('Whether "localhost" is a host of a redirect, for development and tests.')->defaultFalse()->end()
                ->scalarNode('respondent')->info('The service giving the signed-in member, a CurrentSubject of phil/forms-bundle; the member of Symfony Security when null.')->defaultNull()->end()
                ->scalarNode('sessions')->info('The FeedbackSessions service that gives the start and end of each session; no session open when null.')->defaultNull()->end()
                ->booleanNode('pages')->info('Whether the bundle registers its pages, with Twig.')->defaultTrue()->end()
                ->arrayNode('schedule')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Whether the schedule "phil_survey" purges the responses past their retention every day, with Symfony Scheduler.')->defaultTrue()->end()
                        ->scalarNode('at')->info('The time of the purge, HH:MM.')->defaultValue('03:45')->end()
                        ->scalarNode('timezone')->defaultValue('UTC')->end()
                        ->scalarNode('lock_factory')->info('The lock factory that keeps the schedule to one worker; used when it exists, none when null.')->defaultValue('lock.factory')->end()
                        ->scalarNode('state_pool')->info('The cache pool that keeps the last run of the schedule; used when it exists, none when null.')->defaultValue('cache.app')->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [SurveyGuard::TOKEN_ID]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{connection: string, secret: string, redirect_origins: list<string>, allow_localhost: bool, respondent: ?string, sessions: ?string, pages: bool, schedule: array{enabled: bool, at: string, timezone: string, lock_factory: ?string, state_pool: ?string}} $config */
        SurveyServices::register($builder, $config);
    }
}
