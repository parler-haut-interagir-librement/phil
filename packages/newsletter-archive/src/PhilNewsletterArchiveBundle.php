<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive;

use Override;
use Phil\NewsletterArchive\Domain\ValueObject\ArchiveFilter;
use Phil\NewsletterArchive\Infrastructure\DependencyInjection\ArchiveServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The public archive of a site's sent newsletters, on `phil/newsletter-bundle`: the issues an editor publishes,
 * listed a page at a time and filtered by list and by tag, each at a clean slug; a revocable link per issue; and a web
 * version frozen at publication, purified, with neutral data in place of the subscriber's.
 *
 * ```yaml
 * phil_newsletter_archive:
 *     page_size: 20
 * ```
 */
final class PhilNewsletterArchiveBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_newsletter_archive';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')->info("Registers the archive's services.")->defaultTrue()->end()
                ->scalarNode('connection')->info('The Doctrine DBAL connection of the tables.')->defaultValue('doctrine.dbal.default_connection')->cannotBeEmpty()->end()
                ->integerNode('page_size')->info('How many issues a page of the archive lists.')->defaultValue(20)->min(1)->max(ArchiveFilter::MAX_PAGE_SIZE)->end()
                ->scalarNode('slugger')->info('The service of a Phil\Slug\Slugger that makes the slugs of the issues; by default, a TransliteratingSlugger.')->defaultNull()->end()
                ->integerNode('slug_max_length')->info('The longest slug the default slugger makes, cut between two words.')->defaultValue(120)->min(16)->max(200)->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{enabled: bool, connection: string, page_size: int, slugger: string|null, slug_max_length: int} $config */
        ArchiveServices::register($container, $config);
    }
}
