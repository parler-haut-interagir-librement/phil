<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Support;

use Override;
use Phil\CommercePromotionsBundle\Domain\Contract\PromotionRepository;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCustomerUse;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionRedemption;

final class InMemoryPromotions implements PromotionRepository
{
    /** @var array<string, Promotion> */
    private array $promotions = [];

    /** @var array<string, PromotionCode> */
    public array $codes = [];

    /** @var array<string, PromotionRedemption> order number and promotion → redemption */
    public array $redemptions = [];

    /** @var array<string, PromotionCustomerUse> promotion and customer → uses */
    public array $customerUses = [];

    /** @var array<string, int> how many times each code was looked up */
    public array $lookups = [];

    /** @var list<string> the promotions deleted by another request right after a use of theirs is claimed */
    public array $deletedOnClaim = [];

    public function __construct(Promotion ...$promotions)
    {
        foreach ($promotions as $promotion) {
            $this->add($promotion);
        }
    }

    #[Override]
    public function find(string $code): ?Promotion
    {
        return $this->promotions[$code] ?? null;
    }

    #[Override]
    public function active(): array
    {
        $promotions = array_filter($this->promotions, static fn (Promotion $promotion): bool => $promotion->isActive());
        ksort($promotions, \SORT_STRING);

        return array_values($promotions);
    }

    #[Override]
    public function add(Promotion $promotion): void
    {
        $this->promotions[$promotion->code] = $promotion;
    }

    #[Override]
    public function findCode(string $code): ?PromotionCode
    {
        $this->lookups[$code] = ($this->lookups[$code] ?? 0) + 1;

        return $this->codes[$code] ?? null;
    }

    #[Override]
    public function addCode(PromotionCode $code): void
    {
        $this->codes[$code->code] = $code;
    }

    #[Override]
    public function claimUse(string $promotion, ?string $code): bool
    {
        $claimed = $this->promotions[$promotion] ?? null;

        if (!$claimed instanceof Promotion || !$claimed->claim()) {
            return false;
        }

        if (null !== $code && (!isset($this->codes[$code]) || !$this->codes[$code]->claim())) {
            $claimed->release();

            return false;
        }

        if (\in_array($promotion, $this->deletedOnClaim, true)) {
            unset($this->promotions[$promotion]);
        }

        return true;
    }

    #[Override]
    public function customerUses(string $promotion, string $customer): int
    {
        return ($this->customerUses[$promotion.' '.$customer] ?? null)?->uses() ?? 0;
    }

    #[Override]
    public function claimCustomerUse(string $promotion, string $customer, int $limit): bool
    {
        return ($this->customerUses[$promotion.' '.$customer] ??= PromotionCustomerUse::of($promotion, $customer))->claim($limit);
    }

    #[Override]
    public function findRedemption(string $orderNumber, string $promotion): ?PromotionRedemption
    {
        return $this->redemptions[$orderNumber.' '.$promotion] ?? null;
    }

    #[Override]
    public function addRedemption(PromotionRedemption $redemption): void
    {
        $this->redemptions[$redemption->orderNumber.' '.$redemption->promotion] = $redemption;
    }
}
