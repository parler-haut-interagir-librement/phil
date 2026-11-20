<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceSubscriptionBundle\Domain\Dunning\DunningAccess;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DunningStage;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * One tier of a family of offers: what it costs per period, how long its trial lasts, and the features it unlocks.
 *
 * A free tier, paid tiers and support tiers are the same thing: offers of one family, ranked, some at a price of
 * zero. A subscription changes tier within its family. An offer is withdrawn rather than deleted, so that the
 * subscriptions to it keep their features.
 *
 * An offer held by an organization opens its features to every member of it, or, when it counts **seats**, to the
 * members holding one.
 *
 * While a renewal is past due, its **dunning stages** say what the subscriber keeps, from so many days past due on:
 * every feature, some of them, or none. An offer without stages keeps every feature while the retries last.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_subscription_offer')]
#[ORM\Index(columns: ['family'])]
final class Offer
{
    public Money $price {
        get => Money::of($this->priceMinorUnits, Currency::of($this->currencyCode));
    }

    public BillingPeriod $period {
        get => BillingPeriod::of($this->periodUnit, $this->periodCount);
    }

    #[ORM\Column(name: 'price', type: 'bigint')]
    private int $priceMinorUnits;

    #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
    private string $currencyCode;

    #[ORM\Column(name: 'period_unit', length: 8, enumType: BillingUnit::class)]
    private BillingUnit $periodUnit;

    #[ORM\Column(name: 'period_count', type: 'smallint')]
    private int $periodCount;

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    /** @var list<array{after_days: int, access: string, features: list<string>}> */
    #[ORM\Column(name: 'dunning', type: 'json', options: ['default' => '[]'])]
    private array $dunning = [];

    /**
     * @param list<string> $features
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'family', length: 64)]
        public readonly string $family,
        #[ORM\Column(name: 'tier_rank', type: 'smallint')]
        public readonly int $rank,
        #[ORM\Column(name: 'name', length: 255)]
        private string $name,
        Money $price,
        BillingPeriod $period,
        #[ORM\Column(name: 'trial_days', type: 'smallint')]
        public readonly int $trialDays,
        #[ORM\Column(name: 'tax_category', length: 64)]
        public readonly string $taxCategory,
        #[ORM\Column(name: 'features', type: 'json')]
        private array $features,
        #[ORM\Column(name: 'seats', type: 'integer', nullable: true)]
        public readonly ?int $seats,
    ) {
        $this->priceMinorUnits = $price->minorUnits;
        $this->currencyCode = $price->currency->code;
        $this->periodUnit = $period->unit;
        $this->periodCount = $period->count;
    }

    /**
     * @param list<string> $features the features the tier unlocks, as the application names them
     * @param int|null     $seats    how many members of an organization holding it use its features; `null` for all
     */
    public static function create(string $code, string $family, int $rank, string $name, Money $price, BillingPeriod $period, int $trialDays, string $taxCategory, array $features = [], ?int $seats = null): self
    {
        foreach (['code' => $code, 'family' => $family] as $what => $value) {
            if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $value)) {
                throw InvalidValue::because(\sprintf('"%s" is not an offer %s: up to 64 lowercase letters, digits, "_", "." or "-".', $value, $what));
            }
        }

        if ($rank < 0 || $trialDays < 0 || $trialDays > 365) {
            throw InvalidValue::because('An offer ranks from 0, and its trial lasts 0 to 365 days.');
        }

        if (null !== $seats && ($seats < 1 || $seats > 100_000)) {
            throw InvalidValue::because(\sprintf('The offer "%s" counts 1 to 100000 seats, or none.', $code));
        }

        if ('' === trim($name) || '' === $taxCategory) {
            throw InvalidValue::because(\sprintf('The offer "%s" has a name and a tax category.', $code));
        }

        if ($price->isNegative()) {
            throw InvalidValue::because(\sprintf('The offer "%s" has a negative price.', $code));
        }

        foreach ($features as $feature) {
            if (1 !== preg_match('/^[a-z0-9][a-z0-9_.:-]{0,127}$/D', $feature)) {
                throw InvalidValue::because(\sprintf('"%s" is not a feature name: up to 128 lowercase letters, digits, "_", ".", ":" or "-".', $feature));
            }
        }

        return new self($code, $family, $rank, $name, $price, $period, $trialDays, $taxCategory, array_values(array_unique($features)), $seats);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function features(): array
    {
        return $this->features;
    }

    /**
     * @return list<DunningStage> the stages, in the order of their days
     */
    public function dunningStages(): array
    {
        return array_map(DunningStage::fromArray(...), $this->dunning);
    }

    /**
     * Sets the dunning stages, each starting later than the one before, at most ten; a reduced access keeps only
     * features of the tier. No stage keeps every feature while the retries last.
     *
     * @param list<DunningStage> $stages
     */
    public function changeDunning(array $stages): void
    {
        if (\count($stages) > 10) {
            throw InvalidValue::because(\sprintf('The offer "%s" has at most ten dunning stages.', $this->code));
        }

        $stored = [];
        $previous = null;

        foreach ($stages as $stage) {
            if (null !== $previous && $stage->afterDays <= $previous) {
                throw InvalidValue::because(\sprintf('The dunning stages of the offer "%s" each start later than the one before.', $this->code));
            }

            if ([] !== array_diff($stage->features, $this->features)) {
                throw InvalidValue::because(\sprintf('A reduced access of the offer "%s" keeps only features of the tier.', $this->code));
            }

            $previous = $stage->afterDays;
            $stored[] = $stage->toArray();
        }

        $this->dunning = $stored;
    }

    /**
     * The features a subscriber keeps so many whole days past due: those of the last stage started, every feature
     * before the first.
     *
     * @return list<string>
     */
    public function featuresPastDue(int $days): array
    {
        $stage = null;

        foreach ($this->dunningStages() as $candidate) {
            if ($candidate->afterDays <= $days) {
                $stage = $candidate;
            }
        }

        return match ($stage?->access) {
            null, DunningAccess::Full => $this->features,
            DunningAccess::Reduced => $stage->features,
            DunningAccess::None => [],
        };
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isFree(): bool
    {
        return $this->price->isZero();
    }

    /**
     * A new price, for the periods ordered from now on; periods already paid keep theirs.
     */
    public function reprice(Money $price): void
    {
        if ($price->isNegative() || !$price->currency->equals($this->price->currency)) {
            throw InvalidValue::because(\sprintf('The offer "%s" is repriced in %s, at zero or more.', $this->code, $this->currencyCode));
        }

        $this->priceMinorUnits = $price->minorUnits;
    }

    /**
     * Nobody subscribes to it any more; the subscriptions to it go on.
     */
    public function withdraw(): void
    {
        $this->active = false;
    }
}
