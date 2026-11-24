<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\ValueObject\Locale;

/**
 * The title of a section in one language.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_faq_section_locale', columns: ['section_id', 'locale'])]
final class SectionTranslation
{
    public const string TABLE = 'phil_faq_section_translation';

    /** The longest title, in characters. */
    public const int MAX_TITLE = 150;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Section::class)]
        #[ORM\JoinColumn(name: 'section_id', nullable: false, onDelete: 'CASCADE')]
        public readonly Section $section,
        #[ORM\Column(name: 'locale', length: 16)]
        public readonly string $locale,
        #[ORM\Column(name: 'title', length: self::MAX_TITLE)]
        private string $title,
    ) {
    }

    /**
     * @throws InvalidFaq when the title is empty or too long
     */
    public static function of(string $id, Section $section, Locale $locale, string $title): self
    {
        return new self($id, $section, $locale->value, self::checkedTitle($title));
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @throws InvalidFaq when the title is empty or too long
     */
    public function retitle(string $title): void
    {
        $this->title = self::checkedTitle($title);
    }

    /**
     * @throws InvalidFaq when the title is empty or too long
     */
    private static function checkedTitle(string $title): string
    {
        $title = trim($title);

        if ('' === $title || mb_strlen($title) > self::MAX_TITLE) {
            throw InvalidFaq::because(\sprintf('The title of a section is 1 to %d characters.', self::MAX_TITLE));
        }

        return $title;
    }
}
