<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Application;

use Override;
use Phil\LibraryBundle\Application\Catalogue;
use Phil\LibraryBundle\Application\Exception\NotFound;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Phil\LibraryBundle\Tests\Unit\Fixture\Library;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Catalogue::class)]
#[CoversClass(NotFound::class)]
final class CatalogueTest extends TestCase
{
    private Library $library;

    #[Override]
    protected function setUp(): void
    {
        $this->library = new Library();
    }

    public function testTitlesAreAddedAndDescribed(): void
    {
        $catalogue = $this->library->catalogue;
        $title = $catalogue->add(new Record(TitleKind::Game, 'Azul'));

        self::assertSame($title, $this->library->titles->get($title->id));
        self::assertSame('2026-11-13T10:00:00-05:00', $title->createdAt()->format(\DATE_ATOM));
        self::assertSame($title, $catalogue->describe($title->id, new Record(TitleKind::Game, 'Azul: Summer Pavilion')));
        self::assertSame('Azul: Summer Pavilion', $catalogue->title($title->id)->name());
        self::assertSame([$title->id, $title->id], $this->library->titles->saved, 'added, then described');

        $this->expectExceptionObject(NotFound::title('ghost'));
        $catalogue->describe('ghost', new Record(TitleKind::Game, 'Azul'));
    }

    public function testCopiesAreAddedInTheOrderOfTheirBarcodes(): void
    {
        $catalogue = $this->library->catalogue;
        $title = $catalogue->add(new Record(TitleKind::Game, 'Azul'));
        $second = $catalogue->addCopy($title->id, 'G-2');
        $first = $catalogue->addCopy($title->id, 'G-1', CopyCondition::Worn, 'item-1');

        self::assertSame([$first, $second], $catalogue->copies($title->id));
        self::assertSame([CopyCondition::Worn, 'item-1', CopyCondition::Good, null], [$first->condition(), $first->stockroomItem(), $second->condition(), $second->stockroomItem()]);
        self::assertSame($first, $catalogue->copy($first->id));
        self::assertSame($second, $catalogue->copyByBarcode('G-2'));

        try {
            $catalogue->addCopy($title->id, 'G-1');
            self::fail('A barcode was given twice.');
        } catch (InvalidLibraryData $invalidLibraryData) {
            self::assertSame('The barcode "G-1" is taken.', $invalidLibraryData->getMessage());
        }

        foreach ([static fn (): mixed => $catalogue->addCopy('ghost', 'G-9'), static fn (): mixed => $catalogue->copies('ghost')] as $call) {
            try {
                $call();
                self::fail('A title that does not exist was read.');
            } catch (NotFound $notFound) {
                self::assertSame('There is no title "ghost".', $notFound->getMessage());
            }
        }

        foreach ([static fn (): mixed => $catalogue->copy('ghost'), static fn (): mixed => $catalogue->copyByBarcode('ghost')] as $call) {
            try {
                $call();
                self::fail('A copy that does not exist was read.');
            } catch (NotFound $notFound) {
                self::assertSame('There is no copy "ghost".', $notFound->getMessage());
            }
        }
    }

    public function testACopyIsAssessedAndLinked(): void
    {
        $catalogue = $this->library->catalogue;
        $title = $catalogue->add(new Record(TitleKind::Game, 'Azul'));
        $copy = $catalogue->addCopy($title->id, 'G-1');
        $saves = $this->library->copies->saves;

        self::assertSame([CopyCondition::Incomplete, 'Two tiles missing.'], [$catalogue->assess($copy->id, CopyCondition::Incomplete, 'Two tiles missing.')->condition(), $copy->note()]);
        self::assertSame('item-9', $catalogue->link($copy->id, 'item-9')->stockroomItem());
        self::assertNull($catalogue->link($copy->id, null)->stockroomItem());
        self::assertNull($catalogue->assess($copy->id, CopyCondition::Good)->note());
        self::assertSame($saves + 4, $this->library->copies->saves);

        $this->expectException(NotFound::class);
        $catalogue->link('ghost', null);
    }

    public function testTheMessagesOfWhatIsNotFound(): void
    {
        self::assertSame('There is no loan "l".', NotFound::loan('l')->getMessage());
        self::assertSame('The copy "G-1" is not out.', NotFound::notOut('G-1')->getMessage());
        self::assertInstanceOf(Copy::class, $this->library->catalogue->addCopy($this->library->catalogue->add(new Record(TitleKind::Book, 'Dune'))->id, 'B-1'));
    }
}
