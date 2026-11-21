<?php

declare(strict_types=1);

namespace Phil\AiBundle;

use Override;
use Phil\AiBundle\Application\KeyPolicy;
use Phil\AiBundle\Application\PromptTemplates;
use Phil\AiBundle\Application\Quota;
use Phil\AiBundle\Application\TaskDefinition;
use Phil\AiBundle\Application\ToolDefinition;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Infrastructure\DependencyInjection\AiServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Language-model tasks for an application, on Symfony AI:
 *
 * ```yaml
 * phil_ai:
 *     platforms:
 *         main: { platform: 'ai.platform.main' }
 *     tasks:
 *         draft.rewrite: { platform: main, model: 'the-model', options: { max_tokens: 1024 } }
 * ```
 *
 * The configuration names the platforms — Symfony AI services, with the factory that builds one on a person's own
 * key and the name of the secret that holds it —, the prices of the models, the tasks — what each asks for, its tools
 * and its purpose —, whose key a task runs on, the quota, budget and ceiling meters of `phil/usage-meter` and the
 * bound of tasks running at once, the
 * translation domain of the prompts and where the journal is kept. The table is mapped for Doctrine.
 */
final class PhilAiBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_ai';

    /**
     * Whether `phil/user-secrets-bundle` is registered: each extension loads in a container of its own, so the
     * container that prepends the configurations tells it, before the loading.
     */
    private bool $userSecrets = false;

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()
                ->ifTrue(static fn (array $config): bool => self::taskOnUnknownPlatform($config))
                ->thenInvalid('Every task runs on a platform of phil_ai.platforms: %s')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => self::meterWithoutUsageMeter($config))
                ->thenInvalid('A quota meter needs phil_ai.usage_meter, the service of phil/usage-meter that counts it: %s')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => self::concurrencyWithoutUsageMeter($config))
                ->thenInvalid('phil_ai.quota.concurrency belongs to the quota, which needs phil_ai.usage_meter: %s')
            ->end()
            ->children()
                ->arrayNode('platforms')
                    ->info('The Symfony AI platforms, by name.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->validate()
                        ->ifTrue(static fn (array $platforms): bool => [] !== array_filter(array_keys($platforms), static fn (int|string $name): bool => 1 !== preg_match(TaskDefinition::PLATFORM_PATTERN, \sprintf('%s', $name))))
                        ->thenInvalid('A platform name is up to 64 lowercase letters, digits, "_" or "-", starting with a letter: %s')
                    ->end()
                    ->arrayPrototype()
                        ->validate()
                            ->ifTrue(static fn (array $platform): bool => null !== $platform['secret'] && null === $platform['user_key_factory'])
                            ->thenInvalid("A platform that reads a person's secret needs a user_key_factory to build the platform on it: %s")
                        ->end()
                        ->children()
                            ->scalarNode('platform')
                                ->info("The service of the platform on the application's key, a Symfony AI PlatformInterface.")
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('user_key_factory')
                                ->info("The service, a UserKeyPlatformFactory, that builds the platform on a person's own key.")
                                ->defaultNull()
                            ->end()
                            ->scalarNode('secret')
                                ->info("The name of the person's secret in phil/user-secrets-bundle that holds their key for this platform.")
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('currency')
                    ->info('The ISO 4217 code of the prices and of the costs in the journal.')
                    ->cannotBeEmpty()
                    ->defaultValue('USD')
                ->end()
                ->arrayNode('prices')
                    ->info('What each model charges, in micros of the currency per million tokens: 3.00 is 3000000.')
                    ->useAttributeAsKey('model')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('input')->isRequired()->min(0)->end()
                            ->integerNode('output')->isRequired()->min(0)->end()
                            ->integerNode('per_call')
                                ->info('What each call costs whatever its tokens, in micros: an image, a request.')
                                ->min(0)
                                ->defaultValue(0)
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tasks')
                    ->info('The tasks, by kind: the stem of their prompts in the translation domain.')
                    ->useAttributeAsKey('kind')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('platform')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('model')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('options')
                                ->info('Passed as they are to the platform: max_tokens, temperature…')
                                ->normalizeKeys(false)
                                ->variablePrototype()->end()
                            ->end()
                            ->integerNode('reserve')
                                ->info("How many tokens the task reserves on the subject's quota before it starts.")
                                ->min(1)
                                ->defaultValue(1)
                            ->end()
                            ->enumNode('output')
                                ->info('What the task asks for: "text", "structured", "image" or "speech".')
                                ->values(array_column(Output::cases(), 'value'))
                                ->defaultValue(Output::Text->value)
                            ->end()
                            ->scalarNode('structure')
                                ->info('The class a structured output is read into, for output "structured".')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('tools')
                                ->info('The tools the model may propose to call, by name; the bundle never runs them.')
                                ->useAttributeAsKey('name')
                                ->normalizeKeys(false)
                                ->validate()
                                    ->ifTrue(static fn (array $tools): bool => [] !== array_filter(array_keys($tools), static fn (int|string $name): bool => 1 !== preg_match(ToolDefinition::NAME_PATTERN, \sprintf('%s', $name))))
                                    ->thenInvalid('A tool name is 1 to 64 letters, digits, "_" or "-": %s')
                                ->end()
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('description')->isRequired()->cannotBeEmpty()->end()
                                        ->variableNode('parameters')
                                            ->info('The JSON schema of the arguments, an object schema.')
                                            ->defaultNull()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('purpose')
                                ->info('What the task is for, in a sentence, for the usage register.')
                                ->defaultValue('')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->enumNode('key_policy')
                    ->info('Whose key a task of a person runs on: "application", "prefer_user" or "require_user".')
                    ->values(array_column(KeyPolicy::cases(), 'value'))
                    ->defaultValue(KeyPolicy::PreferUser->value)
                ->end()
                ->scalarNode('usage_meter')
                    ->info('The phil/usage-meter service that counts the quotas.')
                    ->defaultNull()
                ->end()
                ->arrayNode('quota')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('tokens_meter')
                            ->info("The meter of tokens: each task's reserve consumed before it starts, settled with the tokens spent.")
                            ->defaultNull()
                        ->end()
                        ->scalarNode('tasks_meter')
                            ->info('The meter of tasks: one consumed per task, before it starts.')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('count_user_keys')
                            ->info("Whether tasks run on a person's own key count too.")
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('budget_meter')
                            ->info("The meter of the subject's spending, in micros of the currency: each task's cost bound consumed before it starts, settled with its cost.")
                            ->defaultNull()
                        ->end()
                        ->scalarNode('ceiling_meter')
                            ->info("The meter of the whole application's spending, in micros of the currency, counted on ceiling_subject like the budget.")
                            ->defaultNull()
                        ->end()
                        ->scalarNode('ceiling_subject')
                            ->info('The subject the ceiling is counted on.')
                            ->cannotBeEmpty()
                            ->defaultValue(Quota::APPLICATION_SUBJECT)
                        ->end()
                        ->integerNode('concurrency')
                            ->info('How many tasks of one subject run at once, through symfony/semaphore; null for no bound.')
                            ->min(1)
                            ->defaultNull()
                        ->end()
                        ->scalarNode('semaphore_factory')
                            ->info('The symfony/semaphore factory of that bound.')
                            ->cannotBeEmpty()
                            ->defaultValue(Quota::SEMAPHORE_FACTORY)
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('translation_domain')
                    ->info('The translation domain of the prompts.')
                    ->cannotBeEmpty()
                    ->defaultValue(PromptTemplates::DEFAULT_DOMAIN)
                ->end()
                ->enumNode('store')
                    ->info('Where the journal is kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the journal.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $this->userSecrets = $builder->hasExtension('phil_user_secrets');

        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilAiBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\AiBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (KeyPolicy::RequireUser->value === $config['key_policy'] && !$this->userSecrets) {
            throw new InvalidConfigurationException('phil_ai.key_policy "require_user" needs phil/user-secrets-bundle, which keeps the people\'s keys.');
        }

        AiServices::register($builder, $config, $this->userSecrets);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function taskOnUnknownPlatform(array $config): bool
    {
        $platforms = \is_array($config['platforms']) ? $config['platforms'] : [];

        foreach (\is_array($config['tasks']) ? $config['tasks'] : [] as $task) {
            $platform = \is_array($task) ? $task['platform'] : null;

            if (!\is_string($platform) || !\array_key_exists($platform, $platforms)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function meterWithoutUsageMeter(array $config): bool
    {
        $quota = \is_array($config['quota']) ? $config['quota'] : [];

        return null === $config['usage_meter'] && (null !== $quota['tokens_meter'] || null !== $quota['tasks_meter'] || null !== $quota['budget_meter'] || null !== $quota['ceiling_meter']);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function concurrencyWithoutUsageMeter(array $config): bool
    {
        $quota = \is_array($config['quota']) ? $config['quota'] : [];

        return null === $config['usage_meter'] && null !== $quota['concurrency'];
    }
}
