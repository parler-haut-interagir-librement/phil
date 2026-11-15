<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Infrastructure\DependencyInjection;

use Phil\Audio\AudioFormat;
use Phil\Audio\Contract\MetadataReader;
use Phil\Audio\Contract\PeaksGenerator;
use Phil\Audio\Contract\Transcoder;
use Phil\Audio\Excerpt;
use Phil\Audio\Ffmpeg\FfmpegAudio;
use Phil\Audio\Ffmpeg\FfmpegLimits;
use Phil\Audio\Ffmpeg\ProcessCommandRunner;
use Phil\Audio\ListeningMode;
use Phil\Audio\ListeningPolicy;
use Phil\Audio\NullAudio;
use Phil\Audio\PeaksComputer;
use Phil\Audio\PeaksReader;
use Phil\Audio\Rendition;
use Phil\AudioBundle\Application\AudioJobs;
use Phil\AudioBundle\Application\Contract\AudioFiles;
use Phil\AudioBundle\Application\Contract\RenditionStore;
use Phil\AudioBundle\Application\Handler\ComputeWaveformHandler;
use Phil\AudioBundle\Application\Handler\TranscodeAudioHandler;
use Phil\AudioBundle\Application\Renditions;
use Phil\AudioBundle\Infrastructure\MediaBundle\MediaLibraryAudioFiles;
use Phil\AudioBundle\Infrastructure\MediaBundle\MediaStorageRenditions;
use Phil\AudioBundle\Infrastructure\Twig\PlayerComponent;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The services of the bundle, from its processed configuration.
 *
 * @phpstan-type Config array{
 *     tools: string,
 *     ffmpeg: array{binary: string, ffprobe_binary: string, timeout: int, max_input_bytes: int, max_output_bytes: int, max_duration: int},
 *     temporary_directory: ?string,
 *     renditions: array<string, array{format: string, bitrate: ?int, channels: ?int, sample_rate: ?int}>,
 *     waveform: array{samples_per_pixel: int},
 *     listening: array{mode: string, excerpt_start: int, excerpt_duration: int},
 *     storage: array{files: ?string, renditions: ?string, prefix: string},
 *     kit_subdirectory: ?string,
 * }
 */
final class AudioServices
{
    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $temporary = $config['temporary_directory'];

        if ('ffmpeg' === $config['tools']) {
            $ffmpeg = $config['ffmpeg'];
            $container->register('phil_audio.ffmpeg.limits', FfmpegLimits::class)
                ->setArguments([(float) $ffmpeg['timeout'], $ffmpeg['max_input_bytes'], $ffmpeg['max_output_bytes'], $ffmpeg['max_duration'] * 1000]);
            $container->register('phil_audio.tools', FfmpegAudio::class)
                ->setArguments([new Definition(ProcessCommandRunner::class), $ffmpeg['binary'], $ffmpeg['ffprobe_binary'], new Reference('phil_audio.ffmpeg.limits'), new Definition(PeaksComputer::class), $temporary]);
        } else {
            $container->register('phil_audio.tools', NullAudio::class);
        }

        foreach ([Transcoder::class, MetadataReader::class, PeaksGenerator::class] as $port) {
            $container->setAlias($port, 'phil_audio.tools')->setPublic(true);
        }

        $renditions = [];

        foreach ($config['renditions'] as $name => $rendition) {
            $renditions[$name] = new Definition(Rendition::class, [AudioFormat::from($rendition['format']), $rendition['bitrate'], $rendition['channels'], $rendition['sample_rate']]);
        }

        $container->register(Renditions::class, Renditions::class)->setArguments([$renditions])->setPublic(true);

        $listening = $config['listening'];
        $mode = ListeningMode::from($listening['mode']);
        $container->register('phil_audio.listening_policy', ListeningPolicy::class)
            ->setArguments([$mode, ListeningMode::Excerpt === $mode ? new Definition(Excerpt::class, [$listening['excerpt_start'] * 1000, $listening['excerpt_duration'] * 1000]) : null])
            ->setPublic(true);
        $container->setAlias(ListeningPolicy::class, 'phil_audio.listening_policy')->setPublic(true);

        $stored = self::storage($container, $config['storage'], $temporary);

        if ($stored && interface_exists(MessageBusInterface::class)) {
            self::jobs($container, $config, $temporary);
        }

        if (class_exists(AsTwigComponent::class)) {
            $container->register(PlayerComponent::class, PlayerComponent::class)
                // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
                ->setArguments([$config['kit_subdirectory']])
                ->setShared(false)
                ->setAutoconfigured(true);
        }
    }

    /**
     * @param array{files: ?string, renditions: ?string, prefix: string} $storage
     *
     * @return bool whether both the files and the store of the renditions are known
     */
    private static function storage(ContainerBuilder $container, array $storage, ?string $temporary): bool
    {
        $media = interface_exists(MediaRepository::class) && class_exists(MediaLibrary::class);

        if (null !== $storage['files']) {
            $container->setAlias(AudioFiles::class, $storage['files'])->setPublic(true);
        } elseif ($media) {
            $container->register(AudioFiles::class, MediaLibraryAudioFiles::class)
                ->setArguments([new Reference(MediaRepository::class), new Reference(MediaLibrary::class), $temporary])
                ->setPublic(true);
        }

        if (null !== $storage['renditions']) {
            $container->setAlias(RenditionStore::class, $storage['renditions'])->setPublic(true);
        } elseif ($media) {
            $container->register(RenditionStore::class, MediaStorageRenditions::class)
                ->setArguments([new Reference(MediaStorage::class), $storage['prefix'], new Definition(PeaksReader::class)])
                ->setPublic(true);
        }

        return $container->has(AudioFiles::class) && $container->has(RenditionStore::class);
    }

    /**
     * @param Config $config
     */
    private static function jobs(ContainerBuilder $container, array $config, ?string $temporary): void
    {
        $container->register(AudioJobs::class, AudioJobs::class)
            ->setArguments([new Reference('messenger.default_bus'), new Reference(Renditions::class), 'ffmpeg' === $config['tools']])
            ->setPublic(true);
        $container->register(TranscodeAudioHandler::class, TranscodeAudioHandler::class)
            ->setArguments([new Reference(AudioFiles::class), new Reference(RenditionStore::class), new Reference(Transcoder::class), new Reference(Renditions::class), $temporary])
            ->addTag('messenger.message_handler');
        $container->register(ComputeWaveformHandler::class, ComputeWaveformHandler::class)
            ->setArguments([new Reference(AudioFiles::class), new Reference(RenditionStore::class), new Reference(PeaksGenerator::class), $config['waveform']['samples_per_pixel']])
            ->addTag('messenger.message_handler');
    }
}
