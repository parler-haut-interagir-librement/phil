<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Symfony\Component\Uid\Uuid;

/**
 * Who holds an event, as the application names them: a kind — `association`, `shop`, `artist` — and a reference
 * within it, an identifier of the application's. The calendar knows nothing more of them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_calendar_event_host')]
#[ORM\UniqueConstraint(columns: ['event', 'kind', 'reference'])]
#[ORM\Index(columns: ['kind', 'reference'])]
final class EventHost
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
    public readonly string $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Event::class)]
        #[ORM\JoinColumn(name: 'event', nullable: false, onDelete: 'CASCADE')]
        public readonly Event $event,
        #[ORM\Column(name: 'kind', length: 64)]
        public readonly string $kind,
        #[ORM\Column(name: 'reference', length: 64)]
        public readonly string $reference,
    ) {
        foreach (['kind' => $kind, 'reference' => $reference] as $name => $value) {
            if (1 !== preg_match('/^[\w.:-]{1,64}$/', $value)) {
                throw InvalidEvent::because(\sprintf('The %s of a host is 1 to 64 letters, digits, "_", ".", ":" or "-".', $name));
            }
        }

        $this->id = Uuid::v7()->toRfc4122();
    }

    public function is(string $kind, string $reference): bool
    {
        return $kind === $this->kind && $reference === $this->reference;
    }
}
