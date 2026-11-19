<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\LocaleDimensions\Locale;

/**
 * A venue's name in one locale — *Main Hall*, *Grande salle* —; its room, address and coordinates are the venue's.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_calendar_venue_translation')]
#[ORM\UniqueConstraint(name: 'phil_calendar_venue_translation_translatable_locale', columns: ['venue', 'locale'])]
final class VenueTranslation implements Translation
{
    /** The locale, in the one spelling of `phil/locale-dimensions`. */
    #[ORM\Column(name: 'locale', length: Translation::LOCALE_LENGTH)]
    private readonly string $locale;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 200)]
    private string $name = '';

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Venue::class)]
        #[ORM\JoinColumn(name: 'venue', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private readonly Venue $translatable,
        Locale $locale,
    ) {
        $this->locale = $locale->value;
    }

    #[Override]
    public function locale(): Locale
    {
        return Locale::fromString($this->locale);
    }

    #[Override]
    public function translatable(): Venue
    {
        return $this->translatable;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > 200) {
            throw InvalidEvent::because('The name of a venue is 1 to 200 characters.');
        }

        $this->name = $name;
    }
}
