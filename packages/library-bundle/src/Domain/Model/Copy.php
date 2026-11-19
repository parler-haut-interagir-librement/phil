<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Symfony\Component\Uid\Uuid;

/**
 * One object of a title that is lent: its barcode — what a scanner reads at the desk —, its condition and a note on it —
 * the pieces missing from a box —, and the item of a stockroom it is, when the library's shelves are part of one.
 *
 * Each loan of the copy claims it: the claim raises its version, so that two loans of the same copy made at once cannot
 * both be kept.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_library_copy_barcode', columns: ['barcode'])]
#[ORM\Index(name: 'phil_library_copy_title', columns: ['title'])]
final class Copy
{
    public const string TABLE = 'phil_library_copy';

    /** What a barcode looks like: letters, digits, dots, underscores and hyphens, starting with a letter or a digit. */
    private const string BARCODE = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D';

    /** What the identifier of a stockroom item looks like. */
    private const string ITEM = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D';

    #[ORM\Column(name: 'note', length: 1000, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'stockroom_item', length: 128, nullable: true)]
    private ?string $stockroomItem = null;

    #[ORM\Column(name: 'claims', type: 'integer')]
    private int $claims = 0;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Title::class)]
        #[ORM\JoinColumn(name: 'title', nullable: false, onDelete: 'CASCADE')]
        private Title $title,
        #[ORM\Column(name: 'barcode', length: 64)]
        private string $barcode,
        #[ORM\Column(name: 'state', length: 16, enumType: CopyCondition::class)]
        private CopyCondition $condition,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @throws InvalidLibraryData when the barcode or the stockroom item is not one
     */
    public static function of(Title $title, string $barcode, DateTimeImmutable $now, CopyCondition $condition = CopyCondition::Good, ?string $stockroomItem = null): self
    {
        if (1 !== preg_match(self::BARCODE, $barcode)) {
            throw InvalidLibraryData::because('A barcode is 1 to 64 letters, digits, dots, underscores or hyphens.');
        }

        $copy = new self(Uuid::v7()->toRfc4122(), $title, $barcode, $condition, $now);
        $copy->link($stockroomItem);

        return $copy;
    }

    public function title(): Title
    {
        return $this->title;
    }

    public function barcode(): string
    {
        return $this->barcode;
    }

    public function condition(): CopyCondition
    {
        return $this->condition;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * The item of a stockroom this copy is, or null when it is not one.
     */
    public function stockroomItem(): ?string
    {
        return $this->stockroomItem;
    }

    public function mayLend(): bool
    {
        return $this->condition->mayLend();
    }

    /**
     * Links the copy to the item of a stockroom it is, or unlinks it with null: a loan of a linked copy moves the item.
     *
     * @throws InvalidLibraryData when the identifier is not one
     */
    public function link(?string $stockroomItem): void
    {
        if (null !== $stockroomItem && 1 !== preg_match(self::ITEM, $stockroomItem)) {
            throw InvalidLibraryData::because('The item of a stockroom is named by an identifier of 1 to 128 letters, digits, dots, underscores, colons or hyphens.');
        }

        $this->stockroomItem = $stockroomItem;
    }

    /**
     * Records its condition and a note on it — the pieces missing —, the note removed when none is given.
     *
     * @throws InvalidLibraryData when the note is too long or holds a control character
     */
    public function assess(CopyCondition $condition, ?string $note = null): void
    {
        $note = null === $note ? '' : trim($note);

        if (mb_strlen($note) > 1000 || 1 === preg_match('/\p{Cc}/u', $note) || !mb_check_encoding($note, 'UTF-8')) {
            throw InvalidLibraryData::because('The note on a copy is a text of at most 1000 characters, without control characters.');
        }

        $this->condition = $condition;
        $this->note = '' === $note ? null : $note;
    }

    /**
     * Claims the copy for a loan: its version rises, so that a loan made meanwhile from an older read is refused.
     */
    public function claim(): void
    {
        ++$this->claims;
    }

    public function claims(): int
    {
        return $this->claims;
    }
}
