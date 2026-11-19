<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle;

use Override;
use Phil\ReactionsBundle\Application\ReactionsPerMember;
use Phil\ReactionsBundle\Domain\Status\Audience;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\ReactionsBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\ReactionsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\ReactionsBundle\Infrastructure\DependencyInjection\ReactionsServices;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionGuard;
use Phil\ReactionsBundle\Infrastructure\Settings\SettingsVisibility;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Reactions to any content as a Live Component: one per member and per content, or as many as the site allows, from a
 * list and with a visibility each site sets, kept in Doctrine, limited per member, and working without JavaScript.
 */
final class PhilReactionsBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    /** The limiter the bundle declares; an application changes it by declaring the same name in `framework.rate_limiter`. */
    public const string LIMITER = 'phil_reactions';

    private const array DEFAULT_REACTIONS = [
        ['code' => 'like', 'emoji' => '👍', 'label' => 'reaction.like'],
        ['code' => 'love', 'emoji' => '❤️', 'label' => 'reaction.love'],
        ['code' => 'laugh', 'emoji' => '😄', 'label' => 'reaction.laugh'],
        ['code' => 'celebrate', 'emoji' => '🎉', 'label' => 'reaction.celebrate'],
        ['code' => 'insightful', 'emoji' => '💡', 'label' => 'reaction.insightful'],
        ['code' => 'thanks', 'emoji' => '🙏', 'label' => 'reaction.thanks'],
    ];

    #[Override]
    protected string $extensionAlias = 'phil_reactions';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $audiences = array_map(static fn (Audience $audience): string => $audience->value, Audience::cases());
        $children = $definition->rootNode()->children();

        $children
            ->enumNode('store')
                ->info('Where reactions are kept: "doctrine", or "memory" for tests.')
                ->values(['doctrine', 'memory'])
                ->defaultValue('doctrine')
            ->end()
            ->scalarNode('connection')
                ->info('The Doctrine connection of the table.')
                ->cannotBeEmpty()
                ->defaultValue('default')
            ->end();

        $this->reactionsNode($children->arrayNode('unicode'), 'emoji', true)
            ->info('The Unicode emojis offered, by default six positive ones. A negative reaction is declared private or not at all.')
            ->defaultValue(array_map(static fn (array $reaction): array => [...$reaction, 'private' => false, 'quick' => true, 'translation_domain' => ReactionDefinition::DEFAULT_DOMAIN], self::DEFAULT_REACTIONS));
        $this->reactionsNode($children->arrayNode('custom'), 'image', false)
            ->info('The emojis of the site, each an image with its replacement text.');

        $children
            ->arrayNode('order')
                ->info('The names of the sources shown first: "unicode", "site" and those of the application.')
                ->scalarPrototype()->end()
            ->end()
            ->arrayNode('visibility')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('panel')->info('Who sees the reactions at all.')->values($audiences)->defaultValue(Audience::Everyone->value)->end()
                    ->enumNode('counts')->info('Who sees the counters.')->values($audiences)->defaultValue(Audience::Everyone->value)->end()
                    ->enumNode('names')->info('Who sees the names of those who reacted.')->values($audiences)->defaultValue(Audience::Nobody->value)->end()
                ->end()
            ->end()
            ->scalarNode('access')->info("The ReactionAccess service; Symfony's voters when Security is there, nobody otherwise.")->defaultNull()->end()
            ->scalarNode('current_member')->info('The CurrentMember service; the user of the security token when Security is there, nobody otherwise.')->defaultNull()->end()
            ->scalarNode('member_names')->info('The MemberNames service, needed to show names.')->defaultNull()->end()
            ->scalarNode('visibility_policy')->info('The VisibilityPolicy service, to decide content by content.')->defaultNull()->end()
            ->scalarNode('rate_limiter')->info('The rate limiter factory holding back a member.')->cannotBeEmpty()->defaultValue('limiter.'.self::LIMITER)->end()
            ->booleanNode('messenger')->info('Whether the events are also sent on the message bus.')->defaultFalse()->end()
            ->integerNode('per_member')->info('How many reactions a member gives one content: 1, which a new reaction replaces, or up to '.ReactionsPerMember::MAX.', each code once.')->min(1)->max(ReactionsPerMember::MAX)->defaultValue(1)->end()
            ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')->defaultNull()->end()
        ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', [
                'csrf_protection' => ['stateless_token_ids' => [ReactionGuard::TOKEN_ID]],
                'rate_limiter' => [self::LIMITER => ['policy' => 'sliding_window', 'limit' => 30, 'interval' => '1 minute']],
            ]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [
                self::CONTROLLERS_DIR => '@phil/reactions-bundle',
                self::STYLES_DIR => 'phil-reactions',
            ]]);
        }

        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilReactionsBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\ReactionsBundle\Domain\Model',
            ]]]]);
        }

        if ($builder->hasExtension('phil_settings')) {
            $audience = [
                'type' => 'choice',
                'scope' => 'site',
                'section' => 'general',
                'default' => SettingsVisibility::CONFIGURED,
                'choices' => [SettingsVisibility::CONFIGURED, ...array_map(static fn (Audience $audience): string => $audience->value, Audience::cases())],
                'translation_domain' => ReactionDefinition::DEFAULT_DOMAIN,
            ];
            $container->extension('phil_settings', ['settings' => [
                SettingsVisibility::PANEL => $audience,
                SettingsVisibility::COUNTS => $audience,
                SettingsVisibility::NAMES => $audience,
            ]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, connection: string, unicode: list<array<string, mixed>>, custom: list<array<string, mixed>>, order: list<string>, visibility: array{panel: string, counts: string, names: string}, access: ?string, current_member: ?string, member_names: ?string, visibility_policy: ?string, rate_limiter: string, messenger: bool, kit_subdirectory: ?string, per_member: int} $config */
        ReactionsServices::register($builder, $config);
    }

    private function reactionsNode(ArrayNodeDefinition $node, string $face, bool $quick): ArrayNodeDefinition
    {
        $reaction = $node->performNoDeepMerging()->arrayPrototype()->children();
        $reaction
            ->scalarNode('code')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('label')->info('A translation key.')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode($face)->isRequired()->cannotBeEmpty()->end()
            ->booleanNode('private')->info('Without public counter or names, for a reaction such as "not useful".')->defaultFalse()->end()
            ->booleanNode('quick')->info('Shown in the bar rather than waiting in the picker.')->defaultValue($quick)->end()
            ->scalarNode('translation_domain')->cannotBeEmpty()->defaultValue(ReactionDefinition::DEFAULT_DOMAIN)->end();

        if ('image' === $face) {
            $reaction->scalarNode('alt')->info('The translation key of the replacement text.')->isRequired()->cannotBeEmpty()->end();
        }

        return $node;
    }
}
