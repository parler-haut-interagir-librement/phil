<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\LocaleDimensions\Locale;

/**
 * An event's title and description in one locale. The event keeps what every locale shares — its times, its place, its
 * page —, and the text it was created with, served when no translation of the chain exists.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_calendar_event_translation')]
#[ORM\UniqueConstraint(name: 'phil_calendar_event_translation_translatable_locale', columns: ['event', 'locale'])]
final class EventTranslation implements Translation
{
    /** The locale, in the one spelling of `phil/locale-dimensions`. */
    #[ORM\Column(name: 'locale', length: Translation::LOCALE_LENGTH)]
    private readonly string $locale;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'title', length: 200)]
    private string $title = '';

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Event::class)]
        #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private readonly Event $translatable,
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
    public function translatable(): Event
    {
        return $this->translatable;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * The text in this locale, held to the event's own limits: a title of 1 to 200 characters, a description of 65 535.
     */
    public function write(string $title, ?string $description = null): void
    {
        $title = trim($title);

        if ('' === $title || mb_strlen($title) > 200) {
            throw InvalidEvent::because('The title of an event is 1 to 200 characters.');
        }

        $description = null === $description || '' === trim($description) ? null : trim($description);

        if (null !== $description && mb_strlen($description) > 65535) {
            throw InvalidEvent::because('The description of an event is 65535 characters or fewer.');
        }

        $this->title = $title;
        $this->description = $description;
    }
}
