<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Override;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMediaBundle\Application\Exception\JobUnavailable;
use Phil\PagedMediaBundle\Application\Exception\StorageFailed;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Infrastructure\Storage\DirectoryJobStore;
use Phil\PagedMediaBundle\Infrastructure\Storage\JobCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(DirectoryJobStore::class)]
#[CoversClass(JobUnavailable::class)]
#[CoversClass(StorageFailed::class)]
#[UsesClass(JobCodec::class)]
final class DirectoryJobStoreTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-paged-media-jobs-'.bin2hex(random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            chmod($this->directory, 0o700);
        }

        new Filesystem()->remove($this->directory);
    }

    public function testAJobIsKeptUnderARandomIdentifierReadableByItsOwnerOnly(): void
    {
        $store = new DirectoryJobStore($this->directory.'/jobs');
        $job = new RenderJob('badge', ['name' => 'Ada'], 'badges/1.pdf');

        $first = $store->keep($job);
        $second = $store->keep(new PipelineJob(Files::of(InputFile::fromContents('a.pdf', '%PDF')), new Pipeline(new Optimize()), 'a.pdf'));

        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $first);
        self::assertNotSame($first, $second);
        self::assertSame(new JobCodec()->encode($job), file_get_contents($this->directory.'/jobs/'.$first.'.json'));
        self::assertSame(0o700, fileperms($this->directory.'/jobs') & 0o777);
        self::assertSame(0o600, fileperms($this->directory.'/jobs/'.$first.'.json') & 0o777);
        self::assertEquals($job, $store->find($first));
        self::assertInstanceOf(PipelineJob::class, $store->find($second));
    }

    public function testARemovedJobIsGone(): void
    {
        $store = new DirectoryJobStore($this->directory);
        $id = $store->keep(new RenderJob('badge', [], 'a.pdf'));

        $store->remove($id);
        $store->remove($id);
        $store->remove('../elsewhere');

        self::assertFileDoesNotExist($this->directory.'/'.$id.'.json');
        $this->expectException(JobUnavailable::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('No job is kept under "%s".', $id));

        $store->find($id);
    }

    #[DataProvider('foreignIdentifiers')]
    public function testAnIdentifierOfAnotherShapeNamesNoJob(string $id): void
    {
        mkdir($this->directory.'/sub', 0o700, true);
        file_put_contents($this->directory.'/sub.json', new JobCodec()->encode(new RenderJob('badge', [], 'a.pdf')));
        file_put_contents($this->directory.'/'.str_repeat('A', 32).'.json', new JobCodec()->encode(new RenderJob('badge', [], 'a.pdf')));

        $this->expectException(JobUnavailable::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('No job is kept under "%s".', $id));

        new DirectoryJobStore($this->directory)->find($id);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function foreignIdentifiers(): iterable
    {
        yield ['sub'];
        yield [str_repeat('A', 32)];
        yield [str_repeat('a', 31)];
        yield [str_repeat('a', 33)];
        yield ["\n".str_repeat('a', 32)];
        yield [str_repeat('a', 32)."\n"];
    }

    public function testAJobOfAnotherShapeIsRefusedByTheCodec(): void
    {
        mkdir($this->directory);
        file_put_contents($this->directory.'/'.str_repeat('a', 32).'.json', '{}');

        $this->expectException(JobUnavailable::class);
        $this->expectExceptionMessageIsOrContains('A kept job holds no valid "path"');

        new DirectoryJobStore($this->directory)->find(str_repeat('a', 32));
    }

    public function testADirectoryThatCannotBeMadeIsReported(): void
    {
        mkdir($this->directory);
        file_put_contents($this->directory.'/jobs', 'a file, not a directory');

        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The directory "%s/jobs" cannot be made.', $this->directory));

        new DirectoryJobStore($this->directory.'/jobs')->keep(new RenderJob('badge', [], 'a.pdf'));
    }

    public function testAJobThatCannotBeWrittenIsReported(): void
    {
        if (0 === posix_geteuid()) {
            self::markTestSkipped('The superuser writes in a read-only directory.');
        }

        mkdir($this->directory, 0o500);

        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageMatches('/\AThe job ".+\/[0-9a-f]{32}\.json" cannot be written\.\z/');

        new DirectoryJobStore($this->directory)->keep(new RenderJob('badge', [], 'a.pdf'));
    }

    public function testAJobThatCannotBeRemovedIsReported(): void
    {
        if (0 === posix_geteuid()) {
            self::markTestSkipped('The superuser removes from a read-only directory.');
        }

        $store = new DirectoryJobStore($this->directory);
        $id = $store->keep(new RenderJob('badge', [], 'a.pdf'));
        chmod($this->directory, 0o500);

        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The job "%s/%s.json" cannot be removed.', $this->directory, $id));

        $store->remove($id);
    }
}
