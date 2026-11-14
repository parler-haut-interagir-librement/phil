<?php

declare(strict_types=1);

namespace Phil\MentionsBundle;

use Override;
use Phil\BlockEditorBundle\Application\Reference\ReferenceSearch;
use Phil\MentionsBundle\Application\MentionLimits;
use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use Phil\MentionsBundle\Infrastructure\BlockEditor\MemberReferenceSearch;
use Phil\MentionsBundle\Infrastructure\DependencyInjection\MentionsPass;
use Phil\MentionsBundle\Infrastructure\DependencyInjection\MentionsServices;
use Phil\MentionsBundle\Infrastructure\Notifications\NotifyMentionedMember;
use Phil\MentionsBundle\Infrastructure\Settings\SettingsMentionPreferences;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Mentions of members, for an application: the configuration names its member directory and sets the handles'
 * pattern, the limits and where mentions are kept. The table is mapped for Doctrine; the notification type is declared
 * to the notifications bundle — on the centre, email, and Web Push when the notifications bundle sends it —, the
 * members' preference to the settings bundle and the `@` search to the block editor, each when it is enabled; with
 * AssetMapper the controller and its stylesheet are served without a build step.
 */
final class PhilMentionsBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    #[Override]
    protected string $extensionAlias = 'phil_mentions';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where mentions are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the table.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('member_directory')
                    ->info('The service, a MemberDirectory, that finds the members of the application.')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('pattern')
                    ->info('What a handle looks like after its "@", a regular expression without delimiters nor anchors.')
                    ->cannotBeEmpty()
                    ->defaultValue(MentionPattern::DEFAULT)
                    ->validate()->ifTrue($this->isInvalidPattern(...))->thenInvalid('The pattern of a handle %s is refused: see MentionPattern.')->end()
                ->end()
                ->arrayNode('limits')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('per_text')->info('How many members a text mentions at most.')->min(1)->max(1000)->defaultValue(MentionLimits::PER_TEXT)->end()
                        ->integerNode('text_length')->info('How many characters a text read for mentions holds at most.')->min(1)->max(10_000_000)->defaultValue(MentionLimits::TEXT_LENGTH)->end()
                    ->end()
                ->end()
                ->scalarNode('notification_type')
                    ->info('The notification type of a mention, declared to the notifications bundle.')
                    ->cannotBeEmpty()
                    ->defaultValue(NotifyMentionedMember::TYPE)
                ->end()
                ->scalarNode('preferences')
                    ->info('The service, a MentionPreferences, that says who wants to be notified; the setting of the settings bundle, else everybody, when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('comments')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('markdown')->info('Whether comments are Markdown, whose code is not read for mentions.')->defaultFalse()->end()
                        ->booleanNode('record')->info('Whether the mentions of each comment written, edited or deleted are recorded by the bundle, with the comments bundle installed.')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilMentionsBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\MentionsBundle\Domain\Model',
            ]]]]);
        }

        if ($builder->hasExtension('phil_notifications')) {
            $container->extension('phil_notifications', ['types' => [$this->notificationType($builder) => ['channels' => $this->channels($builder)]]]);
        }

        if ($builder->hasExtension('phil_settings')) {
            $container->extension('phil_settings', ['settings' => [SettingsMentionPreferences::SETTING => [
                'type' => 'boolean',
                'default' => true,
                'section' => 'notifications',
                'translation_domain' => 'phil_mentions',
                'after' => ['notifications.web_push'],
            ]]]);
        }

        if ($builder->hasExtension('phil_block_editor') && interface_exists(ReferenceSearch::class)) {
            $container->extension('phil_block_editor', ['mention_search' => MemberReferenceSearch::class]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [
                self::CONTROLLERS_DIR => '@phil/mentions-bundle',
                self::STYLES_DIR => 'phil-mentions',
            ]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, entity_manager: string, member_directory: string, pattern: string, limits: array{per_text: int, text_length: int}, notification_type: string, preferences: ?string, comments: array{markdown: bool, record: bool}} $config */
        MentionsServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new MentionsPass());
    }

    private function isInvalidPattern(mixed $pattern): bool
    {
        try {
            MentionPattern::of(\is_string($pattern) ? $pattern : '');
        } catch (InvalidMention) {
            return true;
        }

        return false;
    }

    /**
     * The notification type the application's configuration names, the last one given, or the default.
     */
    /**
     * The channels of the type: the centre and email, and Web Push when the notifications bundle's own is enabled, so
     * that the members who turned it on in a browser get their mentions there.
     *
     * @return list<string>
     */
    private function channels(ContainerBuilder $builder): array
    {
        $webPush = false;

        foreach ($builder->getExtensionConfig('phil_notifications') as $config) {
            if (!\is_array($config) || !\array_key_exists('web_push', $config)) {
                continue;
            }

            $option = $config['web_push'];
            // As `canBeEnabled()` reads it: `true`, `null`, or an array that does not say `enabled: false`.
            $webPush = true === $option || null === $option || (\is_array($option) && false !== ($option['enabled'] ?? true));
        }

        return $webPush ? ['site', 'email', 'web_push'] : ['site', 'email'];
    }

    private function notificationType(ContainerBuilder $builder): string
    {
        $type = NotifyMentionedMember::TYPE;

        foreach ($builder->getExtensionConfig('phil_mentions') as $config) {
            if (\is_array($config) && \is_string($config['notification_type'] ?? null)) {
                $type = $config['notification_type'];
            }
        }

        return $type;
    }
}
