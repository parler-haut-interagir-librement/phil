<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;

/**
 * The register's sale presets: a cart sold under one goes through its channel — the register's when it names none —
 * to its customer group, every line priced again by the core.
 */
final readonly class SalePresets
{
    public function __construct(
        private RegisterRepository $registers,
        private Registers $tills,
        private OrderModifier $modifier,
    ) {
    }

    public function create(string $code, string $label, ?string $channel, ?string $customerGroup = null, bool $identifiesCustomer = false): SalePreset
    {
        $preset = SalePreset::of($code, $label, $channel, $customerGroup, $identifiesCustomer);
        $this->registers->addPreset($preset);

        return $preset;
    }

    /**
     * An active preset.
     */
    public function get(string $code): SalePreset
    {
        $preset = $this->registers->findPreset($code);

        if (!$preset instanceof SalePreset || !$preset->isActive()) {
            throw PosRefused::unknownPreset($code);
        }

        return $preset;
    }

    /**
     * @return list<SalePreset> the presets a register offers, by code
     */
    public function active(): array
    {
        return $this->registers->activePresets();
    }

    /**
     * Sells a cart at a register under a preset, priced again for its channel and customer group.
     */
    public function apply(Order $order, string $preset, string $register, DateTimeImmutable $at): SalePreset
    {
        $chosen = $this->get($preset);
        $channel = $chosen->channel() ?? $this->tills->get($register)->channel();
        $this->modifier->changeAudience($order, $channel, $chosen->customerGroup(), PricingContext::of(1, $at, $chosen->customerGroup(), $channel));

        return $chosen;
    }
}
