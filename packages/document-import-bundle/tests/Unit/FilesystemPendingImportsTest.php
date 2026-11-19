<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Override;
use Phil\DocumentImport\Format;
use Phil\DocumentImportBundle\Application\PendingImport;
use Phil\DocumentImportBundle\Infrastructure\Claim\FilesystemPendingImports;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Files waiting in a directory of their own, readable by their owner only, by claims of 32 random hexadecimal digits
 * that never reach outside it.
 */
#[CoversClass(FilesystemPendingImports::class)]
final class FilesystemPendingImportsTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/phil-pending-'.sodium_bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->glob($this->root.'/*/*') as $file) {
            unlink($file);
        }

        foreach (array_reverse($this->glob($this->root.'/*')) as $directory) {
            is_dir($directory) ? rmdir($directory) : unlink($directory);
        }

        if (is_dir($this->root)) {
            chmod($this->root, 0o700);
            rmdir($this->root);
        }
    }

    public function testAFileIsKeptTakenBackAndReleased(): void
    {
        $directory = $this->root.'/imports';
        $store = new FilesystemPendingImports($directory);
        $claim = $store->keep(new PendingImport("{\\rtf1 Hi}\0", Format::Rtf, 'Jane Doe.rtf'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $claim);
        self::assertSame(0o700, fileperms($directory) & 0o777);
        self::assertSame([$claim.'.document', $claim.'.json'], array_values(array_diff((array) scandir($directory), ['.', '..'])));
        self::assertSame(0o600, fileperms($directory.'/'.$claim.'.document') & 0o777);
        self::assertSame(0o600, fileperms($directory.'/'.$claim.'.json') & 0o777);
        self::assertSame('{"format":"rtf","filename":"Jane Doe.rtf"}', file_get_contents($directory.'/'.$claim.'.json'));
        self::assertEquals(new PendingImport("{\\rtf1 Hi}\0", Format::Rtf, 'Jane Doe.rtf'), $store->take($claim));
        self::assertNotSame($claim, $store->keep(new PendingImport('', Format::Html)));

        $store->release($claim);
        $store->release($claim);

        self::assertNull($store->take($claim));
        self::assertCount(2, array_diff((array) scandir($directory), ['.', '..']));
    }

    public function testAFileWithoutNameIsTakenBackWithoutName(): void
    {
        $store = new FilesystemPendingImports($this->root);
        $claim = $store->keep(new PendingImport('# A', Format::Markdown));

        self::assertEquals(new PendingImport('# A', Format::Markdown), $store->take($claim));
    }

    public function testAClaimOfAnotherShapeNamesNoFile(): void
    {
        $store = new FilesystemPendingImports($this->root.'/imports');
        $claim = $store->keep(new PendingImport('# A', Format::Markdown));
        mkdir($this->root.'/other');
        file_put_contents($this->root.'/other/x.document', 'secret');
        file_put_contents($this->root.'/other/x.json', '{"format":"markdown"}');

        foreach (['../other/x', strtoupper($claim), $claim."\n", substr($claim, 1), $claim.'0'] as $other) {
            self::assertNull($store->take($other), $other);
            $store->release($other);
        }

        self::assertFileExists($this->root.'/other/x.document');
        self::assertNotNull($store->take($claim));
    }

    public function testAHalfWrittenOrForeignFileIsNoImport(): void
    {
        $store = new FilesystemPendingImports($this->root);
        $claims = [];

        foreach (['{', '[]', '{"format":"doc"}', '{"format":3}', '{"filename":"a"}', '{"format":"markdown","filename":3}', '{"a":{"b":1}}', '{"format":"markdown","a":{"b":1}}'] as $about) {
            $claim = $store->keep(new PendingImport('# A', Format::Markdown));
            file_put_contents($this->root.'/'.$claim.'.json', $about);
            $claims[$about] = $claim;
        }

        foreach ($claims as $about => $claim) {
            $import = $store->take($claim);

            '{"format":"markdown","filename":3}' === $about
                ? self::assertEquals(new PendingImport('# A', Format::Markdown), $import)
                : self::assertNull($import, $about);
        }

        $claim = $store->keep(new PendingImport('# A', Format::Markdown));
        unlink($this->root.'/'.$claim.'.json');
        self::assertNull($store->take($claim));

        $claim = $store->keep(new PendingImport('# A', Format::Markdown));
        unlink($this->root.'/'.$claim.'.document');
        self::assertNull($store->take($claim));

        $store->release($claim);
        self::assertFileDoesNotExist($this->root.'/'.$claim.'.json');
    }

    public function testADirectoryThatCannotBeMadeIsReported(): void
    {
        file_put_contents($this->root, 'a file');

        try {
            new FilesystemPendingImports($this->root.'/imports')->keep(new PendingImport('', Format::Html));
            self::fail('A directory is made under a file.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(\sprintf('The directory "%s/imports" of the pending imports cannot be created.', $this->root), $runtimeException->getMessage());
        } finally {
            unlink($this->root);
        }
    }

    public function testAFileThatCannotBeWrittenIsReported(): void
    {
        mkdir($this->root, 0o500);

        if (is_writable($this->root)) {
            self::markTestSkipped('This user writes in a read-only directory, as root does.');
        }

        try {
            new FilesystemPendingImports($this->root)->keep(new PendingImport('', Format::Html));
            self::fail('A file is written in a read-only directory.');
        } catch (RuntimeException $runtimeException) {
            self::assertStringEndsWith('.document" cannot be written.', $runtimeException->getMessage());
            self::assertStringStartsWith('The pending import "', $runtimeException->getMessage());
        }
    }

    /**
     * @return list<string>
     */
    private function glob(string $pattern): array
    {
        $paths = glob($pattern);

        return false === $paths ? [] : $paths;
    }
}
