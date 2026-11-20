<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Domain\Contract;

use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionRedemption;

/**
 * Finds and stores promotions, their codes, their redemptions and their uses per customer.
 * `Infrastructure\Doctrine\DoctrinePromotionRepository` is the Doctrine adapter.
 */
interface PromotionRepository
{
    public function find(string $code): ?Promotion;

    /**
     * @return list<Promotion> the promotions not withdrawn, by code
     */
    public function active(): array;

    public function add(Promotion $promotion): void;

    public function findCode(string $code): ?PromotionCode;

    public function addCode(PromotionCode $code): void;

    /**
     * Claims one use of the promotion and, when given, one of the code, together: `false` when either is at its
     * limit, and then neither is counted. Two orders claiming the last use at once never both get it.
     */
    public function claimUse(string $promotion, ?string $code): bool;

    /**
     * The uses the customer already made of the promotion.
     */
    public function customerUses(string $promotion, string $customer): int;

    /**
     * Claims one use of the promotion for the customer, under the limit: `false`, and nothing counted, when the
     * customer reached it.
     */
    public function claimCustomerUse(string $promotion, string $customer, int $limit): bool;

    public function findRedemption(string $orderNumber, string $promotion): ?PromotionRedemption;

    public function addRedemption(PromotionRedemption $redemption): void;
}
