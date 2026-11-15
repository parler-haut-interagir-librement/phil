<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Unit;

use Override;
use Phil\Audio\AudioFormat;
use Phil\Audio\Exception\AudioToolFailed;
use Phil\Audio\Exception\AudioToolUnavailable;
use Phil\Audio\Exception\LimitExceeded;
use Phil\Audio\Excerpt;
use Phil\Audio\Peaks;
use Phil\Audio\Rendition;
use Phil\AudioBundle\Application\AudioJobs;
use Phil\AudioBundle\Application\Exception\UnknownRendition;
use Phil\AudioBundle\Application\Handler\ComputeWaveformHandler;
use Phil\AudioBundle\Application\Handler\TranscodeAudioHandler;
use Phil\AudioBundle\Application\Message\ComputeWaveform;
use Phil\AudioBundle\Application\Message\TranscodeAudio;
use Phil\AudioBundle\Application\Renditions;
use Phil\AudioBundle\Tests\Support\MemoryAudio;
use Phil\AudioBundle\Tests\Support\MemoryTools;
use Phil\AudioBundle\Tests\Support\RecordingBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[CoversClass(AudioJobs::class)]
#[CoversClass(Renditions::class)]
#[CoversClass(TranscodeAudio::class)]
#[CoversClass(ComputeWaveform::class)]
#[CoversClass(TranscodeAudioHandler::class)]
#[CoversClass(ComputeWaveformHandler::class)]
final class JobsTest extends TestCase
{
    private string $directory;

    private MemoryAudio $storage;

    private Renditions $renditions;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-audio-bundle-'.sodium_bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->storage = new MemoryAudio($this->directory);
        $this->storage->uploads['m1'] = 'sound';
        $this->renditions = new Renditions(['mp3' => new Rendition(AudioFormat::Mp3, 128), 'opus' => new Rendition(AudioFormat::Opus)]);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');
        array_map(unlink(...), false === $files ? [] : $files);
        rmdir($this->directory);
    }

    private function bus(): RecordingBus
    {
        return new RecordingBus();
    }

    public function testAnUploadSchedulesEachRenditionAndTheWaveform(): void
    {
        $bus = $this->bus();
        new AudioJobs($bus, $this->renditions)->schedule('m1');

        self::assertEquals([new TranscodeAudio('m1', 'mp3'), new TranscodeAudio('m1', 'opus'), new ComputeWaveform('m1')], $bus->messages);
    }

    public function testAnExcerptSchedulesTheSameRenditionsOfIt(): void
    {
        $bus = $this->bus();
        new AudioJobs($bus, $this->renditions)->schedule('m1', new Excerpt(15_000, 30_000));

        self::assertEquals([
            new TranscodeAudio('m1', 'mp3'),
            new TranscodeAudio('m1', 'mp3', 15_000, 30_000),
            new TranscodeAudio('m1', 'opus'),
            new TranscodeAudio('m1', 'opus', 15_000, 30_000),
            new ComputeWaveform('m1'),
        ], $bus->messages);
    }

    public function testWithoutAToolNothingIsScheduled(): void
    {
        $bus = $this->bus();
        new AudioJobs($bus, $this->renditions, false)->schedule('m1', new Excerpt(0, 1));

        self::assertSame([], $bus->messages);
    }

    public function testRenditionsAreFoundByName(): void
    {
        self::assertSame(['mp3', 'opus'], $this->renditions->names());
        self::assertSame(128, $this->renditions->get('mp3')->bitrate);

        $this->expectException(UnknownRendition::class);
        $this->expectExceptionMessageIsOrContains('No rendition is named "flac".');
        $this->renditions->get('flac');
    }

    public function testARenditionIsTranscodedKeptAndItsTemporaryFilesDeleted(): void
    {
        $tools = new MemoryTools();
        new TranscodeAudioHandler($this->storage, $this->storage, $tools, $this->renditions, $this->directory.'/')(new TranscodeAudio('m1', 'opus'));

        self::assertSame(['m1/opus.opus' => 'SOUND'], $this->storage->kept);
        self::assertSame([$this->directory.'/copy-m1'], $this->storage->released);
        self::assertSame($this->renditions->get('opus'), $tools->transcoded[0]['rendition']);
        self::assertNull($tools->transcoded[0]['excerpt']);
        self::assertMatchesRegularExpression('#^'.preg_quote($this->directory, '#').'/phil-audio-[0-9a-f]{16}\.opus$#', $tools->transcoded[0]['target']);
        self::assertSame([], glob($this->directory.'/*'));
    }

    public function testAnExcerptIsTranscodedAndKeptAsSuch(): void
    {
        $tools = new MemoryTools();
        new TranscodeAudioHandler($this->storage, $this->storage, $tools, $this->renditions, $this->directory)(new TranscodeAudio('m1', 'mp3', 15_000, 30_000));

        self::assertEquals(new Excerpt(15_000, 30_000), $tools->transcoded[0]['excerpt']);
        self::assertSame(['m1/mp3-excerpt.mp3' => 'SOUND'], $this->storage->kept);
    }

    public function testHalfAnExcerptIsTheWholeFile(): void
    {
        foreach ([new TranscodeAudio('m1', 'mp3', 15_000), new TranscodeAudio('m1', 'mp3', null, 30_000)] as $message) {
            $tools = new MemoryTools();
            new TranscodeAudioHandler($this->storage, $this->storage, $tools, $this->renditions, $this->directory)($message);
            self::assertNull($tools->transcoded[0]['excerpt']);
        }
    }

    public function testTheSystemTemporaryDirectoryIsTheDefault(): void
    {
        $tools = new MemoryTools();
        new TranscodeAudioHandler($this->storage, $this->storage, $tools, $this->renditions)(new TranscodeAudio('m1', 'mp3'));

        self::assertStringStartsWith(rtrim(sys_get_temp_dir(), '/').'/phil-audio-', $tools->transcoded[0]['target']);
        self::assertFileDoesNotExist($tools->transcoded[0]['target']);
    }

    public function testAJobThatCannotSucceedIsNotRetried(): void
    {
        $messages = [
            'gone' => new TranscodeAudio('m2', 'mp3'),
            'rendition' => new TranscodeAudio('m1', 'flac'),
            'excerpt' => new TranscodeAudio('m1', 'mp3', -1, 10),
        ];

        foreach ($messages as $case => $message) {
            try {
                new TranscodeAudioHandler($this->storage, $this->storage, new MemoryTools(), $this->renditions, $this->directory)($message);
                self::fail('The job '.$case.' fails for good.');
            } catch (UnrecoverableMessageHandlingException $exception) {
                self::assertNotNull($exception->getPrevious());
                self::assertSame($exception->getPrevious()->getMessage(), $exception->getMessage());
            }
        }

        self::assertSame([], $this->storage->released);
    }

    public function testAFileTheToolRefusesIsNotRetriedAndItsFilesAreDeleted(): void
    {
        $tools = new MemoryTools(static function (string $source, string $target): never {
            file_put_contents($target, 'half');

            throw new AudioToolFailed('refused');
        });

        try {
            new TranscodeAudioHandler($this->storage, $this->storage, $tools, $this->renditions, $this->directory)(new TranscodeAudio('m1', 'mp3'));
            self::fail('A refused file fails for good.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertSame('refused', $unrecoverableMessageHandlingException->getMessage());
            self::assertInstanceOf(AudioToolFailed::class, $unrecoverableMessageHandlingException->getPrevious());
        }

        self::assertSame([], $this->storage->kept);
        self::assertCount(1, $this->storage->released);
        self::assertSame([], glob($this->directory.'/*'));
    }

    public function testAToolThatCannotStartFailsUnderTheRetryStrategy(): void
    {
        $tools = new MemoryTools(static fn (): never => throw new AudioToolUnavailable('no ffmpeg'));

        try {
            new TranscodeAudioHandler($this->storage, $this->storage, $tools, $this->renditions, $this->directory)(new TranscodeAudio('m1', 'mp3'));
            self::fail('A missing tool fails.');
        } catch (AudioToolUnavailable $audioToolUnavailable) {
            self::assertSame('no ffmpeg', $audioToolUnavailable->getMessage());
        }

        self::assertCount(1, $this->storage->released);
    }

    public function testAWaveformIsComputedAndKept(): void
    {
        $tools = new MemoryTools();
        new ComputeWaveformHandler($this->storage, $this->storage, $tools, 400)(new ComputeWaveform('m1'));

        self::assertEquals(new Peaks(8_000, 400, 8, [0, 1]), $this->storage->peaks('m1'));
        self::assertSame([$this->directory.'/copy-m1'], $this->storage->released);
        self::assertNull($this->storage->peaks('m2'));
    }

    public function testAWaveformThatCannotBeComputedIsNotRetried(): void
    {
        try {
            new ComputeWaveformHandler($this->storage, $this->storage, new MemoryTools(), 400)(new ComputeWaveform('m2'));
            self::fail('A file gone fails for good.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertSame('No file m2', $unrecoverableMessageHandlingException->getMessage());
        }

        $tools = new MemoryTools(peaks: static fn (): never => throw new LimitExceeded('too long'));

        try {
            new ComputeWaveformHandler($this->storage, $this->storage, $tools, 400)(new ComputeWaveform('m1'));
            self::fail('A file too long fails for good.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertSame('too long', $unrecoverableMessageHandlingException->getMessage());
        }

        self::assertCount(1, $this->storage->released);

        $this->expectException(AudioToolUnavailable::class);
        new ComputeWaveformHandler($this->storage, $this->storage, new MemoryTools(peaks: static fn (): never => throw new AudioToolUnavailable('no ffmpeg')), 400)(new ComputeWaveform('m1'));
    }
}
