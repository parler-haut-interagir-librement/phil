<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Tracking\ReturnState;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Goods a reseller sends back to its supplier for an order it forwarded: the units of each SKU announced, then those
 * the supplier received and credited. The supplier keeps it with the reseller as partner, the reseller with the
 * supplier.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_syndication_return')]
#[ORM\Index(columns: ['partner', 'order_number'])]
final class PartnerReturn
{
    /** What the supplier credited, once received; none before. */
    public ?Money $credit {
        get => null === $this->creditMinorUnits ? null : Money::of($this->creditMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'state', length: 16, enumType: ReturnState::class)]
    private ReturnState $state = ReturnState::Requested;

    /** @var array<int|string, int> */
    #[ORM\Column(name: 'received', type: 'json')]
    private array $received = [];

    #[ORM\Column(name: 'credit', type: 'bigint', nullable: true)]
    private ?int $creditMinorUnits = null;

    #[ORM\Column(name: 'received_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $receivedAt = null;

    /**
     * @param array<int|string, int> $requested SKU → units announced; a numeric SKU is an integer key, as PHP makes it
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'partner', length: 63)]
        public readonly string $partner,
        #[ORM\Id]
        #[ORM\Column(name: 'return_number', length: 64)]
        public readonly string $returnNumber,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'requested', type: 'json')]
        public readonly array $requested,
        #[ORM\Column(name: 'carrier', length: 64, nullable: true)]
        public readonly ?string $carrier,
        #[ORM\Column(name: 'tracking', length: 128, nullable: true)]
        public readonly ?string $tracking,
        #[ORM\Column(name: 'requested_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $requestedAt,
    ) {
    }

    /**
     * @param array<int|string, int> $lines SKU → units sent back, each one or more
     */
    public static function request(Partner $partner, string $returnNumber, string $orderNumber, Currency $currency, array $lines, DateTimeImmutable $at, ?string $carrier = null, ?string $tracking = null): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $returnNumber) || 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $orderNumber)) {
            throw InvalidValue::because('A return and its order are each named in 1 to 64 visible ASCII characters.');
        }

        PartnerShipment::checkLines($lines);

        if (null !== $carrier && 1 !== preg_match('/^[\x20-\x7E]{1,64}$/D', $carrier)) {
            throw InvalidValue::because('A carrier is named in 1 to 64 printable ASCII characters.');
        }

        if (null !== $tracking && 1 !== preg_match('/^[\x21-\x7E]{1,128}$/D', $tracking)) {
            throw InvalidValue::because('A tracking number is 1 to 128 visible ASCII characters.');
        }

        return new self($partner->code, $returnNumber, $orderNumber, $currency->code, $lines, $carrier, $tracking, $at);
    }

    public function state(): ReturnState
    {
        return $this->state;
    }

    public function currency(): Currency
    {
        return Currency::of($this->currencyCode);
    }

    /**
     * @return array<int|string, int> SKU → units the supplier received; none before
     */
    public function received(): array
    {
        return $this->received;
    }

    public function receivedAt(): ?DateTimeImmutable
    {
        return $this->receivedAt;
    }

    /**
     * Records the units the supplier received, never more of a SKU than were announced, and what it credited.
     *
     * @param array<int|string, int> $lines SKU → units received; every unit announced when none is given
     */
    public function receive(array $lines, Money $credit, DateTimeImmutable $at): void
    {
        if (ReturnState::Requested !== $this->state) {
            throw SyndicationRefused::returnReceived($this->returnNumber);
        }

        PartnerShipment::checkLines($lines);

        foreach ($lines as $sku => $quantity) {
            $announced = $this->requested[$sku] ?? 0;

            if ($quantity > $announced) {
                throw SyndicationRefused::overReturned($this->returnNumber, (string) $sku, $quantity, $announced);
            }
        }

        if (!$credit->currency->equals($this->currency()) || $credit->isNegative()) {
            throw InvalidValue::because(\sprintf('The credit of the return %s is zero or more, in its currency.', $this->returnNumber));
        }

        $this->state = ReturnState::Received;
        $this->received = $lines;
        $this->creditMinorUnits = $credit->minorUnits;
        $this->receivedAt = $at;
    }
}
