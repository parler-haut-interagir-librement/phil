<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A sale rung up while the register was offline, once imported: the id the register gave it, which makes a second
 * import harmless, and what re-pricing on the server found — the server's total less the register's, zero when
 * they agree, kept for review.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_imported_sale')]
final class ImportedSale
{
    public Money $discrepancy {
        get => Money::of($this->discrepancyMinorUnits, Currency::of($this->currencyCode));
    }

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64)]
        public readonly string $id,
        #[ORM\Column(name: 'session', length: 36, options: ['fixed' => true])]
        public readonly string $session,
        #[ORM\Column(name: 'discrepancy', type: 'bigint')]
        private readonly int $discrepancyMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'rung_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $rungAt,
        #[ORM\Column(name: 'imported_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $importedAt,
    ) {
    }

    public static function of(string $id, CashSession $session, Money $discrepancy, DateTimeImmutable $rungAt, DateTimeImmutable $importedAt): self
    {
        return new self($id, $session->id, $discrepancy->minorUnits, $discrepancy->currency->code, $rungAt, $importedAt);
    }

    public function agrees(): bool
    {
        return 0 === $this->discrepancyMinorUnits;
    }
}
