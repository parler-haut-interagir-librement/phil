<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Unit;

use Phil\Audio\AudioFormat;
use Phil\Audio\Contract\MetadataReader;
use Phil\Audio\Contract\PeaksGenerator;
use Phil\Audio\Contract\Transcoder;
use Phil\Audio\Excerpt;
use Phil\Audio\Ffmpeg\FfmpegAudio;
use Phil\Audio\Ffmpeg\FfmpegLimits;
use Phil\Audio\ListeningMode;
use Phil\Audio\ListeningPolicy;
use Phil\Audio\NullAudio;
use Phil\Audio\Rendition;
use Phil\AudioBundle\Application\AudioJobs;
use Phil\AudioBundle\Application\Contract\AudioFiles;
use Phil\AudioBundle\Application\Contract\RenditionStore;
use Phil\AudioBundle\Application\Handler\ComputeWaveformHandler;
use Phil\AudioBundle\Application\Handler\TranscodeAudioHandler;
use Phil\AudioBundle\Application\Renditions;
use Phil\AudioBundle\Infrastructure\DependencyInjection\AudioServices;
use Phil\AudioBundle\Infrastructure\DependencyInjection\JobsPass;
use Phil\AudioBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\AudioBundle\Infrastructure\MediaBundle\MediaLibraryAudioFiles;
use Phil\AudioBundle\Infrastructure\MediaBundle\MediaStorageRenditions;
use Phil\AudioBundle\Infrastructure\Twig\PlayerComponent;
use Phil\AudioBundle\PhilAudioBundle;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\MessengerBundle;

#[CoversClass(PhilAudioBundle::class)]
#[CoversClass(AudioServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(JobsPass::class)]
final class PhilAudioBundleTest extends TestCase
{
    private function extension(): ExtensionInterface
    {
        $extension = new PhilAudioBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    public function testTheExtensionAliasIsPhilAudio(): void
    {
        self::assertSame('phil_audio', $this->extension()->getAlias());
    }

    public function testNoToolIsTheDefault(): void
    {
        $container = $this->load([]);

        self::assertSame(NullAudio::class, $container->getDefinition('phil_audio.tools')->getClass());

        foreach ([Transcoder::class, MetadataReader::class, PeaksGenerator::class] as $port) {
            self::assertSame('phil_audio.tools', (string) $container->getAlias($port));
            self::assertTrue($container->getAlias($port)->isPublic());
        }

        self::assertEquals([['mp3' => new Definition(Rendition::class, [AudioFormat::Mp3, 128, null, null])]], $container->getDefinition(Renditions::class)->getArguments());
        self::assertSame([ListeningMode::Full, null], $container->getDefinition('phil_audio.listening_policy')->getArguments());
        self::assertSame('phil_audio.listening_policy', (string) $container->getAlias(ListeningPolicy::class));
        self::assertSame([null], $container->getDefinition(PlayerComponent::class)->getArguments());
        self::assertFalse($container->getDefinition(PlayerComponent::class)->isShared());
        self::assertTrue($container->getDefinition(PlayerComponent::class)->isAutoconfigured());
    }

    public function testFfmpegRunsWithTheLimitsOfTheConfiguration(): void
    {
        $container = $this->load([
            'tools' => 'ffmpeg',
            'ffmpeg' => ['binary' => '/opt/ffmpeg', 'ffprobe_binary' => '/opt/ffprobe', 'timeout' => 60, 'max_input_bytes' => 10, 'max_output_bytes' => 20, 'max_duration' => 30],
            'temporary_directory' => '/var/tmp/audio',
        ]);
        $tools = $container->getDefinition('phil_audio.tools');

        self::assertSame(FfmpegAudio::class, $tools->getClass());
        self::assertSame(['/opt/ffmpeg', '/opt/ffprobe'], [$tools->getArgument(1), $tools->getArgument(2)]);
        self::assertEquals(new Reference('phil_audio.ffmpeg.limits'), $tools->getArgument(3));
        self::assertSame('/var/tmp/audio', $tools->getArgument(5));
        self::assertSame([60.0, 10, 20, 30_000], $container->getDefinition('phil_audio.ffmpeg.limits')->getArguments());
        self::assertSame(FfmpegLimits::class, $container->getDefinition('phil_audio.ffmpeg.limits')->getClass());

        $defaults = $this->load(['tools' => 'ffmpeg']);
        self::assertSame([900.0, 2_147_483_648, 1_073_741_824, 21_600_000], $defaults->getDefinition('phil_audio.ffmpeg.limits')->getArguments());
        self::assertSame(['ffmpeg', 'ffprobe', null], [$defaults->getDefinition('phil_audio.tools')->getArgument(1), $defaults->getDefinition('phil_audio.tools')->getArgument(2), $defaults->getDefinition('phil_audio.tools')->getArgument(5)]);
    }

    public function testRenditionsAndAnExcerptPolicyAreConfigured(): void
    {
        $container = $this->load([
            'renditions' => ['opus_64' => ['format' => 'opus', 'bitrate' => 64, 'channels' => 1, 'sample_rate' => 48_000], 'flac' => ['format' => 'flac']],
            'listening' => ['mode' => 'excerpt', 'excerpt_start' => 15, 'excerpt_duration' => 45],
            'kit_subdirectory' => 'ui',
        ]);

        self::assertEquals([[
            'opus_64' => new Definition(Rendition::class, [AudioFormat::Opus, 64, 1, 48_000]),
            'flac' => new Definition(Rendition::class, [AudioFormat::Flac, null, null, null]),
        ]], $container->getDefinition(Renditions::class)->getArguments());
        self::assertEquals([ListeningMode::Excerpt, new Definition(Excerpt::class, [15_000, 45_000])], $container->getDefinition('phil_audio.listening_policy')->getArguments());
        self::assertSame(['ui'], $container->getDefinition(PlayerComponent::class)->getArguments());
        self::assertSame([ListeningMode::PurchaseOnly, null], $this->load(['listening' => ['mode' => 'purchase_only']])->getDefinition('phil_audio.listening_policy')->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'unknown tool' => [['tools' => 'sox']];
        yield 'rendition name' => [['renditions' => ['bad name' => ['format' => 'mp3']]]];
        yield 'lossless bitrate' => [['renditions' => ['flac' => ['format' => 'flac', 'bitrate' => 128]]]];
        yield 'opus rate' => [['renditions' => ['o' => ['format' => 'opus', 'sample_rate' => 44_100]]]];
        yield 'format' => [['renditions' => ['o' => ['format' => 'ogg']]]];
        yield 'no excerpt' => [['listening' => ['excerpt_duration' => 0]]];
        yield 'no timeout' => [['ffmpeg' => ['timeout' => 0]]];
        yield 'no input' => [['ffmpeg' => ['max_input_bytes' => 0]]];
        yield 'no output' => [['ffmpeg' => ['max_output_bytes' => 0]]];
        yield 'no duration' => [['ffmpeg' => ['max_duration' => 0]]];
        yield 'no run' => [['waveform' => ['samples_per_pixel' => 0]]];
        yield 'excerpt before the start' => [['listening' => ['excerpt_start' => -1]]];
        yield 'rendition name ends with a line feed' => [['renditions' => ["mp3\n" => ['format' => 'mp3']]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testAConfigurationNoToolCanFollowIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    public function testTheMediaBundleKeepsTheFilesAndRenditionsByDefault(): void
    {
        $container = $this->load(['storage' => ['prefix' => 'sounds'], 'temporary_directory' => '/t']);

        self::assertSame(MediaLibraryAudioFiles::class, $container->getDefinition(AudioFiles::class)->getClass());
        self::assertEquals([new Reference(MediaRepository::class), new Reference(MediaLibrary::class), '/t'], $container->getDefinition(AudioFiles::class)->getArguments());
        self::assertSame(MediaStorageRenditions::class, $container->getDefinition(RenditionStore::class)->getClass());
        self::assertEquals(new Reference(MediaStorage::class), $container->getDefinition(RenditionStore::class)->getArgument(0));
        self::assertSame('sounds', $container->getDefinition(RenditionStore::class)->getArgument(1));
        self::assertTrue($container->getDefinition(AudioFiles::class)->isPublic());
        self::assertTrue($container->getDefinition(RenditionStore::class)->isPublic());
    }

    public function testTheJobsAreRegisteredWithTheirServices(): void
    {
        $container = $this->load(['tools' => 'ffmpeg', 'waveform' => ['samples_per_pixel' => 400], 'temporary_directory' => '/t']);

        self::assertEquals([new Reference('messenger.default_bus'), new Reference(Renditions::class), true], $container->getDefinition(AudioJobs::class)->getArguments());
        self::assertTrue($container->getDefinition(AudioJobs::class)->isPublic());
        self::assertEquals([new Reference(AudioFiles::class), new Reference(RenditionStore::class), new Reference(Transcoder::class), new Reference(Renditions::class), '/t'], $container->getDefinition(TranscodeAudioHandler::class)->getArguments());
        self::assertEquals([new Reference(AudioFiles::class), new Reference(RenditionStore::class), new Reference(PeaksGenerator::class), 400], $container->getDefinition(ComputeWaveformHandler::class)->getArguments());
        self::assertTrue($container->getDefinition(TranscodeAudioHandler::class)->hasTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(ComputeWaveformHandler::class)->hasTag('messenger.message_handler'));
        self::assertFalse($this->load([])->getDefinition(AudioJobs::class)->getArgument(2));
    }

    public function testServicesOfTheApplicationReplaceTheStorage(): void
    {
        $container = $this->load(['storage' => ['files' => 'app.files', 'renditions' => 'app.renditions']]);

        self::assertSame('app.files', (string) $container->getAlias(AudioFiles::class));
        self::assertSame('app.renditions', (string) $container->getAlias(RenditionStore::class));
        self::assertTrue($container->getAlias(AudioFiles::class)->isPublic());
        self::assertTrue($container->getAlias(RenditionStore::class)->isPublic());
    }

    public function testThePassesRemoveTheJobsWithoutABusAndNameTheKit(): void
    {
        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'shadcn');
        new KitSubdirectoryPass()->process($container);
        new JobsPass()->process($container);

        self::assertSame(['shadcn'], $container->getDefinition(PlayerComponent::class)->getArguments());
        self::assertFalse($container->hasDefinition(AudioJobs::class));
        self::assertFalse($container->hasDefinition(TranscodeAudioHandler::class));
        self::assertFalse($container->hasDefinition(ComputeWaveformHandler::class));

        $bus = $this->load(['kit_subdirectory' => 'ui']);
        $bus->register('messenger.default_bus', stdClass::class);
        new JobsPass()->process($bus);
        new KitSubdirectoryPass()->process($bus);
        self::assertTrue($bus->hasDefinition(AudioJobs::class));
        self::assertSame(['ui'], $bus->getDefinition(PlayerComponent::class)->getArguments());

        $none = $this->load([]);
        new KitSubdirectoryPass()->process($none);
        self::assertSame([''], $none->getDefinition(PlayerComponent::class)->getArguments());

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(PlayerComponent::class));
    }

    public function testThePassesAreAdded(): void
    {
        $container = new ContainerBuilder();
        new PhilAudioBundle()->build($container);
        $classes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(KitSubdirectoryPass::class, $classes);
        self::assertContains(JobsPass::class, $classes);
    }

    public function testTheLowestLimitsAreAccepted(): void
    {
        $container = $this->load([
            'tools' => 'null',
            'ffmpeg' => ['timeout' => 1, 'max_input_bytes' => 1, 'max_output_bytes' => 1, 'max_duration' => 1],
            'waveform' => ['samples_per_pixel' => 1],
            'listening' => ['mode' => 'excerpt', 'excerpt_start' => 0, 'excerpt_duration' => 1],
        ]);

        self::assertEquals([ListeningMode::Excerpt, new Definition(Excerpt::class, [0, 1_000])], $container->getDefinition('phil_audio.listening_policy')->getArguments());
        self::assertSame([1.0, 1, 1, 1_000], $this->load(['tools' => 'ffmpeg', 'ffmpeg' => ['timeout' => 1, 'max_input_bytes' => 1, 'max_output_bytes' => 1, 'max_duration' => 1]])->getDefinition('phil_audio.ffmpeg.limits')->getArguments());
    }

    public function testTheDefaultsAreAnEightHundredSampleRunAndAThirtySecondExcerptFromTheStart(): void
    {
        $container = $this->load(['tools' => 'ffmpeg']);

        self::assertSame(800, $container->getDefinition(ComputeWaveformHandler::class)->getArgument(3));
        self::assertEquals([ListeningMode::Excerpt, new Definition(Excerpt::class, [0, 30_000])], $this->load(['listening' => ['mode' => 'excerpt']])->getDefinition('phil_audio.listening_policy')->getArguments());
    }

    public function testTheServicesOtherPartsReadArePublic(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getDefinition(Renditions::class)->isPublic());
        self::assertTrue($container->getDefinition('phil_audio.listening_policy')->isPublic());
        self::assertTrue($container->getAlias(ListeningPolicy::class)->isPublic());
    }

    public function testTheHandlersAreRemovedBeforeMessengerWiresThem(): void
    {
        $container = new ContainerBuilder();
        new MessengerBundle()->build($container);
        new PhilAudioBundle()->build($container);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $messenger = array_search(MessengerPass::class, $passes, true);
        self::assertIsInt($messenger);

        $removal = array_search(JobsPass::class, $passes, true);
        self::assertIsInt($removal);
        self::assertLessThan($messenger, $removal);
    }
}
