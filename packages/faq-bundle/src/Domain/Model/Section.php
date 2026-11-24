<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\Slug\Slug;

/**
 * A section of the questions: its key, which is also its anchor on the page, and its place among the other sections.
 * Its titles are translations, kept apart. The key stays when a title changes, so a link to the section keeps working.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_faq_section_key', columns: ['section_key'])]
final class Section
{
    public const string TABLE = 'phil_faq_section';

    /** The longest key, in bytes. */
    public const int MAX_KEY = 100;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'section_key', length: self::MAX_KEY)]
        private string $key,
        #[ORM\Column(name: 'position')]
        private int $position,
    ) {
    }

    /**
     * @throws InvalidFaq when the key is too long or the position negative
     */
    public static function create(string $id, Slug $key, int $position): self
    {
        return new self($id, self::checkedKey($key), self::checkedPosition($position));
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * Gives the section another key, and so another anchor: only when someone asks for it, never because a title changed.
     *
     * @throws InvalidFaq when the key is too long
     */
    public function rekey(Slug $key): void
    {
        $this->key = self::checkedKey($key);
    }

    public function position(): int
    {
        return $this->position;
    }

    /**
     * @throws InvalidFaq when the position is negative
     */
    public function moveTo(int $position): void
    {
        $this->position = self::checkedPosition($position);
    }

    /**
     * @throws InvalidFaq when the key is too long
     */
    private static function checkedKey(Slug $key): string
    {
        if (\strlen($key->toString()) > self::MAX_KEY) {
            throw InvalidFaq::because(\sprintf('The key of a section is %d characters or fewer.', self::MAX_KEY));
        }

        return $key->toString();
    }

    /**
     * @throws InvalidFaq when the position is negative
     */
    private static function checkedPosition(int $position): int
    {
        return $position >= 0 ? $position : throw InvalidFaq::because('A position is zero or more.');
    }
}
