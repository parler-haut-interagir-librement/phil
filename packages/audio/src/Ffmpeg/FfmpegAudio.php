<?php

declare(strict_types=1);

namespace Phil\Audio\Ffmpeg;

use Override;
use Phil\Audio\AudioFormat;
use Phil\Audio\AudioMetadata;
use Phil\Audio\Contract\CommandRunner;
use Phil\Audio\Contract\MetadataReader;
use Phil\Audio\Contract\PeaksGenerator;
use Phil\Audio\Contract\Transcoder;
use Phil\Audio\Exception\AudioToolFailed;
use Phil\Audio\Exception\AudioToolUnavailable;
use Phil\Audio\Exception\InvalidAudio;
use Phil\Audio\Exception\LimitExceeded;
use Phil\Audio\Excerpt;
use Phil\Audio\Peaks;
use Phil\Audio\PeaksComputer;
use Phil\Audio\Rendition;

/**
 * The audio tools on ffmpeg and ffprobe, run as external programs: transcoding, metadata and waveforms.
 *
 * Every run is bounded and closed to the network: a file is read by its path alone (`file:`, and the `file` protocol
 * only, so that a playlist cannot make ffmpeg fetch an address), no standard input is read, a file larger or longer
 * than the limits is refused before ffmpeg decodes it, the output is cut at its limit, and the run is stopped at its
 * timeout. The arguments are a list, each one passed as it is. A rendition drops the tags of the source.
 */
final readonly class FfmpegAudio implements Transcoder, MetadataReader, PeaksGenerator
{
    /** The sample rate the waveform is decoded at: enough for its shape, an eighth of the samples of a CD. */
    public const int PEAKS_SAMPLE_RATE = 8_000;

    /** The exit codes of a shell for a program it cannot execute or cannot find. */
    private const array EXIT_NOT_STARTED = [126, 127];

    /** The arguments that open every run of ffmpeg. */
    private const array INPUT = ['-nostdin', '-hide_banner', '-loglevel', 'error', '-protocol_whitelist', 'file'];

    private string $temporaryDirectory;

    /**
     * @param string      $ffmpeg             the path or the name of ffmpeg
     * @param string      $ffprobe            the path or the name of ffprobe
     * @param string|null $temporaryDirectory where the decoded samples of a waveform are written while it is computed;
     *                                        the system's when null
     */
    public function __construct(
        private CommandRunner $runner = new ProcessCommandRunner(),
        private string $ffmpeg = 'ffmpeg',
        private string $ffprobe = 'ffprobe',
        private FfmpegLimits $limits = new FfmpegLimits(),
        private PeaksComputer $computer = new PeaksComputer(),
        ?string $temporaryDirectory = null,
    ) {
        $this->temporaryDirectory = $temporaryDirectory ?? sys_get_temp_dir();
    }

    #[Override]
    public function read(string $path): AudioMetadata
    {
        $this->checkInput($path);
        $result = $this->run([
            $this->ffprobe, '-v', 'error', '-hide_banner', '-protocol_whitelist', 'file',
            '-print_format', 'json', '-show_format', '-show_streams', '-select_streams', 'a:0', 'file:'.$path,
        ]);

        return new FfprobeReader($this->limits->maxReportBytes)->read($result);
    }

    #[Override]
    public function transcode(string $source, string $target, Rendition $rendition, ?Excerpt $excerpt = null): void
    {
        $this->checkDuration($source);
        $command = [$this->ffmpeg, ...self::INPUT];
        if ($excerpt instanceof Excerpt) {
            $command[] = '-ss';
            $command[] = $this->seconds($excerpt->start);
        }

        $command[] = '-i';
        $command[] = 'file:'.$source;
        if ($excerpt instanceof Excerpt) {
            $command[] = '-t';
            $command[] = $this->seconds($excerpt->duration);
        }

        array_push($command, '-map', '0:a:0', '-vn', '-map_metadata', '-1', ...$this->encoder($rendition));
        if (null !== $rendition->channels) {
            $command[] = '-ac';
            $command[] = (string) $rendition->channels;
        }

        if (null !== $rendition->sampleRate) {
            $command[] = '-ar';
            $command[] = (string) $rendition->sampleRate;
        }

        $command[] = '-fs';
        $command[] = (string) $this->limits->maxOutputBytes;
        $command[] = '-f';
        $command[] = $this->muxer($rendition->format);
        $command[] = '-y';
        $command[] = 'file:'.$target;

        try {
            $this->run($command);
        } catch (AudioToolFailed|LimitExceeded $exception) {
            @unlink($target);

            throw $exception;
        }

        clearstatcache(true, $target);

        if (!is_file($target)) {
            throw new AudioToolFailed('ffmpeg wrote no file.');
        }

        if (filesize($target) >= $this->limits->maxOutputBytes) {
            unlink($target);

            throw new LimitExceeded(\sprintf('The rendition reached %d bytes and was cut.', $this->limits->maxOutputBytes));
        }
    }

    #[Override]
    public function peaks(string $path, int $samplesPerPixel = 800): Peaks
    {
        $this->checkDuration($path);
        if (!is_dir($this->temporaryDirectory) || !is_writable($this->temporaryDirectory)) {
            throw new AudioToolFailed('No temporary file can be written for the waveform.');
        }

        $samples = rtrim($this->temporaryDirectory, '/').'/phil-audio-'.sodium_bin2hex(random_bytes(8)).'.pcm';

        try {
            $this->run([
                $this->ffmpeg, ...self::INPUT, '-i', 'file:'.$path, '-map', '0:a:0', '-vn', '-ac', '1',
                '-ar', (string) self::PEAKS_SAMPLE_RATE, '-c:a', 'pcm_s16le', '-f', 's16le',
                '-fs', (string) $this->limits->maxOutputBytes, '-y', 'file:'.$samples,
            ]);
            $stream = fopen($samples, 'r');

            if (false === $stream) {
                throw new AudioToolFailed('The decoded samples cannot be read.');
            }

            try {
                return $this->computer->fromStream($stream, self::PEAKS_SAMPLE_RATE, $samplesPerPixel);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($samples);
        }
    }

    /**
     * @return list<string>
     */
    private function encoder(Rendition $rendition): array
    {
        [$codec, $bitrate] = match ($rendition->format) {
            AudioFormat::Mp3 => ['libmp3lame', 128],
            AudioFormat::Aac => ['aac', 128],
            AudioFormat::Opus => ['libopus', 96],
            AudioFormat::Flac => ['flac', null],
            AudioFormat::Wav => ['pcm_s16le', null],
        };
        $bitrate = $rendition->bitrate ?? $bitrate;

        return null === $bitrate ? ['-c:a', $codec] : ['-c:a', $codec, '-b:a', $bitrate.'k'];
    }

    private function muxer(AudioFormat $format): string
    {
        return match ($format) {
            AudioFormat::Mp3 => 'mp3',
            AudioFormat::Aac => 'ipod',
            AudioFormat::Opus => 'ogg',
            AudioFormat::Flac => 'flac',
            AudioFormat::Wav => 'wav',
        };
    }

    private function seconds(int $milliseconds): string
    {
        return \sprintf('%d.%03d', intdiv($milliseconds, 1000), $milliseconds % 1000);
    }

    private function checkInput(string $path): void
    {
        if ('' === $path || !is_file($path) || !is_readable($path)) {
            throw new InvalidAudio('The audio file cannot be read.');
        }

        if (filesize($path) > $this->limits->maxInputBytes) {
            throw new LimitExceeded(\sprintf('The audio file holds more than %d bytes.', $this->limits->maxInputBytes));
        }
    }

    private function checkDuration(string $path): void
    {
        if ($this->read($path)->duration > $this->limits->maxDuration) {
            throw new LimitExceeded(\sprintf('The audio file lasts more than %d ms.', $this->limits->maxDuration));
        }
    }

    /**
     * @param non-empty-list<string> $command
     *
     * @return string the output of the program
     */
    private function run(array $command): string
    {
        $result = $this->runner->run($command, $this->limits->timeout, $this->limits->maxReportBytes);

        if (\in_array($result->exitCode, self::EXIT_NOT_STARTED, true)) {
            throw new AudioToolUnavailable(\sprintf('"%s" cannot be found or executed.', $command[0]));
        }

        if (!$result->isSuccessful()) {
            $line = trim(explode("\n", trim($result->errorOutput), 2)[0]);

            throw new AudioToolFailed(substr(\sprintf('"%s" failed (%d): %s', $command[0], $result->exitCode, '' === $line ? 'no message' : $line), 0, 500));
        }

        return $result->output;
    }
}
