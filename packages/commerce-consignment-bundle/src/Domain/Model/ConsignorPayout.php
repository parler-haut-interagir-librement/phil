<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Money the shop paid a consignor, as the shop says it paid it: the bundle moves no money, it writes down what was paid.
 * Never changed, never deleted: a payout written by mistake is cancelled by its reversal, a payout of the opposite
 * amount that names it.
 *
 * The caller gives each payout a key of its own — the identifier of the transfer, of the cheque, of the form that sent
 * it —, unique in the table: the same payout written twice is refused, even by two requests at once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_consignment_payout')]
#[ORM\UniqueConstraint(name: 'phil_commerce_consignment_payout_key', columns: ['idempotency_key'])]
#[ORM\UniqueConstraint(name: 'phil_commerce_consignment_payout_reverses', columns: ['reverses'])]
#[ORM\Index(name: 'phil_commerce_consignment_payout_consignor', columns: ['consignor', 'currency'])]
final class ConsignorPayout
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'idempotency_key', length: 64)]
        public readonly string $idempotencyKey,
        #[ORM\Column(name: 'consignor', length: 64)]
        public readonly string $consignor,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currency,
        #[ORM\Column(name: 'amount', type: 'bigint')]
        private readonly int $amount,
        #[ORM\Column(name: 'paid_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $paidAt,
        #[ORM\Column(name: 'method', length: 32)]
        public readonly string $method,
        #[ORM\Column(name: 'reference', length: 64, nullable: true)]
        public readonly ?string $reference,
        #[ORM\Column(name: 'reverses', length: 36, nullable: true, options: ['fixed' => true])]
        public readonly ?string $reverses,
        #[ORM\Column(name: 'recorded_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $recordedAt,
    ) {
    }

    /**
     * @param string      $method    how it was paid — `transfer`, `cheque`, `cash`, `store_credit` —, as the application
     *                               names it
     * @param string|null $reference a free reference: the transfer's, the cheque's number
     */
    public static function record(string $idempotencyKey, Owner $consignor, Money $amount, DateTimeImmutable $paidAt, string $method, ?string $reference, DateTimeImmutable $recordedAt): self
    {
        if (!$consignor->isConsigned()) {
            throw InvalidValue::because('A payout is made to a consignor, not to the shop.');
        }

        if (!$amount->isPositive()) {
            throw InvalidValue::because(\sprintf('A payout is more than zero; %s is not.', $amount->toDecimal()));
        }

        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,31}$/D', $method)) {
            throw InvalidValue::because(\sprintf('A payout method has 1 to 32 lowercase letters, digits, dots, dashes or underscores; "%s" does not.', mb_substr($method, 0, 40)));
        }

        $utc = new DateTimeZone('UTC');

        return new self(
            Uuid::v7()->toRfc4122(),
            InvalidValue::checkIdentifier($idempotencyKey, 'payout key'),
            (string) $consignor->consignor,
            $amount->currency->code,
            $amount->minorUnits,
            $paidAt->setTimezone($utc),
            $method,
            null === $reference ? null : InvalidValue::checkIdentifier($reference, 'payout reference'),
            null,
            $recordedAt->setTimezone($utc),
        );
    }

    /**
     * The payout that cancels this one: the opposite amount, by the same method and reference, at the instant written.
     */
    public function reversal(string $idempotencyKey, DateTimeImmutable $recordedAt): self
    {
        if ($this->isReversal()) {
            throw InvalidValue::because(\sprintf('The payout %s reverses another; record the payout again instead of reversing its reversal.', $this->id));
        }

        $utc = new DateTimeZone('UTC');

        return new self(
            Uuid::v7()->toRfc4122(),
            InvalidValue::checkIdentifier($idempotencyKey, 'payout key'),
            $this->consignor,
            $this->currency,
            -$this->amount,
            $recordedAt->setTimezone($utc),
            $this->method,
            $this->reference,
            $this->id,
            $recordedAt->setTimezone($utc),
        );
    }

    public function owner(): Owner
    {
        return Owner::consignor($this->consignor);
    }

    /**
     * What was paid; negative for a reversal.
     */
    public function amount(): Money
    {
        return Money::of($this->amount, Currency::of($this->currency));
    }

    public function paidAt(): DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function isReversal(): bool
    {
        return null !== $this->reverses;
    }
}
