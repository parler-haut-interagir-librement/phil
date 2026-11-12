<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle;

use Override;
use Phil\SafeHttp\Limits;
use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCards\Card\Color;
use Phil\SocialCards\Exception\SocialCardsException;
use Phil\SocialCards\ShareMetadata;
use Phil\SocialCardsBundle\Infrastructure\DependencyInjection\RequiredServicesPass;
use Phil\SocialCardsBundle\Infrastructure\DependencyInjection\SocialCardsServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * phil/social-cards in a Symfony application: the share tags of each page through `social_meta()`, its card drawn from
 * a declared template and served from `/social-cards/{fingerprint}.png`, by a worker when Messenger is there, the
 * previews of links to other sites, fetched safely, and `phil:social-cards:check`.
 */
final class PhilSocialCardsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_social_cards';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()
                ->ifTrue($this->lacksDefaultTemplate(...))
                ->thenInvalid('The default card template is not among the declared templates: %s')
            ->end()
            ->children()
                ->scalarNode('site_name')->info('The name of the site, og:site_name and the subtitle of a card without a description.')->defaultNull()->end()
                ->scalarNode('locale')
                    ->info("The locale of the pages, as fr_CA; the request's when null.")
                    ->defaultNull()
                    ->validate()->ifTrue(static fn (mixed $locale): bool => !self::valid('locale', $locale))->thenInvalid('%s is not a locale, as fr_CA.')->end()
                ->end()
                ->scalarNode('twitter_site')
                    ->info('The account of the site on X, as @example.')
                    ->defaultNull()
                    ->validate()->ifTrue(static fn (mixed $handle): bool => !self::valid('twitter_site', $handle))->thenInvalid('%s is not an account on X.')->end()
                ->end()
                ->scalarNode('fediverse_creator')
                    ->info('The account on the fediverse credited for the pages, as @user@example.social.')
                    ->defaultNull()
                    ->validate()->ifTrue(static fn (mixed $handle): bool => !self::valid('fediverse_creator', $handle))->thenInvalid('%s is not an account on the fediverse.')->end()
                ->end()
                ->variableNode('default_image')
                    ->info('The image of a page without its own when no card is drawn: an address, or url, alt, width, height and type.')
                    ->defaultNull()
                    ->validate()->ifTrue(static fn (mixed $image): bool => !self::valid('image', $image))->thenInvalid('%s is not an image: an absolute address, or url, alt, width, height and type.')->end()
                ->end()
                ->arrayNode('cards')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Whether a page without an image gets a card; cards need a template.')->defaultTrue()->end()
                        ->scalarNode('directory')->info('Where the cards and what they are drawn from are kept.')->defaultValue('%kernel.project_dir%/var/social-cards')->cannotBeEmpty()->end()
                        ->integerNode('cache_max_age')->info('The seconds a card may be kept by browsers and shared caches.')->defaultValue(31_536_000)->min(0)->end()
                        ->booleanNode('async')->info('Whether the cards are drawn by a worker, as GenerateSocialCard messages, when Messenger is there.')->defaultTrue()->end()
                        ->scalarNode('default_template')->defaultValue('default')->cannotBeEmpty()->end()
                        ->arrayNode('templates')
                            ->info('The card templates by name: title_font is required; see CardTemplate::KEYS.')
                            ->useAttributeAsKey('name')
                            ->variablePrototype()
                                ->validate()->ifTrue(static fn (mixed $template): bool => !self::isTemplate($template))->thenInvalid('%s is not a card template: an array of '.implode(', ', CardTemplate::KEYS).', with title_font, sizes as integers and colours as #rrggbb.')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('preview')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->floatNode('timeout')->info('The seconds to wait for a byte of an answer.')->defaultValue(5.0)->min(0.1)->max(60.0)->end()
                        ->floatNode('max_duration')->info('The seconds a whole fetch may last.')->defaultValue(10.0)->min(0.1)->max(120.0)->end()
                        ->integerNode('max_bytes')->info('The most bytes of a page read.')->defaultValue(1_048_576)->min(1024)->max(16_777_216)->end()
                        ->integerNode('max_redirects')->defaultValue(3)->min(0)->max(10)->end()
                        ->integerNode('cache_ttl')->info('The seconds a preview is kept in cache.app.')->defaultValue(86_400)->min(0)->end()
                        ->scalarNode('user_agent')->defaultValue('PhilSocialCards/0.1 (link preview)')->cannotBeEmpty()
                            ->validate()->ifTrue(static fn (mixed $agent): bool => !\is_string($agent) || !Limits::isHeaderValue($agent))->thenInvalid('%s is not a header value: one line, without a control character.')->end()
                        ->end()
                        ->scalarNode('limiter')->info('The id of the rate limiter factory that bounds the pasted links one visitor has previewed, limiter.<name> of framework.rate_limiter.')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{cards: array{default_template: string, templates: array<string, mixed>}} $config
     */
    private function lacksDefaultTemplate(array $config): bool
    {
        return [] !== $config['cards']['templates'] && !\array_key_exists($config['cards']['default_template'], $config['cards']['templates']);
    }

    /**
     * Whether a value of the configuration is one the share metadata takes, checked by building metadata with it: a
     * null value is a missing one.
     */
    private static function valid(string $key, mixed $value): bool
    {
        try {
            ShareMetadata::fromArray(['title' => 'T', 'url' => 'https://example.com/', $key => $value]);
        } catch (SocialCardsException) {
            return false;
        }

        return true;
    }

    /**
     * Whether a template has the shape `CardTemplate::fromArray()` reads; its fonts and images are read when it is first
     * used, at run time, where they are installed.
     */
    private static function isTemplate(mixed $template): bool
    {
        if (!\is_array($template) || !\is_string($template['title_font'] ?? null) || [] !== array_diff(array_keys($template), CardTemplate::KEYS)) {
            return false;
        }

        foreach (['background', 'title_color', 'subtitle_color', 'accent'] as $color) {
            try {
                Color::fromHex(\is_string($template[$color] ?? '#fff') ? $template[$color] ?? '#fff' : '');
            } catch (SocialCardsException) {
                return false;
            }
        }

        return array_all(['padding', 'title_max_size', 'title_min_size', 'title_max_lines', 'subtitle_size', 'subtitle_max_lines'], static fn (string $size): bool => !isset($template[$size]) || \is_int($template[$size]));
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RequiredServicesPass(SocialCardsServices::REQUIREMENTS), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{site_name: ?string, locale: ?string, twitter_site: ?string, fediverse_creator: ?string, default_image: array<string, mixed>|string|null, cards: array{enabled: bool, directory: string, cache_max_age: int, async: bool, default_template: string, templates: array<string, array<string, mixed>>}, preview: array{enabled: bool, timeout: float, max_duration: float, max_bytes: int, max_redirects: int, cache_ttl: int, user_agent: string, limiter: ?string}} $config */
        SocialCardsServices::register($builder, $config);
    }
}
