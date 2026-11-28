<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use Symfony\Component\Uid\Uuid;

/**
 * A participant's seats in a session: confirmed, until cancelled. Its participant is an identifier the application
 * gives — an account, an order, a ticket —, never a name or an address: a participant that looks like an e-mail address
 * is refused, since an address changes and identifies a person.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_workshops_registration_seats', columns: ['session', 'status'])]
#[ORM\Index(name: 'phil_workshops_registration_participant', columns: ['session', 'participant'])]
final class Registration
{
    public const string TABLE = 'phil_workshops_registration';

    /** The longest participant identifier, in bytes. */
    public const int PARTICIPANT_LENGTH = 255;

    /** What an e-mail address looks like: something, an at sign, a domain with a dot. */
    private const string EMAIL = '/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/D';

    #[ORM\Column(name: 'cancelled_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: WorkshopSession::class)]
        #[ORM\JoinColumn(name: 'session', nullable: false, onDelete: 'CASCADE')]
        private WorkshopSession $session,
        #[ORM\Column(name: 'participant', length: self::PARTICIPANT_LENGTH)]
        private string $participant,
        #[ORM\Column(name: 'seats')]
        private int $seats,
        #[ORM\Column(name: 'status', length: 16, enumType: RegistrationStatus::class)]
        private RegistrationStatus $status,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Seats taken: at once, or by accepting a place a waiting list offered.
     *
     * @throws InvalidWorkshop when the participant is not an identifier, or the seats below one
     */
    public static function confirmed(WorkshopSession $session, string $participant, int $seats, DateTimeImmutable $now): self
    {
        return self::make($session, $participant, $seats, $now);
    }

    public function session(): WorkshopSession
    {
        return $this->session;
    }

    public function participant(): string
    {
        return $this->participant;
    }

    public function seats(): int
    {
        return $this->seats;
    }

    public function status(): RegistrationStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->local($this->createdAt);
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt instanceof DateTimeImmutable ? $this->local($this->cancelledAt) : null;
    }

    /**
     * @throws InvalidWorkshop when it is already cancelled
     */
    public function cancel(DateTimeImmutable $now): void
    {
        if (!$this->status->isLive()) {
            throw InvalidWorkshop::because('A cancelled registration is not cancelled again.');
        }

        $this->status = RegistrationStatus::Cancelled;
        $this->cancelledAt = $now;
    }

    private static function make(WorkshopSession $session, string $participant, int $seats, DateTimeImmutable $now): self
    {
        if ('' === $participant || \strlen($participant) > self::PARTICIPANT_LENGTH) {
            throw InvalidWorkshop::because(\sprintf('A participant is an identifier of 1 to %d bytes.', self::PARTICIPANT_LENGTH));
        }

        if (1 === preg_match(self::EMAIL, $participant)) {
            throw InvalidWorkshop::because('A participant is a stable identifier, never an e-mail address.');
        }

        if ($seats < 1) {
            throw InvalidWorkshop::because('A registration takes at least one seat.');
        }

        return new self(Uuid::v7()->toRfc4122(), $session, $participant, $seats, RegistrationStatus::Confirmed, $now);
    }

    private function local(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone($this->session->timezone());
    }
}
