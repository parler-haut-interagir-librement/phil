<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Unit\Ffmpeg;

use Override;
use Phil\Audio\AudioFormat;
use Phil\Audio\CommandResult;
use Phil\Audio\Exception\AudioToolFailed;
use Phil\Audio\Exception\AudioToolUnavailable;
use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Exception\LimitExceeded;
use Phil\Audio\Excerpt;
use Phil\Audio\Ffmpeg\FfmpegAudio;
use Phil\Audio\Ffmpeg\FfmpegLimits;
use Phil\Audio\PeaksComputer;
use Phil\Audio\Rendition;
use Phil\Audio\Tests\Support\ScriptedRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ffmpeg and ffprobe replaced by a scripted runner: the commands are checked argument by argument, and the files they
 * would write are written by the script.
 */
#[CoversClass(FfmpegAudio::class)]
#[CoversClass(FfmpegLimits::class)]
final class FfmpegAudioTest extends TestCase
{
    private const array PROBE = ['/usr/bin/ffprobe', '-v', 'error', '-hide_banner', '-protocol_whitelist', 'file', '-print_format', 'json', '-show_format', '-show_streams', '-select_streams', 'a:0'];

    private string $directory;

    private string $source;

    private string $target;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-audio-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->directory = (string) realpath($this->directory);
        $this->source = $this->directory.'/source.wav';
        $this->target = $this->directory.'/target.mp3';
        file_put_contents($this->source, 'RIFF');
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');
        array_map(unlink(...), false === $files ? [] : $files);
        rmdir($this->directory);
    }

    private static function report(string $duration = '61.5'): string
    {
        return '{"streams":[{"codec_type":"audio","codec_name":"pcm_s16le","sample_rate":"48000","channels":2}],"format":{"format_name":"wav","duration":"'.$duration.'"}}';
    }

    private function audio(ScriptedRunner $runner, FfmpegLimits $limits = new FfmpegLimits()): FfmpegAudio
    {
        return new FfmpegAudio($runner, '/usr/bin/ffmpeg', '/usr/bin/ffprobe', $limits, new PeaksComputer(), $this->directory);
    }

    public function testMetadataIsReadByFfprobeOnTheFileProtocolOnly(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report());
        $metadata = $this->audio($runner, new FfmpegLimits(12.5, maxReportBytes: 4_096))->read($this->source);

        self::assertSame(61_500, $metadata->duration);
        self::assertSame('wav', $metadata->container);
        self::assertSame([...self::PROBE, 'file:'.$this->source], $runner->command(0));
        self::assertSame(12.5, $runner->runs[0]['timeout']);
        self::assertSame(4_096, $runner->runs[0]['maxOutputBytes']);
    }

    public function testTheReportIsBoundedByTheLimits(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The report of ffprobe holds more than 10 bytes.');

        $this->audio(ScriptedRunner::ffmpeg(self::report()), new FfmpegLimits(maxReportBytes: 10))->read($this->source);
    }

    public function testARenditionIsEncodedWithoutTheTagsOfTheSource(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report());
        $this->audio($runner, new FfmpegLimits(maxOutputBytes: 1_000))->transcode($this->source, $this->target, new Rendition(AudioFormat::Mp3, 192, 1, 44_100));

        self::assertSame([...self::PROBE, 'file:'.$this->source], $runner->command(0));
        self::assertSame([
            '/usr/bin/ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-protocol_whitelist', 'file',
            '-i', 'file:'.$this->source, '-map', '0:a:0', '-vn', '-map_metadata', '-1',
            '-c:a', 'libmp3lame', '-b:a', '192k', '-ac', '1', '-ar', '44100',
            '-fs', '1000', '-f', 'mp3', '-y', 'file:'.$this->target,
        ], $runner->command(1));
        self::assertSame('audio', file_get_contents($this->target));
    }

    public function testAnExcerptIsCutBeforeAndAfterTheInput(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report());
        $this->audio($runner)->transcode($this->source, $this->target, new Rendition(AudioFormat::Opus), new Excerpt(61_005, 30_000));
        $command = $runner->command(1);

        self::assertSame(['-ss', '61.005', '-i', 'file:'.$this->source, '-t', '30.000'], \array_slice($command, 7, 6));
        self::assertSame(['-c:a', 'libopus', '-b:a', '96k', '-fs', '1073741824', '-f', 'ogg'], \array_slice($command, 18, 8));
    }

    /**
     * @return iterable<string, array{Rendition, list<string>}>
     */
    public static function encoders(): iterable
    {
        yield 'mp3' => [new Rendition(AudioFormat::Mp3), ['-c:a', 'libmp3lame', '-b:a', '128k', '-fs', '1073741824', '-f', 'mp3']];
        yield 'aac' => [new Rendition(AudioFormat::Aac), ['-c:a', 'aac', '-b:a', '128k', '-fs', '1073741824', '-f', 'ipod']];
        yield 'aac at 64' => [new Rendition(AudioFormat::Aac, 64), ['-c:a', 'aac', '-b:a', '64k', '-fs', '1073741824', '-f', 'ipod']];
        yield 'flac' => [new Rendition(AudioFormat::Flac), ['-c:a', 'flac', '-fs', '1073741824', '-f', 'flac']];
        yield 'wav' => [new Rendition(AudioFormat::Wav), ['-c:a', 'pcm_s16le', '-fs', '1073741824', '-f', 'wav']];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('encoders')]
    public function testEachFormatHasItsEncoderAndMuxer(Rendition $rendition, array $arguments): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report());
        $this->audio($runner)->transcode($this->source, $this->target, $rendition);

        self::assertSame($arguments, \array_slice($runner->command(1), 14, \count($arguments)));
    }

    public function testAFileTooLongIsRefusedBeforeItIsEncoded(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report('61.5'));
        $this->audio($runner, new FfmpegLimits(maxDuration: 61_500))->transcode($this->source, $this->target, new Rendition(AudioFormat::Mp3));
        self::assertCount(2, $runner->runs);

        $runner = ScriptedRunner::ffmpeg(self::report('61.501'));

        try {
            $this->audio($runner, new FfmpegLimits(maxDuration: 61_500))->transcode($this->source, $this->target, new Rendition(AudioFormat::Mp3));
            self::fail('A file past the duration is refused.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('The audio file lasts more than 61500 ms.', $limitExceeded->getMessage());
        }

        self::assertCount(1, $runner->runs);
    }

    public function testAFileTooLargeIsRefusedBeforeAnyToolRuns(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report());
        self::assertSame(61_500, $this->audio($runner, new FfmpegLimits(maxInputBytes: 4))->read($this->source)->duration);

        try {
            $this->audio($runner, new FfmpegLimits(maxInputBytes: 3))->read($this->source);
            self::fail('A file past its bytes is refused.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('The audio file holds more than 3 bytes.', $limitExceeded->getMessage());
        }

        self::assertCount(1, $runner->runs);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadable(): iterable
    {
        yield 'empty' => [''];
        yield 'missing' => ['/nonexistent/phil-audio.wav'];
        yield 'a directory' => [sys_get_temp_dir()];
    }

    #[DataProvider('unreadable')]
    public function testAFileThatCannotBeReadIsRefused(string $path): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report());

        try {
            $this->audio($runner)->read($path);
            self::fail('An unreadable file is refused.');
        } catch (InvalidAudio $invalidAudio) {
            self::assertSame('The audio file cannot be read.', $invalidAudio->getMessage());
        }

        self::assertSame([], $runner->runs);
    }

    public function testARenditionCutAtItsLimitIsRefusedAndDeleted(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report(), str_repeat('a', 5));
        $this->audio($runner, new FfmpegLimits(maxOutputBytes: 6))->transcode($this->source, $this->target, new Rendition(AudioFormat::Mp3));
        self::assertFileExists($this->target);

        try {
            $this->audio($runner, new FfmpegLimits(maxOutputBytes: 5))->transcode($this->source, $this->target, new Rendition(AudioFormat::Mp3));
            self::fail('A rendition at its limit is refused.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('The rendition reached 5 bytes and was cut.', $limitExceeded->getMessage());
        }

        self::assertFileDoesNotExist($this->target);
    }

    public function testAFailureDeletesWhatWasWrittenAndGivesTheFirstLineOfError(): void
    {
        $runner = new ScriptedRunner(function (array $command): CommandResult {
            if (str_contains($command[0], 'ffprobe')) {
                return new CommandResult(0, self::report());
            }

            file_put_contents($this->target, 'half');

            return new CommandResult(1, '', "  Unknown encoder 'libmp3lame'\nsecond line");
        });

        try {
            $this->audio($runner)->transcode($this->source, $this->target, new Rendition(AudioFormat::Mp3));
            self::fail('A failure is reported.');
        } catch (AudioToolFailed $audioToolFailed) {
            self::assertSame("\"/usr/bin/ffmpeg\" failed (1): Unknown encoder 'libmp3lame'", $audioToolFailed->getMessage());
        }

        self::assertFileDoesNotExist($this->target);
    }

    public function testAnOutputPastItsLimitDeletesWhatWasWritten(): void
    {
        $runner = new ScriptedRunner(function (array $command): CommandResult {
            if (str_contains($command[0], 'ffprobe')) {
                return new CommandResult(0, self::report());
            }

            file_put_contents($this->target, 'half');

            throw new LimitExceeded('stopped');
        });

        try {
            $this->audio($runner)->transcode($this->source, $this->target, new Rendition(AudioFormat::Mp3));
            self::fail('A run stopped is reported.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('stopped', $limitExceeded->getMessage());
        }

        self::assertFileDoesNotExist($this->target);
    }

    public function testAnErrorMessageIsBoundedAndNeverEmpty(): void
    {
        $long = new ScriptedRunner(static fn (): CommandResult => new CommandResult(2, '', str_repeat('x', 600)));

        try {
            $this->audio($long)->read($this->source);
            self::fail('A failure is reported.');
        } catch (AudioToolFailed $audioToolFailed) {
            self::assertSame(500, \strlen($audioToolFailed->getMessage()));
            self::assertStringStartsWith('"/usr/bin/ffprobe" failed (2): xxx', $audioToolFailed->getMessage());
        }

        $this->expectException(AudioToolFailed::class);
        $this->expectExceptionMessageIsOrContains('"/usr/bin/ffprobe" failed (3): no message');
        $this->audio(new ScriptedRunner(static fn (): CommandResult => new CommandResult(3, '', " \n ")))->read($this->source);
    }

    public function testTheFirstLineOfAnErrorIsTrimmedWhateverBlankLinesSurroundIt(): void
    {
        foreach (["\n  boom \nrest", "  boom  \nrest"] as $errorOutput) {
            try {
                $this->audio(new ScriptedRunner(static fn (): CommandResult => new CommandResult(2, '', $errorOutput)))->read($this->source);
                self::fail('A failure is reported.');
            } catch (AudioToolFailed $audioToolFailed) {
                self::assertSame('"/usr/bin/ffprobe" failed (2): boom', $audioToolFailed->getMessage());
            }
        }
    }

    public function testAnExcerptCutAtAThousandSecondsIsWrittenInSecondsAndMilliseconds(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report());
        $this->audio($runner)->transcode($this->source, $this->target, new Rendition(AudioFormat::Mp3), new Excerpt(1_000_000, 1_000_000));

        self::assertSame(['-ss', '1000.000', '-i', 'file:'.$this->source, '-t', '1000.000'], \array_slice($runner->command(1), 7, 6));
    }

    public function testAProgramNotFoundIsUnavailable(): void
    {
        foreach ([126, 127] as $code) {
            try {
                $this->audio(new ScriptedRunner(static fn (): CommandResult => new CommandResult($code)))->read($this->source);
                self::fail('A program not started is unavailable.');
            } catch (AudioToolUnavailable $exception) {
                self::assertSame('"/usr/bin/ffprobe" cannot be found or executed.', $exception->getMessage());
            }
        }

        $this->expectException(AudioToolFailed::class);
        $this->audio(new ScriptedRunner(static fn (): CommandResult => new CommandResult(125)))->read($this->source);
    }

    public function testARunThatWritesNoFileFails(): void
    {
        $runner = new ScriptedRunner(static fn (array $command): CommandResult => new CommandResult(0, str_contains($command[0], 'ffprobe') ? self::report() : ''));

        $this->expectException(AudioToolFailed::class);
        $this->expectExceptionMessageIsOrContains('ffmpeg wrote no file.');
        $this->audio($runner)->transcode($this->source, $this->target, new Rendition(AudioFormat::Mp3));
    }

    public function testAWaveformIsDecodedToSamplesThenComputedAndTheSamplesDeleted(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report(), pack('v*', 256, 512, 1_024));
        $peaks = $this->audio($runner, new FfmpegLimits(maxOutputBytes: 99))->peaks($this->source, 2);
        $command = $runner->command(1);
        $samples = substr((string) array_last($command), \strlen('file:'));

        self::assertSame([1, 2, 4, 4], $peaks->data);
        self::assertSame(FfmpegAudio::PEAKS_SAMPLE_RATE, $peaks->sampleRate);
        self::assertSame([
            '/usr/bin/ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-protocol_whitelist', 'file',
            '-i', 'file:'.$this->source, '-map', '0:a:0', '-vn', '-ac', '1', '-ar', '8000', '-c:a', 'pcm_s16le',
            '-f', 's16le', '-fs', '99', '-y',
        ], \array_slice($command, 0, -1));
        self::assertMatchesRegularExpression('#^'.preg_quote($this->directory, '#').'/phil-audio-[0-9a-f]{16}\.pcm$#', $samples);
        self::assertFileDoesNotExist($samples);
        self::assertSame(800, $this->audio(ScriptedRunner::ffmpeg(self::report(), ''))->peaks($this->source)->samplesPerPixel);
    }

    public function testAWaveformOfAFileTooLongIsRefused(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report('10'));

        try {
            $this->audio($runner, new FfmpegLimits(maxDuration: 9_999))->peaks($this->source);
            self::fail('A file past the duration is refused.');
        } catch (LimitExceeded) {
            self::assertCount(1, $runner->runs);
        }

        self::assertSame([], glob($this->directory.'/phil-audio-*'));
    }

    public function testTheSamplesAreDeletedWhenTheDecoderFails(): void
    {
        $runner = new ScriptedRunner(static fn (array $command): CommandResult => str_contains($command[0], 'ffprobe') ? new CommandResult(0, self::report()) : new CommandResult(1, '', 'bad'));

        try {
            $this->audio($runner)->peaks($this->source);
            self::fail('A failure is reported.');
        } catch (AudioToolFailed) {
            self::assertSame([], glob($this->directory.'/phil-audio-*'));
        }
    }

    public function testATrailingSlashOfTheTemporaryDirectoryIsDropped(): void
    {
        $runner = ScriptedRunner::ffmpeg(self::report(), '');
        new FfmpegAudio($runner, temporaryDirectory: $this->directory.'/')->peaks($this->source);

        self::assertStringStartsWith('file:'.$this->directory.'/phil-audio-', (string) array_last($runner->command(1)));
        self::assertSame('ffprobe', $runner->command(0)[0]);
        self::assertSame('ffmpeg', $runner->command(1)[0]);
    }

    public function testAReadOnlyDirectoryMeansNoWaveform(): void
    {
        mkdir($this->directory.'/locked', 0o500);

        if (is_writable($this->directory.'/locked')) {
            rmdir($this->directory.'/locked');
            self::markTestSkipped('The user of the tests writes in a read-only directory, as root does.');
        }

        $audio = new FfmpegAudio(ScriptedRunner::ffmpeg(self::report()), temporaryDirectory: $this->directory.'/locked');

        try {
            $audio->peaks($this->source);
            self::fail('A read-only directory is refused.');
        } catch (AudioToolFailed $audioToolFailed) {
            self::assertSame('No temporary file can be written for the waveform.', $audioToolFailed->getMessage());
        } finally {
            rmdir($this->directory.'/locked');
        }
    }

    public function testNoTemporaryFileMeansNoWaveform(): void
    {
        $audio = new FfmpegAudio(ScriptedRunner::ffmpeg(self::report()), temporaryDirectory: $this->directory.'/missing/');

        $this->expectException(AudioToolFailed::class);
        $this->expectExceptionMessageIsOrContains('No temporary file can be written for the waveform.');
        $audio->peaks($this->source);
    }

    /**
     * @return iterable<string, array{float, int, int, int, int}>
     */
    public static function badLimits(): iterable
    {
        yield 'no time' => [0.0, 1, 1, 1, 1];
        yield 'no input' => [1.0, 0, 1, 1, 1];
        yield 'no output' => [1.0, 1, 0, 1, 1];
        yield 'no duration' => [1.0, 1, 1, 0, 1];
        yield 'no report' => [1.0, 1, 1, 1, 0];
    }

    #[DataProvider('badLimits')]
    public function testEveryLimitIsPositive(float $timeout, int $input, int $output, int $duration, int $report): void
    {
        $this->expectException(InvalidAudio::class);

        new FfmpegLimits($timeout, $input, $output, $duration, $report);
    }

    public function testTheDefaultsCoverSixHours(): void
    {
        $limits = new FfmpegLimits(0.001, 1, 1, 1, 1);
        self::assertSame(0.001, $limits->timeout);

        $defaults = new FfmpegLimits();
        self::assertSame([900.0, 2_147_483_648, 1_073_741_824, 21_600_000, 1_048_576], [$defaults->timeout, $defaults->maxInputBytes, $defaults->maxOutputBytes, $defaults->maxDuration, $defaults->maxReportBytes]);
    }
}
