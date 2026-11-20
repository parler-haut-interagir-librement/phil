<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Session\CashMovementKind;
use Phil\CommercePosBundle\Domain\Session\SessionState;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A register's shift, from the float counted at the opening to the cash counted at the close: the cash the drawer
 * should hold follows each movement, and the close tells what was over or short.
 *
 * People are named by whatever the application identifies them with: a user identifier, a staff number.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_session')]
#[ORM\Index(columns: ['register', 'state'])]
final class CashSession
{
    public Money $expected {
        get => Money::of($this->expectedMinorUnits, Currency::of($this->currencyCode));
    }

    public ?Money $counted {
        get => null === $this->countedMinorUnits ? null : Money::of($this->countedMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'expected', type: 'bigint')]
    private int $expectedMinorUnits = 0;

    #[ORM\Column(name: 'state', length: 8, enumType: SessionState::class)]
    private SessionState $state = SessionState::Open;

    #[ORM\Column(name: 'counted', type: 'bigint', nullable: true)]
    private ?int $countedMinorUnits = null;

    #[ORM\Column(name: 'closed_by', length: 255, nullable: true)]
    private ?string $closedBy = null;

    #[ORM\Column(name: 'closed_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'register', length: 32)]
        public readonly string $register,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'opened_by', length: 255)]
        public readonly string $openedBy,
        #[ORM\Column(name: 'opened_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $openedAt,
        #[ORM\Column(name: 'tax_included', type: 'boolean', options: ['default' => false])]
        public readonly bool $taxIncluded,
    ) {
    }

    /**
     * Opens a session on the float counted in the drawer, zero or more. A session whose prices include the taxes — a
     * day at a fair, where the price on the tag is the price paid — sells every cart of the session that way.
     *
     * @return array{self, CashMovement}
     */
    public static function open(Register $register, string $by, Money $float, DateTimeImmutable $at, bool $taxIncluded = false): array
    {
        if ($float->isNegative()) {
            throw InvalidValue::because('A float is zero or more.');
        }

        $session = new self(Uuid::v7()->toRfc4122(), $register->code, $register->currency()->code, $by, $at, $taxIncluded);

        return [$session, $session->move(CashMovementKind::Float, $float, Money::zero($float->currency), $by, $at)];
    }

    public function currency(): Currency
    {
        return Currency::of($this->currencyCode);
    }

    public function state(): SessionState
    {
        return $this->state;
    }

    public function isOpen(): bool
    {
        return SessionState::Open === $this->state;
    }

    public function closedBy(): ?string
    {
        return $this->closedBy;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * Records cash in or out; the amount is given positive, the kind tells the direction.
     *
     * @param Money $rounding for a sale or a refund, the cash counted less the payment's amount
     */
    public function move(CashMovementKind $kind, Money $amount, Money $rounding, string $by, DateTimeImmutable $at, ?string $payment = null, ?string $note = null): CashMovement
    {
        if (!$this->isOpen()) {
            throw PosRefused::sessionClosed($this->id);
        }

        $this->checkPerson($by);

        if ($amount->isNegative() || !$amount->currency->equals($this->currency()) || !$rounding->currency->equals($this->currency())) {
            throw InvalidValue::because(\sprintf('A cash movement of the session %s is zero or more, in %s.', $this->id, $this->currencyCode));
        }

        $signed = $kind->addsCash() ? $amount : $amount->negate();
        $this->expectedMinorUnits += $signed->minorUnits;

        return CashMovement::record($this, $kind, $signed, $rounding, $by, $at, $payment, $note);
    }

    /**
     * Closes the session on the cash counted in the drawer.
     */
    public function close(Money $counted, string $by, DateTimeImmutable $at): void
    {
        if (!$this->isOpen()) {
            throw PosRefused::sessionClosed($this->id);
        }

        $this->checkPerson($by);

        if ($counted->isNegative() || !$counted->currency->equals($this->currency())) {
            throw InvalidValue::because(\sprintf('The cash counted in the session %s is zero or more, in %s.', $this->id, $this->currencyCode));
        }

        $this->countedMinorUnits = $counted->minorUnits;
        $this->closedBy = $by;
        $this->closedAt = $at;
        $this->state = SessionState::Closed;
    }

    /**
     * What the count found more — positive — or less — negative — than expected; `null` while open.
     */
    public function overShort(): ?Money
    {
        return $this->counted?->subtract($this->expected);
    }

    private function checkPerson(string $by): void
    {
        if ('' === trim($by) || mb_strlen($by) > 255) {
            throw InvalidValue::because('A person at the register is named in 1 to 255 characters.');
        }
    }
}
