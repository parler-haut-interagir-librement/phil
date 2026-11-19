<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use LogicException;
use Phil\DocumentImport\Media;
use Phil\DocumentImportBundle\Infrastructure\Media\MediaLibraryStore;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Model\Media as Medium;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(MediaLibraryStore::class)]
final class MediaLibraryStoreTest extends TestCase
{
    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg"/>';

    private MemoryMedia $memory;

    private MediaLibrary $library;

    protected function setUp(): void
    {
        $this->memory = new MemoryMedia();
        $this->library = new MediaLibrary($this->memory, $this->memory, $this->memory, new MockClock('2026-11-07 10:00:00'));
    }

    public function testAnImageBecomesAMediumOfTheCollection(): void
    {
        $store = new MediaLibraryStore($this->library, $this->memory, static fn (Medium $medium): string => '/media/'.$medium->id, 'imports');
        $before = $this->temporaryFiles();

        $address = $store->store(new Media('word/media/a b.svg', self::SVG, " A drawing \n"));
        $second = $store->store(new Media('word/media/c.svg', self::SVG));

        self::assertSame($before, $this->temporaryFiles());
        self::assertCount(1, $this->memory->collections);
        $collection = $this->memory->collections['imports'];
        self::assertSame('imports', $collection->name());
        [$saved, $first, $described, $other] = $this->memory->saved;
        self::assertSame($collection, $saved);
        self::assertInstanceOf(Medium::class, $first);
        self::assertSame($first, $described);
        self::assertInstanceOf(Medium::class, $other);
        self::assertCount(4, $this->memory->saved);
        self::assertSame('/media/'.$first->id, $address);
        self::assertSame('/media/'.$other->id, $second);
        self::assertSame($collection, $first->collection());
        self::assertSame($collection, $other->collection());
        self::assertSame('a-b.svg', $first->file()->filename);
        self::assertSame('image/svg+xml', $first->file()->mimeType);
        self::assertSame('A drawing', $first->alt());
        self::assertNull($first->title());
        self::assertNull($other->alt());
        self::assertSame([self::SVG, self::SVG], array_values($this->memory->files));
    }

    public function testAnExistingCollectionIsUsedAndALongAlternativeIsCut(): void
    {
        $collection = MediaCollection::create('imports', 'Imports');
        $this->memory->collections['imports'] = $collection;
        $store = new MediaLibraryStore($this->library, $this->memory, static fn (Medium $medium): string => $medium->file()->filename, 'imports');

        self::assertSame('c.svg', $store->store(new Media('c.svg', self::SVG, str_repeat('é', 501))));
        self::assertCount(2, $this->memory->saved);
        $medium = $this->memory->saved[0];
        self::assertInstanceOf(Medium::class, $medium);
        self::assertSame($collection, $medium->collection());
        self::assertSame(str_repeat('é', 500), $medium->alt());
    }

    public function testWithoutCollectionTheMediumStandsAlone(): void
    {
        $store = new MediaLibraryStore($this->library, $this->memory, static fn (Medium $medium): string => 'x');

        self::assertSame('x', $store->store(new Media('c.svg', self::SVG, '   ')));
        self::assertCount(1, $this->memory->saved);
        $medium = $this->memory->saved[0];
        self::assertInstanceOf(Medium::class, $medium);
        self::assertNull($medium->collection());
        self::assertSame([], $this->memory->collections);
    }

    public function testTheTemporaryFileIsRemovedWhenTheUploadFails(): void
    {
        $this->memory->failing = true;
        $store = new MediaLibraryStore($this->library, $this->memory, static fn (Medium $medium): string => 'x');
        $before = $this->temporaryFiles();

        try {
            $store->store(new Media('c.svg', self::SVG));
            self::fail('No exception.');
        } catch (LogicException $logicException) {
            self::assertSame('The disk is full.', $logicException->getMessage());
        }

        self::assertSame($before, $this->temporaryFiles());
    }

    /**
     * @return list<string>
     */
    private function temporaryFiles(): array
    {
        $files = glob(sys_get_temp_dir().'/phil-import-*');

        return false === $files ? [] : $files;
    }
}
