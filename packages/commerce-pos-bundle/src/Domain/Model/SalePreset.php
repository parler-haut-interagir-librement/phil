<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A light sale preset over the core's channels: a button at the register — a convention, a member, a wholesale buyer,
 * a pickup — that sells the cart through a channel and to a customer group, priced again for them, and may ask the
 * cashier to identify the customer before the sale is rung up.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_sale_preset')]
final class SalePreset
{
    #[ORM\Column(name: 'active')]
    private bool $active = true;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 32)]
        public readonly string $code,
        #[ORM\Column(name: 'label', length: 64)]
        private string $label,
        #[ORM\Column(name: 'channel', length: 64, nullable: true)]
        private ?string $channel,
        #[ORM\Column(name: 'customer_group', length: 64, nullable: true)]
        private ?string $customerGroup,
        #[ORM\Column(name: 'identifies_customer')]
        private bool $identifiesCustomer,
    ) {
    }

    /**
     * @param string|null $channel       the channel the cart sells through; the register's when null
     * @param string|null $customerGroup the customer group the cart sells to, if any
     */
    public static function of(string $code, string $label, ?string $channel, ?string $customerGroup = null, bool $identifiesCustomer = false): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a preset code: up to 32 lowercase letters, digits, "_" or "-".', $code));
        }

        return new self($code, self::checkedLabel($label), self::checkedReference($channel), self::checkedReference($customerGroup), $identifiesCustomer);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function channel(): ?string
    {
        return $this->channel;
    }

    public function customerGroup(): ?string
    {
        return $this->customerGroup;
    }

    public function identifiesCustomer(): bool
    {
        return $this->identifiesCustomer;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function change(string $label, ?string $channel, ?string $customerGroup, bool $identifiesCustomer): void
    {
        $this->label = self::checkedLabel($label);
        $this->channel = self::checkedReference($channel);
        $this->customerGroup = self::checkedReference($customerGroup);
        $this->identifiesCustomer = $identifiesCustomer;
    }

    public function retire(): void
    {
        $this->active = false;
    }

    public function reinstate(): void
    {
        $this->active = true;
    }

    private static function checkedLabel(string $label): string
    {
        if ('' === trim($label) || mb_strlen($label) > 64) {
            throw InvalidValue::because('A sale preset is labelled in 1 to 64 characters.');
        }

        return $label;
    }

    private static function checkedReference(?string $reference): ?string
    {
        if (null !== $reference && 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $reference)) {
            throw InvalidValue::because(\sprintf('"%s" is not a channel or a customer group: 1 to 64 visible ASCII characters.', $reference));
        }

        return $reference;
    }
}
