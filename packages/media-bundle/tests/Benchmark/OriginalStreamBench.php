<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Benchmark;

use DateTimeImmutable;
use FilesystemIterator;
use Generator;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A video handed to a tool that reads files: `original()` holds it whole in the worker's memory, where
 * `readOriginal()` and `copyOriginalTo()` stream it, so the peak memory stays flat whatever the size of the file.
 *
 * @BeforeMethods("setUp")
 *
 * @AfterMethods("tearDown")
 *
 * @Groups({"media"})
 */
final class OriginalStreamBench
{
    private readonly MediaLibrary $library;

    private Media $media;

    private readonly string $directory;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir().'/phil-media-bench-'.bin2hex(random_bytes(6));
        $this->library = new MediaLibrary(new DoctrineMediaRepository(Database::entityManager()), new FilesystemStorage($this->directory), new GdImageProcessor(), new MutableClock(new DateTimeImmutable('2026-11-14 12:00 UTC')));
        $this->media = Media::upload(FileInfo::of('video.mp4', 'video/mp4', 1), null, new DateTimeImmutable('2026-11-14 12:00 UTC'));
    }

    /**
     * @param array{megabytes: int} $params
     */
    public function setUp(array $params): void
    {
        $bytes = $params['megabytes'] * 1_048_576;
        $this->media = Media::upload(FileInfo::of('video.mp4', 'video/mp4', $bytes), null, new DateTimeImmutable('2026-11-14 12:00 UTC'));
        $path = $this->directory.'/'.$this->media->storageKey();
        mkdir(\dirname($path), recursive: true);
        $file = fopen($path, 'w');
        \assert(false !== $file);
        $chunk = str_repeat("\0", 1_048_576);

        for ($i = 0; $i < $params['megabytes']; ++$i) {
            fwrite($file, $chunk);
        }

        fclose($file);
    }

    public function tearDown(): void
    {
        /** @var iterable<SplFileInfo> $entries */
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->directory);
    }

    /**
     * The file read whole, then written to the tool's path.
     *
     * @ParamProviders("sizes")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchOriginal(): void
    {
        file_put_contents($this->directory.'/copy.mp4', $this->library->original($this->media));
    }

    /**
     * The file copied to the tool's path through a stream.
     *
     * @ParamProviders("sizes")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchCopyOriginalTo(): void
    {
        $this->library->copyOriginalTo($this->media, $this->directory.'/copy.mp4');
    }

    /**
     * The stream read by chunks of 1 MiB, as a response that sends it would.
     *
     * @ParamProviders("sizes")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchReadOriginal(): void
    {
        $stream = $this->library->readOriginal($this->media);

        while (!feof($stream)) {
            fread($stream, 1_048_576);
        }

        fclose($stream);
    }

    /**
     * @return Generator<string, array{megabytes: int}>
     */
    public function sizes(): Generator
    {
        yield '16 MiB' => ['megabytes' => 16];
        yield '128 MiB' => ['megabytes' => 128];
    }
}
