<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle;

use Override;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Infrastructure\DependencyInjection\PagedMediaServices;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/paged-media` in a Symfony application: the native writer and Gotenberg behind one service, `PagedMedia`; the
 * Gotenberg URL and credentials, the signing certificate and the timestamp authority from the configuration; document
 * templates registered by an attribute; and Messenger messages that render, run stages or convert asynchronously, each
 * carrying only the identifier of a job kept aside.
 */
final class PhilPagedMediaBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_paged_media';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $this->gotenberg($children);
        $this->signing($children);
        $children
            ->arrayNode('storage')
                ->info('Where the files rendered by messages are kept; alias Phil\PagedMediaBundle\Application\Port\PdfStorage to keep them elsewhere.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('directory')->defaultValue('%kernel.project_dir%/var/paged-media')->cannotBeEmpty()->end()
                ->end()
            ->end()
            ->arrayNode('jobs')
                ->info('Where the jobs of the messages wait for a worker, readable by their owner only; alias Phil\PagedMediaBundle\Application\Port\JobStore to keep them elsewhere.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('directory')->defaultValue('%kernel.project_dir%/var/paged-media-jobs')->cannotBeEmpty()->end()
                    ->booleanNode('remove_failed')
                        ->info('Whether the job of a message that failed for good is forgotten; false keeps it, for a message retried from a failure transport.')
                        ->defaultTrue()
                    ->end()
                    ->scalarNode('cache_pool')
                        ->info("A PSR-6 cache pool service the jobs are kept in instead of the directory, shared by every worker, such as the pool of a transport's claim check.")
                        ->defaultNull()
                    ->end()
                    ->integerNode('lifetime')
                        ->info("How long a job is kept in the cache pool, in seconds; the pool's default when null.")
                        ->defaultNull()
                        ->min(1)
                    ->end()
                ->end()
            ->end()
            ->arrayNode('templates')
                ->info('The printed templates of phil/paged-media, registered as document templates: name badges, labels, gift and download cards, calendars, large print.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->scalarNode('font')->info('A TrueType font file the templates write in; Helvetica when null.')->defaultNull()->end()
                    ->scalarNode('bold_font')->info('A TrueType font file for what they stress; the regular font, or Helvetica Bold, when null.')->defaultNull()->end()
                ->end()
            ->end()
        ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{gotenberg: array{url: ?string, username: ?string, password: ?string, timeout: ?float, http_client: string}, signing: array{certificate: array{type: string, path: ?string, key_path: ?string, password: ?string, certificate: ?string, key: ?string, vault_address: ?string, vault_token: ?string, vault_path: ?string, vault_mount: string}, timestamp_url: ?string, reason: ?string, location: ?string, contact_info: ?string, name: ?string, pades: bool}, storage: array{directory: string}, jobs: array{directory: string, remove_failed: bool, cache_pool: ?string, lifetime: ?int}, templates: array{enabled: bool, font: ?string, bold_font: ?string}} $config */
        PagedMediaServices::register($builder, $config['gotenberg'], $config['signing'], $config['storage'], $config['jobs'], $config['templates']);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(AsDocumentTemplate::class, static function (ChildDefinition $definition, AsDocumentTemplate $attribute): void {
            $definition->addTag(PagedMediaServices::TEMPLATE_TAG, ['name' => $attribute->name]);
        });
    }

    private function gotenberg(NodeBuilder $children): void
    {
        $children
            ->arrayNode('gotenberg')
                ->info('The Gotenberg 9 the PDF stages and the LibreOffice conversions are sent to; none when the URL is null.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('url')->info('Its base URL, such as http://gotenberg:3000.')->defaultNull()->end()
                    ->scalarNode('username')->info('The basic authentication user, when Gotenberg runs with --api-enable-basic-auth.')->defaultNull()->end()
                    ->scalarNode('password')->defaultNull()->end()
                    ->floatNode('timeout')->info("The longest a request may take, in seconds; the HTTP client's default when null.")->defaultNull()->min(0)->end()
                    ->scalarNode('http_client')->info('The Symfony HttpClient service the requests go through.')->defaultValue('http_client')->cannotBeEmpty()->end()
                ->end()
            ->end();
    }

    private function signing(NodeBuilder $children): void
    {
        $children
            ->arrayNode('signing')
                ->info('The certificate PDFs are signed with, the RFC 3161 authority that timestamps them, and what every signature says.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('certificate')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('type')->values(['none', 'file', 'inline', 'vault'])->defaultValue('none')->end()
                            ->scalarNode('path')->info('file: the PKCS#12 file, or the PEM certificate with key_path.')->defaultNull()->end()
                            ->scalarNode('key_path')->info('file: the PEM private key of a PEM certificate.')->defaultNull()->end()
                            ->scalarNode('password')->info('file, inline: the password of a PKCS#12 file.')->defaultNull()->end()
                            ->scalarNode('certificate')->info('inline: the PEM certificate, or the bytes of a PKCS#12 file, such as %env(SIGNING_CERTIFICATE)%.')->defaultNull()->end()
                            ->scalarNode('key')->info('inline: the PEM private key of a PEM certificate.')->defaultNull()->end()
                            ->scalarNode('vault_address')->info('vault: the address of HashiCorp Vault or OpenBao.')->defaultNull()->end()
                            ->scalarNode('vault_token')->defaultNull()->end()
                            ->scalarNode('vault_path')->info('vault: the secret holding "certificate" and "key" in PEM.')->defaultNull()->end()
                            ->scalarNode('vault_mount')->defaultValue('secret')->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static fn (array $certificate): bool => match ($certificate['type']) {
                                'file' => null === $certificate['path'],
                                'inline' => null === $certificate['certificate'],
                                'vault' => null === $certificate['vault_address'] || null === $certificate['vault_token'] || null === $certificate['vault_path'],
                                default => false,
                            })
                            ->thenInvalid('A file certificate needs its path, an inline one its certificate, a vault one its address, token and path: %s')
                        ->end()
                    ->end()
                    ->scalarNode('timestamp_url')->info('The RFC 3161 timestamp authority, such as http://timestamp.example.com.')->defaultNull()->end()
                    ->scalarNode('reason')->defaultNull()->end()
                    ->scalarNode('location')->defaultNull()->end()
                    ->scalarNode('contact_info')->defaultNull()->end()
                    ->scalarNode('name')->defaultNull()->end()
                    ->booleanNode('pades')->info('Whether signatures are PAdES, the format eIDAS recognises.')->defaultFalse()->end()
                ->end()
            ->end();
    }
}
