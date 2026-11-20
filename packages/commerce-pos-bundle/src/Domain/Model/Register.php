<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommercePosBundle\Domain\Register\RegisterRole;
use Phil\CommercePosBundle\Domain\ValueObject\CashRounding;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;

/**
 * A till: its code and name, the currency its drawer holds and the increment cash rounds to, its role — the main
 * register at the counter, or a seller's mobile one —, and, if the application uses them, the stock location it sells
 * from and the channel its sales belong to.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_register')]
final class Register
{
    #[ORM\Column(name: 'active')]
    private bool $active = true;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 32)]
        public readonly string $code,
        #[ORM\Column(name: 'name', length: 255)]
        private string $name,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'cash_increment', type: 'smallint')]
        private int $cashIncrement,
        #[ORM\Column(name: 'location', length: 64, nullable: true)]
        private ?string $location,
        #[ORM\Column(name: 'channel', length: 64, nullable: true)]
        private ?string $channel,
        #[ORM\Column(name: 'role', length: 8, enumType: RegisterRole::class, options: ['default' => 'mobile'])]
        private RegisterRole $role,
    ) {
    }

    public static function of(string $code, string $name, Currency $currency, int $cashIncrement = 1, ?string $location = null, ?string $channel = null, RegisterRole $role = RegisterRole::Mobile): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a register code: up to 32 lowercase letters, digits, "_" or "-".', $code));
        }

        return new self($code, self::checkedName($code, $name), $currency->code, CashRounding::of($cashIncrement)->increment, self::checkedReference($location), self::checkedReference($channel), $role);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function currency(): Currency
    {
        return Currency::of($this->currencyCode);
    }

    public function rounding(): CashRounding
    {
        return CashRounding::of($this->cashIncrement);
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function channel(): ?string
    {
        return $this->channel;
    }

    public function role(): RegisterRole
    {
        return $this->role;
    }

    /**
     * Whether it is the main register of its location: the one that takes PIN payments, picks up a seller's sale and
     * sells offline.
     */
    public function isMain(): bool
    {
        return RegisterRole::Main === $this->role;
    }

    /**
     * Changes the register's role; `Registers` keeps one active main register per location.
     */
    public function assign(RegisterRole $role): void
    {
        $this->role = $role;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function rename(string $name): void
    {
        $this->name = self::checkedName($this->code, $name);
    }

    public function roundCashTo(int $increment): void
    {
        $this->cashIncrement = CashRounding::of($increment)->increment;
    }

    public function place(?string $location, ?string $channel): void
    {
        $this->location = self::checkedReference($location);
        $this->channel = self::checkedReference($channel);
    }

    public function retire(): void
    {
        $this->active = false;
    }

    public function reinstate(): void
    {
        $this->active = true;
    }

    private static function checkedName(string $code, string $name): string
    {
        if ('' === trim($name) || mb_strlen($name) > 255) {
            throw InvalidValue::because(\sprintf('The register %s has a name of 1 to 255 characters.', $code));
        }

        return $name;
    }

    private static function checkedReference(?string $reference): ?string
    {
        if (null !== $reference && 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $reference)) {
            throw InvalidValue::because(\sprintf('"%s" is not a location or a channel: 1 to 64 visible ASCII characters.', $reference));
        }

        return $reference;
    }
}
