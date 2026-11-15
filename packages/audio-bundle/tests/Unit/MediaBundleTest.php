<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Unit;

use InvalidArgumentException;
use Override;
use Phil\Audio\AudioFormat;
use Phil\Audio\Peaks;
use Phil\AudioBundle\Application\Exception\AudioNotFound;
use Phil\AudioBundle\Infrastructure\MediaBundle\MediaLibraryAudioFiles;
use Phil\AudioBundle\Infrastructure\MediaBundle\MediaStorageRenditions;
use Phil\AudioBundle\Tests\Support\FailingTemporaryFile;
use Phil\AudioBundle\Tests\Support\MemoryMedia;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MediaLibraryAudioFiles::class)]
#[CoversClass(MediaStorageRenditions::class)]
final class MediaBundleTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-audio-media-'.sodium_bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');
        array_map(unlink(...), false === $files ? [] : $files);
        rmdir($this->directory);
    }

    public function testAnUploadedFileIsCopiedForAJobAndDeletedOnRelease(): void
    {
        $media = new MemoryMedia();
        $upload = $this->directory.'/episode one.mp3';
        file_put_contents($upload, 'ID3 audio');
        $medium = $media->library()->upload($upload, 'episode one.mp3');
        unlink($upload);
        $files = new MediaLibraryAudioFiles($media, $media->library(), $this->directory.'/');
        $copy = $files->localCopy($medium->id);

        self::assertMatchesRegularExpression('#^'.preg_quote($this->directory, '#').'/phil-audio-source-[0-9a-f]{16}$#', $copy);
        self::assertSame('ID3 audio', file_get_contents($copy));

        $files->release($copy);
        self::assertFileDoesNotExist($copy);
    }

    public function testALongRecordingIsStreamedIntoTheCopy(): void
    {
        $media = new MemoryMedia();
        $upload = $this->directory.'/long.flac';
        $contents = random_bytes(3_000_000);
        file_put_contents($upload, $contents);
        $medium = $media->library()->upload($upload, 'long.flac');
        unlink($upload);
        $files = new MediaLibraryAudioFiles($media, $media->library(), $this->directory);

        $copy = $files->localCopy($medium->id);

        self::assertSame(hash('xxh128', $contents), hash_file('xxh128', $copy));
        self::assertSame(0o600, fileperms($copy) & 0o777);
        $files->release($copy);
        self::assertFileDoesNotExist($copy);
    }

    public function testACopyIsReadableByItsOwnerOnly(): void
    {
        $media = new MemoryMedia();
        $upload = $this->directory.'/b.mp3';
        file_put_contents($upload, str_repeat('b', 70_000));
        $medium = $media->library()->upload($upload, 'b.mp3');
        $files = new MediaLibraryAudioFiles($media, $media->library(), $this->directory);
        $copy = $files->localCopy($medium->id);

        self::assertSame(0o600, fileperms($copy) & 0o777);
        self::assertSame(70_000, filesize($copy));
        $files->release($copy);
    }

    public function testAMissingOriginalIsAFileGoneAndLeavesNoCopy(): void
    {
        $media = new MemoryMedia();
        $upload = $this->directory.'/c.mp3';
        file_put_contents($upload, 'c');
        $medium = $media->library()->upload($upload, 'c.mp3');
        unlink($upload);
        $media->files = [];
        $files = new MediaLibraryAudioFiles($media, $media->library(), $this->directory);

        try {
            $files->localCopy($medium->id);
            self::fail('A missing original is refused.');
        } catch (AudioNotFound $audioNotFound) {
            self::assertInstanceOf(InvalidMedia::class, $audioNotFound->getPrevious());
            self::assertSame($audioNotFound->getPrevious()->getMessage(), $audioNotFound->getMessage());
        }

        self::assertSame([], glob($this->directory.'/phil-audio-source-*'));
    }

    public function testAFailingStorageLeavesNoCopy(): void
    {
        $media = new MemoryMedia();
        $upload = $this->directory.'/d.mp3';
        file_put_contents($upload, 'd');
        $medium = $media->library()->upload($upload, 'd.mp3');
        unlink($upload);
        $failure = new RuntimeException('The storage is down.');
        $media->readFailure = $failure;
        $files = new MediaLibraryAudioFiles($media, $media->library(), $this->directory);

        try {
            $files->localCopy($medium->id);
            self::fail('A failing storage fails the copy.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($failure, $runtimeException);
        }

        self::assertSame([], glob($this->directory.'/phil-audio-source-*'));
    }

    public function testADirectoryThatCannotHoldTheCopyIsReported(): void
    {
        $media = new MemoryMedia();
        $upload = $this->directory.'/e.mp3';
        file_put_contents($upload, 'e');
        $medium = $media->library()->upload($upload, 'e.mp3');
        $files = new MediaLibraryAudioFiles($media, $media->library(), $this->directory.'/missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('No temporary file can be created in "'.$this->directory.'/missing".');
        $files->localCopy($medium->id);
    }

    #[DataProvider('failingDisks')]
    public function testADiskThatFailsTheWritingOrTheFlushingFailsTheCopyAndLeavesNoCopy(string $failing): void
    {
        $media = new MemoryMedia();
        $upload = $this->directory.'/f.mp3';
        file_put_contents($upload, 'f');
        $medium = $media->library()->upload($upload, 'f.mp3');
        $files = new MediaLibraryAudioFiles($media, $media->library(), FailingTemporaryFile::directory($failing));

        try {
            $files->localCopy($medium->id);
            self::fail('A disk that fails fails the copy.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(\sprintf('The copy of %s could not be written.', $medium->id), $runtimeException->getMessage());
        }

        self::assertCount(1, FailingTemporaryFile::$deleted);
        self::assertStringStartsWith(FailingTemporaryFile::PROTOCOL.'://disk/phil-audio-source-', FailingTemporaryFile::$deleted[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function failingDisks(): iterable
    {
        yield 'the writing fails' => ['write'];
        yield 'the flushing fails' => ['flush'];
    }

    public function testOnlyItsOwnCopiesAreDeleted(): void
    {
        $media = new MemoryMedia();
        $files = new MediaLibraryAudioFiles($media, $media->library(), $this->directory);
        $other = $this->directory.'/kept';
        file_put_contents($other, 'x');

        $files->release($other);
        $files->release($this->directory.'/phil-audio-source-missing');
        self::assertFileExists($other);

        $this->expectException(AudioNotFound::class);
        $this->expectExceptionMessageIsOrContains('No medium has the identifier "nope".');
        $files->localCopy('nope');
    }

    public function testTheSystemTemporaryDirectoryIsTheDefault(): void
    {
        $media = new MemoryMedia();
        $upload = $this->directory.'/a.mp3';
        file_put_contents($upload, 'a');
        $medium = $media->library()->upload($upload, 'a.mp3');
        $files = new MediaLibraryAudioFiles($media, $media->library());
        $copy = $files->localCopy($medium->id);

        self::assertStringStartsWith(rtrim(sys_get_temp_dir(), '/').'/phil-audio-source-', $copy);
        $files->release($copy);
        self::assertFileDoesNotExist($copy);
    }

    public function testRenditionsAndWaveformsAreKeptUnderTheMedium(): void
    {
        $media = new MemoryMedia();
        $store = new MediaStorageRenditions($media, '/sounds/');
        $file = $this->directory.'/r.opus';
        file_put_contents($file, 'OPUS');
        $peaks = new Peaks(8_000, 800, 8, [-1, 1]);

        $store->save('m-1', 'opus_64', AudioFormat::Opus, $file);
        $store->save('m-1', 'opus_64', AudioFormat::Opus, $file, excerpt: true);
        $store->savePeaks('m-1', $peaks);

        self::assertSame(['sounds/m-1/opus_64.opus' => 'OPUS', 'sounds/m-1/opus_64-excerpt.opus' => 'OPUS', 'sounds/m-1/peaks.json' => $peaks->toJson()], $media->files);
        self::assertEquals($peaks, $store->peaks('m-1'));
        self::assertNull($store->peaks('m-2'));
        self::assertSame('audio/m/peaks.json', new MediaStorageRenditions($media)->key('m', 'peaks.json'));
    }

    public function testAKeyNeverLeavesTheFolderOfItsMedium(): void
    {
        $store = new MediaStorageRenditions(new MemoryMedia());
        self::assertSame('audio/'.str_repeat('a', 64).'/x', $store->key(str_repeat('a', 64), 'x'));

        foreach (['../etc', '', 'a/b', str_repeat('a', 65), "a\n"] as $name) {
            try {
                $store->key($name, 'peaks.json');
                self::fail('The name "'.$name.'" is refused.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('holds letters, digits, "_" and "-" only, 64 at most.', $exception->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $store->save('m', '../x', AudioFormat::Mp3, __FILE__);
    }
}
