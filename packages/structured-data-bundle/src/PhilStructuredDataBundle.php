<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle;

use Override;
use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\LanguageTag;
use Phil\StructuredData\Node;
use Phil\StructuredData\Schema;
use Phil\StructuredData\Type\WebSite;
use Phil\StructuredDataBundle\Application\PageContext;
use Phil\StructuredDataBundle\Infrastructure\DependencyInjection\StructuredDataServices;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The schema.org graph of each page of a Symfony site: the nodes of the configuration — the site, its owner, the page
 * —, those of the providers and listeners of other packages, those templates and controllers add, the breadcrumb of a
 * phil/menu-bundle menu, printed as one script element by `structured_data()`.
 *
 * A value no graph could hold — an address that is not absolute, a language that is not BCP 47, a search address
 * without `{search_term_string}` — is refused when the container is built, not on a page.
 *
 * @phpstan-import-type StructuredDataConfig from StructuredDataServices
 */
final class PhilStructuredDataBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_structured_data';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode();
        $root->validate()->always(static fn (array $config): array => self::onLocalhost($config))->end();
        $children = $root->children();
        $children->scalarNode('base_url')
            ->info('The address of the site, such as "https://example.com/", for the identifiers and the addresses of the nodes; the request\'s when null.')
            ->defaultNull()
            ->validate()->always(static fn (mixed $url): ?string => null === $url ? null : rtrim(self::absolute($url, 'base_url'), '/').'/')->end()
        ->end();
        $children->scalarNode('language')
            ->info('The language of the texts of every graph, as a BCP 47 tag.')
            ->defaultNull()
            ->validate()->always(static fn (mixed $language): ?string => null === $language ? null : self::language($language))->end()
        ->end();
        $children->booleanNode('request_language')->info('Whether a graph is in the language of the request when no language is set.')->defaultFalse()->end();
        $children->append($this->website());
        $children->append($this->organization());
        $children->booleanNode('web_page')->info('Whether each page has its WebPage node, part of the site.')->defaultTrue()->end();
        $children->arrayNode('breadcrumb')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('menu')->info('The phil/menu-bundle menu whose breadcrumb is the BreadcrumbList of each page.')->defaultNull()->end()
            ->end()
        ->end();
        $children->arrayNode('nonce')
            ->info('Where the nonce of the script element comes from; none by default: the element is a data block, which a content security policy does not execute.')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('request_attribute')->info('The attribute of the main request holding the nonce of the content security policy.')->defaultNull()->end()
                ->booleanNode('content_security_policy')->info('Whether the nonce is the script nonce of phil/browser-security-bundle.')->defaultFalse()->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $nonce): bool => null !== $nonce['request_attribute'] && true === $nonce['content_security_policy'])
                ->thenInvalid('The nonce of phil_structured_data comes from a request attribute or from the content security policy, not both.')
            ->end()
        ->end();
        $children->booleanNode('allow_localhost')
            ->info('Whether an address of the graphs may be on localhost or under .localhost: true in development and tests only.')
            ->defaultFalse()
        ->end();
        $children->arrayNode('check')
            ->addDefaultsIfNotSet()
            ->children()
                ->floatNode('timeout')->info('The seconds phil:structured-data:check waits for a page.')->defaultValue(10.0)->min(0.1)->end()
            ->end()
        ->end();
    }

    /**
     * The factories of `Schema` check every node they build: they take localhost when the configuration allows it.
     */
    #[Override]
    public function boot(): void
    {
        $container = $this->container;
        Schema::allowLocalhost($container instanceof ContainerInterface && $container->hasParameter('phil_structured_data.allow_localhost') && true === $container->getParameter('phil_structured_data.allow_localhost'));
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var StructuredDataConfig $config */
        // The nodes of the configuration are built once here, so that a value their type refuses stops the build; they
        // take localhost as the application will once booted.
        Schema::allowLocalhost($config['allow_localhost']);

        try {
            [...StructuredDataServices::defaultNodes($config)->nodes(new PageContext('https://example.com/', 'https://example.com/'))];
        } catch (InvalidStructuredData $invalidStructuredData) {
            throw InvalidStructuredData::because('The website or the organization of phil_structured_data: '.$invalidStructuredData->getMessage());
        } finally {
            Schema::allowLocalhost(false);
        }

        StructuredDataServices::register($builder, $config);
    }

    private function website(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('website')->getRootNode();
        $children = $node->info('The site as a WebSite node, "#website" at its address.')->canBeEnabled()->children();
        $this->texts($children, ['name' => 'Its name; required.', 'alternate_name' => 'Another name, such as an acronym.', 'description' => 'What it is about.']);
        $this->urls($children, ['url' => 'Its address; the base address when null.']);
        $children->scalarNode('search_url')
            ->info('The address of its search, with {search_term_string} where the words go.')
            ->defaultNull()
            ->validate()->always(static fn (mixed $url): ?string => null === $url ? null : self::search($url))->end()
        ->end();

        $node->validate()
            ->ifTrue(static fn (array $config): bool => true === $config['enabled'] && !\is_string($config['name']))
            ->thenInvalid('The website has a name.');

        return $node;
    }

    private function organization(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('organization')->getRootNode();
        $children = $node->info('The owner of the site as an Organization node, or one of its kinds, "#organization" at its address.')->canBeEnabled()->children();
        $children->scalarNode('type')
            ->info('Organization, or a kind of it: Corporation, NGO, LocalBusiness, Store…')
            ->defaultValue('Organization')
            ->validate()->always(static function (mixed $type): string {
                try {
                    return Node::of(\is_string($type) ? $type : '')->types[0];
                } catch (InvalidStructuredData $invalidStructuredData) {
                    throw InvalidStructuredData::because('The type of the organization of phil_structured_data: '.$invalidStructuredData->getMessage());
                }
            })->end()
        ->end();
        $this->texts($children, ['name' => 'Its name; required.', 'legal_name' => 'Its registered name.', 'description' => 'What it does.', 'email' => 'Its address for mail.', 'telephone' => 'Its telephone number, best in E.164: +15145550100.']);
        $this->urls($children, ['url' => 'Its address; the base address when null.', 'logo' => 'The address of its logo.']);
        $children->arrayNode('same_as')
            ->info('Its pages elsewhere: an encyclopedia, its profiles.')
            ->scalarPrototype()->validate()->always(static fn (mixed $url): string => self::absolute($url, 'same_as'))->end()->end()
        ->end();
        $address = $children->arrayNode('address')->info('Its postal address.')->addDefaultsIfNotSet()->children();
        $this->texts($address, ['street_address' => 'The street and number.', 'address_locality' => 'The city.', 'address_region' => 'The province or state.', 'postal_code' => 'The postal code.', 'address_country' => 'The country, as an ISO 3166-1 code such as "CA".']);

        $node->validate()
            ->ifTrue(static fn (array $config): bool => true === $config['enabled'] && !\is_string($config['name']))
            ->thenInvalid('The organization has a name.');

        return $node;
    }

    /**
     * @param array<string, string> $names the info of each text
     */
    private function texts(NodeBuilder $children, array $names): void
    {
        foreach ($names as $name => $info) {
            $children->scalarNode($name)->info($info)->defaultNull()->end();
        }
    }

    /**
     * @param array<string, string> $names the info of each address
     */
    private function urls(NodeBuilder $children, array $names): void
    {
        foreach ($names as $name => $info) {
            $children->scalarNode($name)
                ->info($info)
                ->defaultNull()
                ->validate()->always(static fn (mixed $url): ?string => null === $url ? null : self::absolute($url, $name))->end()
            ->end();
        }
    }

    /**
     * An absolute address, read by `phil/url` as a browser reads it: an address with a user name or a password, or whose
     * host is an IP address, is refused. `localhost` passes here, and the configuration as a whole refuses it unless
     * `allow_localhost` ({@see self::onLocalhost()}).
     */
    private static function absolute(mixed $url, string $name): string
    {
        if (!\is_string($url)) {
            throw InvalidStructuredData::because(\sprintf('The %s of phil_structured_data is an absolute http:// or https:// address; "%s" is not.', $name, get_debug_type($url)));
        }

        try {
            Url::fromString($url, allowLocalhost: true);
        } catch (InvalidUrl) {
            throw InvalidStructuredData::because(\sprintf('The %s of phil_structured_data is an absolute http:// or https:// address; "%s" is not.', $name, $url));
        }

        return $url;
    }

    /**
     * The configuration, once each address of it is on a host of the web, unless `allow_localhost`: in development and
     * tests only, an address may be on `localhost` or under `.localhost`.
     *
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private static function onLocalhost(array $config): array
    {
        /** @var array{base_url: ?string, allow_localhost: bool, website: array{url: ?string, search_url: ?string}, organization: array{url: ?string, logo: ?string, same_as: list<string>}} $config */
        if ($config['allow_localhost']) {
            return $config;
        }

        $urls = [
            'base_url' => [$config['base_url']],
            'url' => [$config['website']['url'], $config['organization']['url']],
            'search_url' => [$config['website']['search_url']],
            'logo' => [$config['organization']['logo']],
            'same_as' => $config['organization']['same_as'],
        ];

        foreach ($urls as $name => $values) {
            foreach (array_filter($values, \is_string(...)) as $url) {
                try {
                    Url::fromString($url);
                } catch (InvalidUrl) {
                    throw InvalidStructuredData::because(\sprintf('The %s of phil_structured_data is on localhost, which allow_localhost leaves false; "%s" is.', $name, $url));
                }
            }
        }

        return $config;
    }

    private static function search(mixed $url): string
    {
        $url = self::absolute($url, 'search_url');

        return str_contains($url, WebSite::SEARCH_TERM) ? $url : throw InvalidStructuredData::because(\sprintf('The search_url of phil_structured_data holds %s where the words go; "%s" does not.', WebSite::SEARCH_TERM, $url));
    }

    private static function language(mixed $language): string
    {
        return LanguageTag::normalize(\is_string($language) ? $language : '');
    }
}
