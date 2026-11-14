<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle;

use Override;
use Phil\OnboardingBundle\Domain\Flow\Completion;
use Phil\OnboardingBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\OnboardingBundle\Infrastructure\DependencyInjection\OnboardingPass;
use Phil\OnboardingBundle\Infrastructure\DependencyInjection\OnboardingServices;
use Phil\OnboardingBundle\Infrastructure\Http\OnboardingRequest;
use Phil\OnboardingBundle\Infrastructure\Settings\SettingsTourPreference;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The welcome of a new member: onboarding flows of ordered steps, declared by configuration or attribute, each
 * completed by hand, by a Symfony event or by a checker; the progress of each user kept under their identifier; a
 * checklist and a guided tour. The table is mapped for Doctrine, the endpoints' CSRF token added to the stateless ones
 * of FrameworkBundle, the setting that turns the tours off declared to phil/settings-bundle when it is installed, and
 * with AssetMapper the Stimulus controller and its stylesheet are served without a build step. The templates draw their
 * buttons with the recipe `Button` of the kit, installed by the Symfony UX Toolkit.
 *
 * @phpstan-import-type Config from OnboardingServices
 */
final class PhilOnboardingBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    #[Override]
    protected string $extensionAlias = 'phil_onboarding';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the progress is kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the table.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('current_user')
                    ->info("The CurrentUser service naming whose progress a request shows; Symfony Security's user by default.")
                    ->defaultNull()
                ->end()
                ->scalarNode('answer_grants')
                    ->info('The AnswerGrants service granting the roles and subscriptions of the answers; required when a choice grants any.')
                    ->defaultNull()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
                ->scalarNode('tour_preference')
                    ->info('The TourPreference service saying who wants the tours; the setting of phil/settings-bundle when it is installed, everyone otherwise.')
                    ->defaultNull()
                ->end()
                ->arrayNode('flows')
                    ->info('The flows, by identifier, such as "author".')
                    ->useAttributeAsKey('id')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('title')->info('The translation key of the title.')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('description')->info('The translation key of the description.')->defaultNull()->end()
                            ->scalarNode('translation_domain')->defaultValue('messages')->end()
                            ->arrayNode('steps')
                                ->info('The steps, by identifier, in their order.')
                                ->useAttributeAsKey('id')
                                ->normalizeKeys(false)
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('title')->info('The translation key of the title.')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('description')->info('The translation key of the description.')->defaultNull()->end()
                                        ->scalarNode('route')->info('The route the step leads to.')->defaultNull()->end()
                                        ->arrayNode('route_parameters')->useAttributeAsKey('name')->scalarPrototype()->end()->end()
                                        ->scalarNode('target')->info('The CSS selector of the element the tour highlights.')->defaultNull()->end()
                                        ->enumNode('completion')
                                            ->info('How the step gets done: ticked by hand, on an event, or by a checker.')
                                            ->values(array_map(static fn (Completion $completion): string => $completion->value, Completion::cases()))
                                            ->defaultValue(Completion::Manual->value)
                                        ->end()
                                        ->scalarNode('event')->info('The name of the event that completes the step, usually its class.')->defaultNull()->end()
                                        ->scalarNode('checker')->info('The StepChecker service that says the step is done.')->defaultNull()->end()
                                        ->booleanNode('skippable')->info('Whether a person may skip the step.')->defaultTrue()->end()
                                        ->arrayNode('before')->info('The steps this one comes before.')->scalarPrototype()->end()->end()
                                        ->arrayNode('after')->info('The steps this one comes after.')->scalarPrototype()->end()->end()
                                        ->arrayNode('question')
                                            ->info('The question of a step done by an answer: its choices, by identifier, and whether several may be picked.')
                                            ->children()
                                                ->booleanNode('multiple')->info('Whether the person may pick several choices.')->defaultFalse()->end()
                                                ->arrayNode('choices')
                                                    ->info('Two to twenty choices, by identifier, in their order.')
                                                    ->isRequired()
                                                    ->useAttributeAsKey('id')
                                                    ->normalizeKeys(false)
                                                    ->arrayPrototype()
                                                        ->children()
                                                            ->scalarNode('label')->info('The translation key of the label.')->isRequired()->cannotBeEmpty()->end()
                                                            ->arrayNode('roles')->info('The roles the application grants, through AnswerGrants.')->scalarPrototype()->end()->end()
                                                            ->arrayNode('subscriptions')->info('The subscriptions the application grants, through AnswerGrants.')->scalarPrototype()->end()->end()
                                                        ->end()
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilOnboardingBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\OnboardingBundle\Domain\Model',
            ]]]]);
        }

        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [OnboardingRequest::TOKEN_ID]]]);

            if (interface_exists(AssetMapperInterface::class)) {
                $container->extension('framework', ['asset_mapper' => ['paths' => [
                    self::CONTROLLERS_DIR => '@phil/onboarding-bundle',
                    self::STYLES_DIR => 'phil-onboarding',
                ]]]);
            }
        }

        if ($builder->hasExtension('phil_settings')) {
            $container->extension('phil_settings', ['settings' => [SettingsTourPreference::KEY => [
                'type' => 'boolean',
                'default' => true,
                'section' => 'general',
                'help' => SettingsTourPreference::KEY.'.help',
                'translation_domain' => 'phil_onboarding',
            ]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        OnboardingServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new OnboardingPass());
        $container->addCompilerPass(new KitSubdirectoryPass());
    }
}
