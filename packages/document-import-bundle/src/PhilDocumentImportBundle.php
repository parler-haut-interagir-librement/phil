<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle;

use Override;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\StyleRole;
use Phil\DocumentImportBundle\Application\Message\ImportDocument;
use Phil\DocumentImportBundle\Infrastructure\DependencyInjection\DocumentImportServices;
use Phil\SvgSanitizer\Policy;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * `phil/document-import` in a Symfony application: the `DocumentImporter` and `DocumentExporter` services with the
 * application's limits, style map and media, the policy of the sanitizer every SVG image goes through, the store the
 * images go to — the media library of `phil/media-bundle` when that bundle is registered, a folder prefix otherwise —,
 * `DocumentImports`, which reads and writes only the formats the application allows, has each file scanned by
 * `phil/upload-scan-bundle` before it is parsed when that bundle is registered, and tells the application what each
 * import left out — as an event, a block, or the `@PhilDocumentImport/report.html.twig` fragment —, and, on demand,
 * `DocumentImportQueue`, which imports on a Messenger worker with messages that carry a claim and nothing else.
 */
final class PhilDocumentImportBundle extends AbstractBundle
{
    /** The formats an export writes. */
    public const array EXPORTS = ['docx', 'odt'];

    /** The longest name of a style. */
    private const int STYLE_LENGTH = 255;

    #[Override]
    protected string $extensionAlias = 'phil_document_import';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('media')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('store')
                            ->info('Where the images go: auto keeps them in phil/media-bundle when it is registered, under the prefix otherwise.')
                            ->values(['auto', 'library', 'folder'])
                            ->defaultValue('auto')
                        ->end()
                        ->scalarNode('prefix')
                            ->info('The address of each image under the folder store, before its file name; the application writes the bytes there.')
                            ->defaultValue('media/')
                        ->end()
                        ->scalarNode('collection')
                            ->info('The key of the media library collection the images go to, created the first time; none by default.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $key): bool => !\is_string($key) || 1 !== preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $key))
                                ->thenInvalid('A collection key is 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter; %s is not.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('formats')
                    ->info('The formats the application takes in and gives out; a format left out is refused before it is read.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('import')
                            ->info('The formats an import reads.')
                            ->enumPrototype()->values($this->values(Format::cases()))->end()
                            ->defaultValue($this->values(Format::cases()))
                        ->end()
                        ->arrayNode('export')
                            ->info('The formats an export writes.')
                            ->enumPrototype()->values(self::EXPORTS)->end()
                            ->defaultValue(self::EXPORTS)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('limits')
                    ->info('How much a document may hold once decoded, in bytes, how deep it may nest, and how many objects a PDF may declare.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('entry_bytes')->min(1)->defaultValue(64 * 1024 * 1024)->end()
                        ->integerNode('total_bytes')->min(1)->defaultValue(256 * 1024 * 1024)->end()
                        ->integerNode('depth')->min(1)->defaultValue(100)->end()
                        ->integerNode('objects')->min(1)->defaultValue(500_000)->end()
                    ->end()
                ->end()
                ->arrayNode('styles')
                    ->info("The block each paragraph style becomes, by the name the word processor shows or the identifier of the style, read before the importer's own rules.")
                    ->useAttributeAsKey('style')
                    ->normalizeKeys(false)
                    ->enumPrototype()->values($this->values(StyleRole::cases()))->end()
                    ->validate()
                        ->ifTrue(static fn (array $styles): bool => [] !== array_filter(array_keys($styles), static fn (int|string $style): bool => '' === trim((string) $style) || mb_strlen((string) $style) > self::STYLE_LENGTH))
                        ->thenInvalid('A style is named by 1 to 255 characters other than spaces.')
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('report')
                    ->info('The report of what an import left out, as a block added to the document.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('title')
                            ->info('The title of the report block.')
                            ->defaultValue('What was not imported')
                            ->validate()
                                ->ifTrue(static fn (mixed $title): bool => !\is_string($title) || '' === trim($title))
                                ->thenInvalid('The title of the report is a text; %s is not.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->enumNode('scan')
                    ->info('Whether each file goes through the scanner of phil/upload-scan-bundle before it is parsed: auto when that bundle is registered.')
                    ->values(['auto', 'on', 'off'])
                    ->defaultValue('auto')
                ->end()
                ->arrayNode('async')
                    ->info('The imports on a Messenger worker: the file waits in a store, and the message carries its claim alone.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('transport')
                            ->info('The Messenger transport ImportDocument is routed to; null leaves its routing to the application.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $transport): bool => null !== $transport && (!\is_string($transport) || 1 !== preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $transport)))
                                ->thenInvalid('The transport is the name of a Messenger transport, such as async: %s.')
                            ->end()
                        ->end()
                        ->scalarNode('directory')
                            ->info('The directory the files wait in, outside the web root; the default store only.')
                            ->defaultValue('%kernel.project_dir%/var/document-imports')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('store')
                            ->info('The identifier of a service implementing Phil\\DocumentImportBundle\\Application\\Contract\\PendingImports, in place of the directory.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('export')
                    ->info('Where the exporter finds the images a document shows, and where it writes its archives.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('media_source')
                            ->info('The identifier of a service implementing Phil\\DocumentImport\\Contract\\MediaSource; without one, images are reported and left out.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('temporary_directory')
                            ->info("The directory each archive is written in before it is read back; the system's by default.")
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('svg')
                    ->info('The policy of the sanitizer every SVG image goes through before it is stored.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_bytes')->min(1)->defaultValue(4_194_304)->end()
                        ->integerNode('max_depth')->min(1)->defaultValue(64)->end()
                        ->integerNode('max_elements')->min(1)->defaultValue(50_000)->end()
                        ->booleanNode('data_images')
                            ->info('Whether an SVG image may carry PNG, JPEG, GIF, WebP and AVIF images in data: addresses.')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('animations')
                            ->info('Whether SMIL animation is kept.')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('link_schemes')
                            ->info('The schemes the links of an SVG image may use.')
                            ->scalarPrototype()
                                ->validate()
                                    ->ifTrue(static fn (mixed $scheme): bool => !\is_string($scheme) || 1 !== preg_match('/^[a-z][a-z0-9+.-]*$/D', $scheme) || \in_array($scheme, Policy::DENIED_SCHEMES, true))
                                    ->thenInvalid('A link scheme is written in lower case, and never one that runs code or holds a document; %s is not allowed.')
                                ->end()
                            ->end()
                            ->defaultValue(Policy::DEFAULT_LINK_SCHEMES)
                        ->end()
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
        /** @var array{media: array{store: string, prefix: string, collection: ?string}, formats: array{import: list<string>, export: list<string>}, limits: array{entry_bytes: int, total_bytes: int, depth: int, objects: int}, styles: array<string, string>, report: array{title: string}, scan: string, async: array{enabled: bool, transport: ?string, directory: string, store: ?string}, export: array{media_source: ?string, temporary_directory: ?string}, svg: array{max_bytes: int, max_depth: int, max_elements: int, data_images: bool, animations: bool, link_schemes: list<string>}} $config */
        $media = $this->registered($builder, 'phil_media', 'PhilMediaBundle');

        if ('library' === $config['media']['store'] && !$media) {
            throw new InvalidConfigurationException('The store "library" keeps the images in phil/media-bundle, which is not registered: register PhilMediaBundle, or choose "folder".');
        }

        $scanner = $this->registered($builder, 'phil_upload_scan', 'PhilUploadScanBundle');

        if ('on' === $config['scan'] && !$scanner) {
            throw new InvalidConfigurationException('The scan "on" goes through phil/upload-scan-bundle, which is not registered: register PhilUploadScanBundle, or choose "off".');
        }

        if ($config['async']['enabled'] && !interface_exists(MessageBusInterface::class)) {
            throw new InvalidConfigurationException('The imports on a worker go through symfony/messenger, which is not installed: install it, or leave async disabled.');
        }

        DocumentImportServices::register($builder, $config['media'], $config['limits'], $config['svg'], 'folder' !== $config['media']['store'] && $media);
        DocumentImportServices::registerImports($builder, $config['formats'], $config['styles'], $config['report']['title'], $config['export'], 'off' !== $config['scan'] && $scanner);

        if ($config['async']['enabled']) {
            DocumentImportServices::registerQueue($builder, $config['async']);
        }
    }

    /**
     * Routes `ImportDocument` to the transport `async.transport` names, before FrameworkBundle reads its routing: the
     * application's own routing of the message, given after, still wins.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('framework')) {
            return;
        }

        $transport = null;

        foreach ($builder->getExtensionConfig('phil_document_import') as $config) {
            $async = \is_array($config) ? $config['async'] ?? null : null;

            if (\is_array($async) && \is_string($async['transport'] ?? null)) {
                $transport = $async['transport'];
            }
        }

        if (null !== $transport) {
            $builder->prependExtensionConfig('framework', ['messenger' => ['routing' => [ImportDocument::class => $transport]]]);
        }
    }

    /**
     * Whether another bundle is registered: in the application by its name in the kernel's list of bundles, since an
     * extension is loaded in a container of its own, or in a container built by hand by its extension.
     */
    private function registered(ContainerBuilder $builder, string $extension, string $bundle): bool
    {
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];

        return $builder->hasExtension($extension) || (\is_array($bundles) && \array_key_exists($bundle, $bundles));
    }

    /**
     * @param list<Format>|list<StyleRole> $cases
     *
     * @return list<string>
     */
    private function values(array $cases): array
    {
        return array_map(static fn (Format|StyleRole $case): string => $case->value, $cases);
    }
}
