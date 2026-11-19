<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application;

use Phil\LibraryBundle\Application\Exception\NotFound;
use Phil\LibraryBundle\Domain\Contract\CopyRepository;
use Phil\LibraryBundle\Domain\Contract\TitleRepository;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Title;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Psr\Clock\ClockInterface;

/**
 * The catalogue: its titles and their records, their copies, the condition of each and the stockroom item it is.
 */
final readonly class Catalogue
{
    /** The most copies a title lists. */
    public const int MAX_COPIES = 500;

    public function __construct(
        private TitleRepository $titles,
        private CopyRepository $copies,
        private ClockInterface $clock,
    ) {
    }

    public function add(Record $record): Title
    {
        $title = Title::catalogue($record, $this->clock->now());
        $this->titles->save($title);

        return $title;
    }

    /**
     * @throws NotFound when the title does not exist
     */
    public function describe(string $title, Record $record): Title
    {
        $found = $this->title($title);
        $found->describe($record);

        $this->titles->save($found);

        return $found;
    }

    /**
     * @throws NotFound when the title does not exist
     */
    public function title(string $id): Title
    {
        return $this->titles->get($id) ?? throw NotFound::title($id);
    }

    /**
     * A new copy of a title, its barcode unique.
     *
     * @throws NotFound           when the title does not exist
     * @throws InvalidLibraryData when the barcode is taken or not one, or the stockroom item is not an identifier
     */
    public function addCopy(string $title, string $barcode, CopyCondition $condition = CopyCondition::Good, ?string $stockroomItem = null): Copy
    {
        $found = $this->title($title);

        if ($this->copies->byBarcode($barcode) instanceof Copy) {
            throw InvalidLibraryData::because(\sprintf('The barcode "%s" is taken.', $barcode));
        }

        $copy = Copy::of($found, $barcode, $this->clock->now(), $condition, $stockroomItem);
        $this->copies->save($copy);

        return $copy;
    }

    /**
     * The copies of a title, in the order of their barcodes.
     *
     * @return list<Copy>
     *
     * @throws NotFound when the title does not exist
     */
    public function copies(string $title): array
    {
        return $this->copies->ofTitle($this->title($title), self::MAX_COPIES);
    }

    /**
     * @throws NotFound when the copy does not exist
     */
    public function copy(string $id): Copy
    {
        return $this->copies->get($id) ?? throw NotFound::copy($id);
    }

    /**
     * @throws NotFound when no copy carries the barcode
     */
    public function copyByBarcode(string $barcode): Copy
    {
        return $this->copies->byBarcode($barcode) ?? throw NotFound::copy($barcode);
    }

    /**
     * @throws NotFound           when the copy does not exist
     * @throws InvalidLibraryData when the note is not one
     */
    public function assess(string $copy, CopyCondition $condition, ?string $note = null): Copy
    {
        $found = $this->copy($copy);
        $found->assess($condition, $note);

        $this->copies->save($found);

        return $found;
    }

    /**
     * Links a copy to the stockroom item it is, or unlinks it with null.
     *
     * @throws NotFound           when the copy does not exist
     * @throws InvalidLibraryData when the item is not an identifier
     */
    public function link(string $copy, ?string $stockroomItem): Copy
    {
        $found = $this->copy($copy);
        $found->link($stockroomItem);

        $this->copies->save($found);

        return $found;
    }
}
