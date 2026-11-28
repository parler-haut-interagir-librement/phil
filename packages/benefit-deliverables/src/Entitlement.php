<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables;

use Phil\BenefitDeliverables\Exception\InvalidDeliverable;

/**
 * What one member is owed for a period: the benefits of their tier of subscription, membership or donation. The tier
 * itself stays with the source: the package needs the benefits only.
 */
final readonly class Entitlement
{
    /** @var array<string, Benefit> by code */
    public array $benefits;

    /**
     * @param list<Benefit> $benefits
     */
    public function __construct(
        public string $member,
        array $benefits,
    ) {
        Member::check($member);

        if (\count($benefits) > 100) {
            throw InvalidDeliverable::because('A member is owed 100 benefits a period at most.');
        }

        $byCode = [];

        foreach ($benefits as $benefit) {
            $byCode[$benefit->code] = $benefit;
        }

        $this->benefits = $byCode;
    }

    public function benefit(string $code): ?Benefit
    {
        return $this->benefits[$code] ?? null;
    }
}
