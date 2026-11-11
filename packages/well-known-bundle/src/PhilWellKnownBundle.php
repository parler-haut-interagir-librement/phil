<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle;

use Override;
use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\Did;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfo;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoSoftware;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerResource;
use Phil\WellKnownBundle\Domain\ValueObject\WellKnownPath;
use Phil\WellKnownBundle\Infrastructure\DependencyInjection\WellKnownPass;
use Phil\WellKnownBundle\Infrastructure\DependencyInjection\WellKnownServices;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The `/.well-known/` addresses of a site (RFC 8615), each turned on in the `phil_well_known` configuration: security.txt,
 * change-password, WebFinger, NodeInfo, `did.json`, the DID configuration, the AT Protocol handle, the app links of
 * Apple and Android, fixed documents, and the documents other bundles add with `#[AsWellKnownDocument]`.
 *
 * A value a document could not serve — a security.txt that expires, a contact that is not a URI, a DID that is not
 * one — is refused when the container is built, not when the document is asked for.
 *
 * @phpstan-import-type WellKnownConfig from WellKnownServices
 */
final class PhilWellKnownBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_well_known';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('cache_max_age')
                    ->info('The seconds a served document may be kept by browsers and shared caches; 0 keeps it in none.')
                    ->defaultValue(3600)
                    ->min(0)
                ->end()
                ->append($this->securityTxt())
                ->append($this->changePassword())
                ->append($this->webFinger())
                ->append($this->nodeInfo())
                ->append($this->jsonDocument('did', 'did.json, the DID document of a did:web identifier.', WellKnownServices::didDocument(...)))
                ->append($this->didConfiguration())
                ->append($this->atproto())
                ->append($this->jsonDocument('apple_app_site_association', 'apple-app-site-association, the universal links of iOS apps.', static fn (mixed $document): array => WellKnownServices::jsonObject($document, 'apple_app_site_association')))
                ->append($this->assetLinks())
                ->append($this->documents())
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new WellKnownPass());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var WellKnownConfig $config */
        WellKnownServices::register($builder, $config);
    }

    private function securityTxt(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('security_txt')->getRootNode();
        $children = $node->info('/.well-known/security.txt (RFC 9116).')->canBeEnabled()->children();
        $this->strings($children, 'contact', 'mailto:, tel: or https:// URIs; at least one.');
        $children->scalarNode('expires')->info('The date the file stops holding, such as "2027-06-30T00:00:00Z"; required, and in the future when the container is built.')->defaultNull()->end();
        $this->strings($children, 'encryption', 'https://, dns: or openpgp4fpr: URIs of the key to encrypt reports with.');
        $this->strings($children, 'acknowledgments', 'https:// URLs of the page thanking reporters.');
        $this->strings($children, 'preferred_languages', 'Language tags, such as "en" or "fr-CA".');
        $this->strings($children, 'canonical', 'https:// URLs where the file is published.');
        $this->strings($children, 'policy', 'https:// URLs of the disclosure policy.');
        $this->strings($children, 'hiring', 'https:// URLs of security jobs.');
        $children->scalarNode('signer')->info('The id of a SecurityTxtSigner service that signs the file.')->defaultNull()->end();
        $children->integerNode('expiry_warning_days')
            ->info('The days before Expires from which the check of phil/monitoring-bundle is degraded; down once the date has passed.')
            ->min(1)
            ->max(365)
            // Not the constant of the check: its class needs phil/monitoring-bundle, which the bundle does not require.
            ->defaultValue(30)
        ->end();

        $node->validate()
            ->ifTrue(static fn (array $config): bool => true === $config['enabled'])
            ->then(static function (array $config): array {
                /** @var array{contact: list<string>, expires: ?string, encryption: list<string>, acknowledgments: list<string>, preferred_languages: list<string>, canonical: list<string>, policy: list<string>, hiring: list<string>} $config */
                WellKnownServices::securityTxt($config);

                return $config;
            });

        return $node;
    }

    private function changePassword(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('change_password')->getRootNode();
        $node->info('/.well-known/change-password: a 302 to the page where a user changes their password.')
            ->canBeEnabled()
            ->children()
                ->scalarNode('route')->info('The route of that page.')->defaultNull()->end()
                ->arrayNode('parameters')->info('The parameters of that route.')->useAttributeAsKey('name')->normalizeKeys(false)->scalarPrototype()->end()->end()
                ->scalarNode('url')->info('Or the URL of that page: https://, or a path starting with "/".')->defaultNull()->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => true === $config['enabled'] && (null === $config['route']) === (null === $config['url']))
                ->thenInvalid('change_password takes a route or a URL, one of them.')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => \is_string($config['url']) && !str_starts_with($config['url'], 'https://') && 1 !== preg_match('~^/(?![/\\\\])~', $config['url']))
                ->then(static fn (array $config): never => throw InvalidValue::because(\sprintf('The URL of change_password is https://, or a path starting with "/": "%s".', \is_string($config['url']) ? $config['url'] : '')))
            ->end();

        return $node;
    }

    private function webFinger(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('webfinger')->getRootNode();
        $resources = $node->info('/.well-known/webfinger (RFC 7033).')
            ->canBeEnabled()
            ->children()
                ->arrayNode('resources')
                    ->info('Fixed resources, such as acct:contact@example.com, and what WebFinger says of them; resolvers add the others.')
                    ->useAttributeAsKey('resource')->normalizeKeys(false)
                    ->arrayPrototype();
        $children = $resources->children();
        $children->scalarNode('subject')->info('The subject, when it is not the resource.')->defaultNull()->end();
        $this->strings($children, 'aliases', 'Other URIs of the resource.');
        $this->nullableMap($children, 'properties', 'Properties keyed by URI.');
        $link = $children->arrayNode('links')->arrayPrototype()->children();
        $link->scalarNode('rel')->isRequired()->cannotBeEmpty()->end();
        $link->scalarNode('type')->defaultNull()->end();
        $link->scalarNode('href')->defaultNull()->end();
        $link->scalarNode('template')->defaultNull()->end();
        $link->arrayNode('titles')->useAttributeAsKey('language')->normalizeKeys(false)->scalarPrototype()->end()->end();
        $this->nullableMap($link, 'properties', 'Properties keyed by URI.');

        $node->validate()->always(static function (array $config): array {
            /** @var array{enabled: bool, resources: array<string, array<string, mixed>>} $config */
            $resources = [];

            foreach ($config['resources'] as $resource => $description) {
                $resources[WebFingerResource::parse($resource)->uri] = $description;
            }

            $config['resources'] = $resources;

            return $config;
        });

        return $node;
    }

    private function nodeInfo(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('nodeinfo')->getRootNode();
        $children = $node->info('/.well-known/nodeinfo and the NodeInfo 2.1 document it links to.')->canBeEnabled()->children();
        $children->scalarNode('path')->info('Where the 2.1 document is served.')->defaultValue('/nodeinfo/2.1')->end();
        $software = $children->arrayNode('software')->info('The software, when no provider tells it.')->addDefaultsIfNotSet()->children();
        $software->scalarNode('name')->defaultValue('phil')->end();
        $software->scalarNode('version')->defaultValue('0.1.0')->end();
        $software->scalarNode('repository')->defaultNull()->end();
        $software->scalarNode('homepage')->defaultNull()->end();
        $this->strings($children, 'protocols', 'NodeInfo 2.1 protocols; at least one.', ['activitypub']);
        $services = $children->arrayNode('services')->addDefaultsIfNotSet()->children();
        $this->strings($services, 'inbound', 'NodeInfo 2.1 inbound services.');
        $this->strings($services, 'outbound', 'NodeInfo 2.1 outbound services.');
        $children->booleanNode('open_registrations')->info('Whether the site takes new users, when no provider tells it.')->defaultFalse()->end();
        $children->variableNode('metadata')->info('Free metadata, when no provider tells it.')->defaultValue([])->end();
        $children->scalarNode('provider')->info('The id of a NodeInfoProvider service.')->defaultNull()->end();

        $node->validate()->always(static function (array $config): array {
            /** @var array{path: string, software: array{name: string, version: string, repository: ?string, homepage: ?string}, protocols: list<string>, services: array{inbound: list<string>, outbound: list<string>}, metadata: mixed} $config */
            WellKnownPath::of($config['path']);

            if (!str_starts_with($config['path'], '/')) {
                throw InvalidValue::because(\sprintf('The path of the NodeInfo document is absolute: "%s".', $config['path']));
            }

            new NodeInfoSoftware(...$config['software']);
            NodeInfo::check('protocol', $config['protocols'], NodeInfo::PROTOCOLS);
            NodeInfo::check('inbound service', $config['services']['inbound'], NodeInfo::INBOUND);
            NodeInfo::check('outbound service', $config['services']['outbound'], NodeInfo::OUTBOUND);

            if ([] === $config['protocols'] || !\is_array($config['metadata']) || ([] !== $config['metadata'] && array_is_list($config['metadata']))) {
                throw InvalidValue::because('NodeInfo names at least one protocol, and its metadata is a map.');
            }

            return $config;
        });

        return $node;
    }

    /**
     * A document given in the configuration, or read from a JSON file when the container is built.
     *
     * @param callable(mixed): mixed $check
     */
    private function jsonDocument(string $name, string $info, callable $check): ArrayNodeDefinition
    {
        $node = new TreeBuilder($name)->getRootNode();
        $node->info($info)
            ->canBeEnabled()
            ->children()
                ->variableNode('document')->info('The document, as a map.')->defaultNull()->end()
                ->scalarNode('file')->info('Or a JSON file holding it, read when the container is built.')->defaultNull()->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => true === $config['enabled'] && (null === $config['document']) === (null === $config['file']))
                ->thenInvalid(\sprintf('%s takes a document or a file, one of them.', $name))
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => null !== $config['document'])
                ->then(static function (array $config) use ($check): array {
                    $config['document'] = $check($config['document']);

                    return $config;
                })
            ->end();

        return $node;
    }

    private function didConfiguration(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('did_configuration')->getRootNode();
        $node->info('/.well-known/did-configuration.json (DIF Well Known DID Configuration): the credentials linking the domain to its DIDs.')
            ->canBeEnabled()
            ->children()
                ->arrayNode('linked_dids')
                    ->info('Domain linkage credentials: JWTs, or JSON-LD credentials as maps.')
                    ->variablePrototype()
                        ->validate()->always(static fn (mixed $credential): mixed => WellKnownServices::linkedDid($credential, 'linked_dids'))->end()
                    ->end()
                ->end()
                ->arrayNode('files')->info('Files holding one credential each, read when the container is built.')->scalarPrototype()->end()->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => true === $config['enabled'] && [] === $config['linked_dids'] && [] === $config['files'])
                ->thenInvalid('did_configuration links at least one DID.')
            ->end();

        return $node;
    }

    private function atproto(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('atproto')->getRootNode();
        $node->info('/.well-known/atproto-did: the DID an AT Protocol handle of the domain resolves to.')
            ->canBeEnabled()
            ->children()
                ->scalarNode('did')->info("The DID of the site's own handle.")->defaultNull()->end()
                ->arrayNode('hosts')->info('The DID of other hosts served by the site, such as user handles on subdomains.')->useAttributeAsKey('host')->normalizeKeys(false)->scalarPrototype()->end()->end()
            ->end()
            ->validate()->always(static function (array $config): array {
                /** @var array{enabled: bool, did: ?string, hosts: array<string, string>} $config */
                $hosts = [];

                foreach ($config['hosts'] as $host => $did) {
                    $hosts[strtolower($host)] = Did::of($did)->did;
                }

                if (null !== $config['did']) {
                    Did::of($config['did']);
                }

                if ($config['enabled'] && null === $config['did'] && [] === $hosts) {
                    throw InvalidValue::because('atproto names a DID, or the DID of a host.');
                }

                $config['hosts'] = $hosts;

                return $config;
            })->end();

        return $node;
    }

    private function assetLinks(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('asset_links')->getRootNode();
        $app = $node->info('/.well-known/assetlinks.json (Digital Asset Links): the app links of Android apps.')
            ->canBeEnabled()
            ->children()
                ->arrayNode('android_apps')->arrayPrototype()->children();
        $app->scalarNode('package_name')
            ->isRequired()
            ->validate()
                ->ifTrue(static fn (mixed $name): bool => !\is_string($name) || 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+$/D', $name))
                ->thenInvalid('%s is not an Android package name.')
            ->end()
        ->end();
        $app->arrayNode('sha256_cert_fingerprints')
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->scalarPrototype()
                ->validate()
                    ->ifTrue(static fn (mixed $print): bool => !\is_string($print) || 1 !== preg_match('/^[0-9A-F]{2}(?::[0-9A-F]{2}){31}$/D', $print))
                    ->thenInvalid('%s is not a SHA-256 fingerprint: 32 bytes in uppercase hexadecimal, separated by colons.')
                ->end()
            ->end()
        ->end();
        $this->strings($app, 'relations', 'The relations granted.', ['delegate_permission/common.handle_all_urls']);
        $node->children()->arrayNode('statements')->info('Other statements, written as they are served.')->variablePrototype()->end()->end();
        $node->validate()
            ->ifTrue(static fn (array $config): bool => true === $config['enabled'] && [] === $config['android_apps'] && [] === $config['statements'])
            ->thenInvalid('asset_links names at least one app or statement.');

        return $node;
    }

    private function documents(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('documents')->getRootNode();
        $node->info('Fixed documents, keyed by their path under /.well-known/.')
            ->useAttributeAsKey('path')->normalizeKeys(false)
            ->arrayPrototype()
                ->children()
                    ->scalarNode('content')->info('The body.')->defaultNull()->end()
                    ->scalarNode('file')->info('Or a file holding it, read when the container is built.')->defaultNull()->end()
                    ->scalarNode('content_type')->info('Its media type.')->isRequired()->cannotBeEmpty()->end()
                    ->booleanNode('cors')->info('Whether the browsers of other sites may read it.')->defaultFalse()->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $config): bool => (null === $config['content']) === (null === $config['file']))
                    ->thenInvalid('A document takes a content or a file, one of them.')
                ->end()
            ->end()
            ->validate()->always(static function (array $documents): array {
                foreach (array_keys($documents) as $path) {
                    WellKnownPath::of((string) $path);
                }

                return $documents;
            })->end();

        return $node;
    }

    /**
     * @param list<string> $default
     */
    private function strings(NodeBuilder $children, string $name, string $info, array $default = []): void
    {
        $children->arrayNode($name)->info($info)->defaultValue($default)->scalarPrototype()->end()->end();
    }

    private function nullableMap(NodeBuilder $children, string $name, string $info): void
    {
        $children->arrayNode($name)->info($info)->useAttributeAsKey('name')->normalizeKeys(false)->scalarPrototype()->end()->end();
    }
}
