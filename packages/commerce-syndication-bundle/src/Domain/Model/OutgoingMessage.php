<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Symfony\Component\Uid\Uuid;

/**
 * A message in the outbox for its partner: tried until it is delivered or given up, then kept, its personal data
 * cleared, until the retention purges it.
 *
 * **Every attempt carries the same id**, so the partner applies it once however many times it arrives. A failed
 * attempt waits twice as long as the one before — 1, 2, 4… minutes — and the last allowed one gives the message up; a
 * refusal that sending again cannot change gives it up at once. A delivery run claims a message before sending it, so
 * that two runs at once never send it both.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_syndication_outgoing')]
#[ORM\Index(columns: ['delivered_at', 'next_attempt_at'])]
#[ORM\Index(columns: ['given_up_at'])]
final class OutgoingMessage
{
    /** The moment of the envelope, to the microsecond: a later message describes its subject after an earlier one. */
    public const string MOMENT = 'Y-m-d\TH:i:s.uP';

    /** The keys of `data` that name a person — the address of a forwarded order —, cleared once the message ends. */
    public const array PERSONAL = ['ship_to'];

    /**
     * The envelope's `data`, without its personal keys once the message is delivered or given up.
     *
     * @var array<string, mixed>
     */
    public array $data {
        get => $this->payload;
    }

    #[ORM\Column(name: 'attempts', type: 'smallint')]
    private int $attempts = 0;

    #[ORM\Column(name: 'next_attempt_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $nextAttemptAt;

    #[ORM\Column(name: 'delivered_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(name: 'last_error', length: 255, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(name: 'given_up_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $givenUpAt = null;

    #[ORM\Column(name: 'claimed_until', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $claimedUntil = null;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36)]
        public readonly string $id,
        #[ORM\Column(name: 'partner', length: 63)]
        public readonly string $partner,
        #[ORM\Column(name: 'type', length: 64)]
        public readonly string $type,
        #[ORM\Column(name: 'data', type: 'json')]
        private array $payload,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $createdAt,
    ) {
        $this->nextAttemptAt = $createdAt;
    }

    /**
     * @param array<string, mixed> $data the envelope's `data`, a JSON object
     */
    public static function to(Partner $partner, string $type, array $data, DateTimeImmutable $at): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_.]*$/D', $type) || \strlen($type) > 64) {
            throw InvalidValue::because(\sprintf('"%s" is not a message type: a dotted name such as "product.updated".', $type));
        }

        return new self('msg_'.Uuid::v7()->toBase58(), $partner->code, $type, $data, $at);
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function nextAttemptAt(): ?DateTimeImmutable
    {
        return $this->nextAttemptAt;
    }

    public function deliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function givenUpAt(): ?DateTimeImmutable
    {
        return $this->givenUpAt;
    }

    public function claimedUntil(): ?DateTimeImmutable
    {
        return $this->claimedUntil;
    }

    /**
     * Due once its next attempt has come, unless a delivery run holds it.
     */
    public function isDue(DateTimeImmutable $at): bool
    {
        return !$this->deliveredAt instanceof DateTimeImmutable
            && $this->nextAttemptAt instanceof DateTimeImmutable && $this->nextAttemptAt <= $at
            && !$this->isClaimed($at);
    }

    public function isClaimed(DateTimeImmutable $at): bool
    {
        return $this->claimedUntil instanceof DateTimeImmutable && $this->claimedUntil > $at;
    }

    /**
     * Held by a delivery run until then: no other run sends it meanwhile.
     */
    public function claim(DateTimeImmutable $until): void
    {
        $this->claimedUntil = $until;
    }

    public function delivered(DateTimeImmutable $at): void
    {
        ++$this->attempts;
        $this->deliveredAt = $at;
        $this->nextAttemptAt = null;
        $this->lastError = null;
        $this->claimedUntil = null;
        $this->forgetPersonalData();
    }

    /**
     * Records a failed attempt; after `$maxAttempts`, the message is given up and never tried again.
     */
    public function failed(DateTimeImmutable $at, string $error, int $maxAttempts): void
    {
        ++$this->attempts;
        $this->lastError = mb_substr($error, 0, 255);
        $this->claimedUntil = null;

        if ($this->attempts >= $maxAttempts) {
            $this->end($at);

            return;
        }

        $this->nextAttemptAt = $at->modify(\sprintf('+%d minutes', 2 ** ($this->attempts - 1)));
    }

    /**
     * Records an attempt the partner refused for good — sending the same message again would change nothing —, and
     * gives the message up.
     */
    public function refused(DateTimeImmutable $at, string $error): void
    {
        ++$this->attempts;
        $this->lastError = mb_substr($error, 0, 255);
        $this->claimedUntil = null;
        $this->end($at);
    }

    private function end(DateTimeImmutable $at): void
    {
        $this->nextAttemptAt = null;
        $this->givenUpAt = $at;
        $this->forgetPersonalData();
    }

    private function forgetPersonalData(): void
    {
        foreach (self::PERSONAL as $key) {
            unset($this->payload[$key]);
        }
    }

    /**
     * The envelope sent: its type, its moment, its data, and the partner it is addressed to.
     */
    public function body(): string
    {
        return json_encode(['type' => $this->type, 'timestamp' => $this->createdAt->format(self::MOMENT), 'audience' => $this->partner, 'data' => (object) $this->payload], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
