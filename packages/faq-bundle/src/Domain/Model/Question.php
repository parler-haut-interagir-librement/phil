<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\Slug\Slug;

/**
 * A question of a section: its anchor, the stable fragment of its address, its place in the section, and the counts of
 * the readers who found its answer useful or not. Its wording and its answer are translations, kept apart.
 *
 * The anchor is chosen once, from the first wording, and stays when the wording changes: a shared link keeps opening
 * the same question. The counts are totals only: nothing says who voted.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_faq_question_anchor', columns: ['anchor'])]
#[ORM\Index(name: 'phil_faq_question_order', columns: ['section_id', 'position'])]
final class Question
{
    public const string TABLE = 'phil_faq_question';

    /** The longest anchor, in bytes. */
    public const int MAX_ANCHOR = 100;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Section::class)]
        #[ORM\JoinColumn(name: 'section_id', nullable: false, onDelete: 'CASCADE')]
        private Section $section,
        #[ORM\Column(name: 'anchor', length: self::MAX_ANCHOR)]
        private string $anchor,
        #[ORM\Column(name: 'position')]
        private int $position,
        #[ORM\Column(name: 'helpful_yes', options: ['default' => 0])]
        private int $helpfulYes = 0,
        #[ORM\Column(name: 'helpful_no', options: ['default' => 0])]
        private int $helpfulNo = 0,
    ) {
    }

    /**
     * @throws InvalidFaq when the anchor is too long or the position negative
     */
    public static function create(string $id, Section $section, Slug $anchor, int $position): self
    {
        return new self($id, $section, self::checkedAnchor($anchor), self::checkedPosition($position));
    }

    public function section(): Section
    {
        return $this->section;
    }

    public function anchor(): string
    {
        return $this->anchor;
    }

    /**
     * Gives the question another anchor: only when someone asks for it, never because its wording changed.
     *
     * @throws InvalidFaq when the anchor is too long
     */
    public function reanchor(Slug $anchor): void
    {
        $this->anchor = self::checkedAnchor($anchor);
    }

    public function position(): int
    {
        return $this->position;
    }

    /**
     * Puts the question at a place of a section, its own or another.
     *
     * @throws InvalidFaq when the position is negative
     */
    public function moveTo(Section $section, int $position): void
    {
        $this->section = $section;
        $this->position = self::checkedPosition($position);
    }

    public function helpfulYes(): int
    {
        return $this->helpfulYes;
    }

    public function helpfulNo(): int
    {
        return $this->helpfulNo;
    }

    /**
     * @throws InvalidFaq when the anchor is too long
     */
    private static function checkedAnchor(Slug $anchor): string
    {
        if (\strlen($anchor->toString()) > self::MAX_ANCHOR) {
            throw InvalidFaq::because(\sprintf('The anchor of a question is %d characters or fewer.', self::MAX_ANCHOR));
        }

        return $anchor->toString();
    }

    /**
     * @throws InvalidFaq when the position is negative
     */
    private static function checkedPosition(int $position): int
    {
        return $position >= 0 ? $position : throw InvalidFaq::because('A position is zero or more.');
    }
}
