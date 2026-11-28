<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Symfony\Component\Uid\Uuid;

/**
 * What is taught, given once or many times: its title, and whether it takes new sessions. Each time it is given is a
 * `WorkshopSession`. What the application adds — a description, a price, the material, an age — it keeps in its own
 * tables by the workshop's identifier.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class Workshop
{
    public const string TABLE = 'phil_workshops_workshop';

    /** The longest title, in characters. */
    public const int TITLE_LENGTH = 200;

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'title', length: self::TITLE_LENGTH)]
        private string $title,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @throws InvalidWorkshop when the title is empty or too long
     */
    public static function create(string $title, DateTimeImmutable $now): self
    {
        return new self(Uuid::v7()->toRfc4122(), self::checked($title), $now);
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @throws InvalidWorkshop when the title is empty or too long
     */
    public function rename(string $title): void
    {
        $this->title = self::checked($title);
    }

    /**
     * When it was created, in UTC.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt->setTimezone(new DateTimeZone('UTC'));
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * An inactive workshop takes no new session; its sessions already scheduled go on.
     */
    public function activate(bool $active): void
    {
        $this->active = $active;
    }

    private static function checked(string $title): string
    {
        $title = trim($title);

        if ('' === $title || mb_strlen($title) > self::TITLE_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $title)) {
            throw InvalidWorkshop::because(\sprintf('A workshop title has 1 to %d characters, on one line.', self::TITLE_LENGTH));
        }

        return $title;
    }
}
