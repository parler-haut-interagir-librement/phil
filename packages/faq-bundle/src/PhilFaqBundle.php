<?php

declare(strict_types=1);

namespace Phil\FaqBundle;

use Override;
use Phil\FaqBundle\Application\VoteTokens;
use Phil\FaqBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\FaqBundle\Infrastructure\DependencyInjection\FaqServices;
use Phil\FaqBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\FaqBundle\Infrastructure\Http\FaqGuard;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Frequently asked questions by section, searchable with and without JavaScript, each in a `details` element with a
 * stable anchor, an anonymous "Was this useful?" vote, the searches that found nothing counted without saying who, and
 * the `FAQPage` JSON-LD of the page.
 *
 * ```yaml
 * phil_faq:
 *     contact_url: /contact
 *     admin: { enabled: true, role: ROLE_EDITOR, locales: [en, fr] }
 * ```
 */
final class PhilFaqBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    /** The limiter of the votes, per question; an application changes it by declaring the same name in `framework.rate_limiter`. */
    public const string VOTE_LIMITER = 'phil_faq_votes';

    /** The limiter of the counted searches, per language. */
    public const string SEARCH_LIMITER = 'phil_faq_searches';

    public const string DEFAULT_LAYOUT = '@PhilFaq/layout.html.twig';

    public const string DEFAULT_ADMIN_LAYOUT = '@PhilFaq/admin/layout.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_faq';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the tables.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('contact_url')
                    ->info('The address of the contact form a search without answer links to; no link when null.')
                    ->defaultNull()
                ->end()
                ->scalarNode('layout')
                    ->info('The template the page of the bundle extends, with the blocks "title" and "content".')
                    ->cannotBeEmpty()
                    ->defaultValue(self::DEFAULT_LAYOUT)
                ->end()
                ->integerNode('max_questions')
                    ->info('The most questions a page shows.')
                    ->min(1)->max(5_000)
                    ->defaultValue(500)
                ->end()
                ->integerNode('vote_lifetime')
                    ->info('How long the vote token of a page votes, in seconds.')
                    ->min(VoteTokens::MIN_LIFETIME)->max(VoteTokens::MAX_LIFETIME)
                    ->defaultValue(86_400)
                ->end()
                ->scalarNode('markup')
                    ->info('The Phil\Markup\Markup service that reads the answers; Markdown, HTML and the other formats of phil/markup, purified, when null.')
                    ->defaultNull()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('rate_limiters')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('votes')->info('The rate limiter factory of the votes, per question.')->cannotBeEmpty()->defaultValue('limiter.'.self::VOTE_LIMITER)->end()
                        ->scalarNode('searches')->info('The rate limiter factory of the counted searches, per language.')->cannotBeEmpty()->defaultValue('limiter.'.self::SEARCH_LIMITER)->end()
                    ->end()
                ->end()
                ->arrayNode('admin')
                    ->info('The administration, mounted from the routes of the bundle behind the firewall; it needs Symfony Security.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('role')->info('The role that opens the administration.')->cannotBeEmpty()->defaultValue('ROLE_ADMIN')->end()
                        ->scalarNode('layout')->info('The template the pages of the administration extend.')->cannotBeEmpty()->defaultValue(self::DEFAULT_ADMIN_LAYOUT)->end()
                        ->arrayNode('locales')
                            ->info('The languages the administration edits, the first one by default.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['en'])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', [
                'csrf_protection' => ['stateless_token_ids' => [FaqGuard::TOKEN_ID]],
                'rate_limiter' => [
                    self::VOTE_LIMITER => ['policy' => 'sliding_window', 'limit' => 60, 'interval' => '1 minute'],
                    self::SEARCH_LIMITER => ['policy' => 'sliding_window', 'limit' => 120, 'interval' => '1 minute'],
                ],
            ]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/faq-bundle']]);
        }

        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilFaqBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\FaqBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{entity_manager: string, contact_url: ?string, layout: string, max_questions: int<1, max>, vote_lifetime: int<60, 604800>, markup: ?string, kit_subdirectory: ?string, rate_limiters: array{votes: string, searches: string}, admin: array{enabled: bool, role: string, layout: string, locales: list<string>}} $config */
        FaqServices::register($builder, $config);
    }
}
