<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * An order rung up at a register, in a session: what the session's report counts as its sales, the tip it took —
 * kept here, so that the report reads no order — and the sale preset it was sold under.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_sale')]
#[ORM\Index(columns: ['session'])]
final class RegisterSale
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'session', length: 36, options: ['fixed' => true])]
        public readonly string $session,
        #[ORM\Column(name: 'register', length: 32)]
        public readonly string $register,
        #[ORM\Column(name: 'by_whom', length: 255)]
        public readonly string $by,
        #[ORM\Column(name: 'rung_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $rungAt,
        #[ORM\Column(name: 'tip', type: 'bigint')]
        private readonly int $tipMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'preset', length: 32, nullable: true)]
        public readonly ?string $preset,
    ) {
    }

    /**
     * @param Money|null  $tip    the tip the order took, in the session's currency; none by default
     * @param string|null $preset the code of the sale preset it was sold under, if any
     */
    public static function of(string $orderNumber, CashSession $session, string $by, DateTimeImmutable $at, ?Money $tip = null, ?string $preset = null): self
    {
        $tip ??= Money::zero($session->currency());

        if ($tip->isNegative() || !$tip->currency->equals($session->currency())) {
            throw InvalidValue::because(\sprintf('The tip of the sale %s is zero or more, in %s.', $orderNumber, $session->currency()->code));
        }

        return new self($orderNumber, $session->id, $session->register, $by, $at, $tip->minorUnits, $tip->currency->code, $preset);
    }

    public function tip(): Money
    {
        return Money::of($this->tipMinorUnits, Currency::of($this->currencyCode));
    }
}
