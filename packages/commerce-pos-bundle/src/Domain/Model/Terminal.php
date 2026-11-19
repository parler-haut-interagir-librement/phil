<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A connected payment terminal, driven by the server: the register it sits at, the adapter that speaks to its
 * processor, the processor's identifier of the device, and whether it takes payments that ask for a PIN — which only
 * the main register does.
 *
 * The till never talks to the terminal: it asks the server, the server asks the processor through the adapter, and the
 * processor's signed notification settles the payment.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_terminal')]
#[ORM\Index(columns: ['register'])]
final class Terminal
{
    #[ORM\Column(name: 'active')]
    private bool $active = true;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 32)]
        public readonly string $code,
        #[ORM\Column(name: 'register', length: 32)]
        public readonly string $register,
        #[ORM\Column(name: 'adapter', length: 32)]
        public readonly string $adapter,
        #[ORM\Column(name: 'device', length: 255)]
        public readonly string $device,
        #[ORM\Column(name: 'label', length: 64)]
        private string $label,
        #[ORM\Column(name: 'pin_entry')]
        public readonly bool $pinEntry,
    ) {
    }

    /**
     * @param string $adapter  the name of the `PaymentTerminal` adapter that speaks to its processor
     * @param string $device   the processor's identifier of the device
     * @param bool   $pinEntry whether it takes payments that ask for a PIN: only at the main register
     */
    public static function of(string $code, Register $register, string $adapter, string $device, string $label, bool $pinEntry): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a terminal code: up to 32 lowercase letters, digits, "_" or "-".', $code));
        }

        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $adapter)) {
            throw InvalidValue::because(\sprintf('"%s" is not an adapter name: up to 32 lowercase letters, digits or "_".', $adapter));
        }

        if (1 !== preg_match('/^[\x21-\x7E]{1,255}$/D', $device)) {
            throw InvalidValue::because("A terminal's device is named in 1 to 255 visible ASCII characters.");
        }

        return new self($code, $register->code, $adapter, $device, self::checkedLabel($label), $pinEntry);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function relabel(string $label): void
    {
        $this->label = self::checkedLabel($label);
    }

    public function isActive(): bool
    {
        return $this->active;
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
            throw InvalidValue::because('A terminal is labelled in 1 to 64 characters.');
        }

        return $label;
    }
}
