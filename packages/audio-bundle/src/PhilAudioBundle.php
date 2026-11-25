<?php

declare(strict_types=1);

namespace Phil\AudioBundle;

use Override;
use Phil\Audio\AudioFormat;
use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\ListeningMode;
use Phil\Audio\Rendition;
use Phil\AudioBundle\Infrastructure\DependencyInjection\AudioServices;
use Phil\AudioBundle\Infrastructure\DependencyInjection\JobsPass;
use Phil\AudioBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * phil/audio in Symfony: the audio tools from the configuration — none, or ffmpeg —, the renditions and the waveform of
 * each uploaded file produced by Messenger jobs that carry identifiers only, the creator's default listening policy,
 * and the player, a Twig component drawn with recipes of the PHIL UX kit — or the Shadcn kit —, which stays a browser
 * `<audio>` without JavaScript.
 *
 * ```yaml
 * phil_audio:
 *     tools: ffmpeg
 *     renditions:
 *         mp3: { format: mp3, bitrate: 128 }
 *         opus: { format: opus, bitrate: 64 }
 * ```
 */
final class PhilAudioBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_audio';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $formats = array_map(static fn (AudioFormat $format): string => $format->value, AudioFormat::cases());

        $definition->rootNode()
            ->children()
                ->enumNode('tools')
                    ->info('The audio tools: "null" serves the files as they were uploaded, "ffmpeg" runs ffmpeg and ffprobe for renditions, metadata and waveforms.')
                    ->values(['null', 'ffmpeg'])
                    ->defaultValue('null')
                ->end()
                ->arrayNode('ffmpeg')
                    ->info('The programs and the limits of each run, read when "tools" is "ffmpeg".')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('binary')->info('The path or the name of ffmpeg.')->cannotBeEmpty()->defaultValue('ffmpeg')->end()
                        ->scalarNode('ffprobe_binary')->info('The path or the name of ffprobe.')->cannotBeEmpty()->defaultValue('ffprobe')->end()
                        ->integerNode('timeout')->info('The seconds one run may take.')->min(1)->defaultValue(900)->end()
                        ->integerNode('max_input_bytes')->info('The bytes of a file read.')->min(1)->defaultValue(2_147_483_648)->end()
                        ->integerNode('max_output_bytes')->info('The bytes of a file written.')->min(1)->defaultValue(1_073_741_824)->end()
                        ->integerNode('max_duration')->info('The seconds of a file read.')->min(1)->defaultValue(21_600)->end()
                    ->end()
                ->end()
                ->scalarNode('temporary_directory')
                    ->info("Where the jobs write their temporary files; the system's when null.")
                    ->defaultNull()
                ->end()
                ->arrayNode('renditions')
                    ->info('The renditions produced from each uploaded file, by name: letters, digits, "_" and "-".')
                    ->useAttributeAsKey('name')
                    ->validate()
                        ->ifTrue(static fn (array $renditions): bool => [] !== array_filter(array_keys($renditions), static fn (int|string $name): bool => 1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', (string) $name)))
                        ->thenInvalid('A rendition is named with letters, digits, "_" and "-" only: %s')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $renditions): bool => [] !== array_filter($renditions, self::refused(...)))
                        ->thenInvalid('A rendition asks for what no encoder takes: %s')
                    ->end()
                    ->defaultValue(['mp3' => ['format' => 'mp3', 'bitrate' => 128, 'channels' => null, 'sample_rate' => null]])
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('format')->values($formats)->isRequired()->end()
                            ->integerNode('bitrate')->info('In kbit/s, from 8 to 512; none for flac and wav.')->defaultNull()->end()
                            ->integerNode('channels')->info('1 or 2.')->defaultNull()->end()
                            ->integerNode('sample_rate')->info('In hertz.')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('waveform')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('samples_per_pixel')
                            ->info('The samples of each run of the waveform, decoded at 8000 Hz: 800 gives ten runs a second.')
                            ->min(1)
                            ->defaultValue(800)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('listening')
                    ->info('The default policy of a creator who has not chosen: what a listener who has not bought a track may hear.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('mode')
                            ->values(array_map(static fn (ListeningMode $mode): string => $mode->value, ListeningMode::cases()))
                            ->defaultValue(ListeningMode::Full->value)
                        ->end()
                        ->integerNode('excerpt_start')->info('The second the excerpt starts at.')->min(0)->defaultValue(0)->end()
                        ->integerNode('excerpt_duration')->info('The seconds of the excerpt.')->min(1)->defaultValue(30)->end()
                    ->end()
                ->end()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('files')
                            ->info("The service, an AudioFiles, that gives the jobs a local copy of a file; phil/media-bundle's library when null and installed.")
                            ->defaultNull()
                        ->end()
                        ->scalarNode('renditions')
                            ->info("The service, a RenditionStore, that keeps renditions and waveforms; phil/media-bundle's storage when null and installed.")
                            ->defaultNull()
                        ->end()
                        ->scalarNode('prefix')
                            ->info('The folder of the renditions in the storage of phil/media-bundle.')
                            ->cannotBeEmpty()
                            ->defaultValue('audio')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    /**
     * Whether a rendition of the configuration is refused by phil/audio: a bitrate for a lossless format, a sample rate
     * the format does not take.
     *
     * @param mixed $rendition a rendition of the tree, normalised by its prototype
     */
    private static function refused(mixed $rendition): bool
    {
        /** @var array{format: string, bitrate: ?int, channels: ?int, sample_rate: ?int} $rendition */
        try {
            new Rendition(AudioFormat::from($rendition['format']), $rendition['bitrate'], $rendition['channels'], $rendition['sample_rate']);

            return false;
        } catch (InvalidAudio) {
            return true;
        }
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new KitSubdirectoryPass());
        // Priority 1: before the MessengerPass of symfony/messenger (priority 0, from a bundle registered before this one), which wires the handlers and middleware this pass may remove or add.
        $container->addCompilerPass(new JobsPass(), priority: 1);
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{tools: string, ffmpeg: array{binary: string, ffprobe_binary: string, timeout: int, max_input_bytes: int, max_output_bytes: int, max_duration: int}, temporary_directory: ?string, renditions: array<string, array{format: string, bitrate: ?int, channels: ?int, sample_rate: ?int}>, waveform: array{samples_per_pixel: int}, listening: array{mode: string, excerpt_start: int, excerpt_duration: int}, storage: array{files: ?string, renditions: ?string, prefix: string}, kit_subdirectory: ?string} $config */
        AudioServices::register($builder, $config);
    }
}
