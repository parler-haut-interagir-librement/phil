<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A place stock is kept: a warehouse, a store's back room. Locations are tried for reservations by priority, highest
 * first.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_location')]
final class Location
{
    #[ORM\Column(name: 'active')]
    private bool $active = true;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'name', length: 255)]
        public readonly string $name,
        #[ORM\Column(name: 'priority', type: 'integer')]
        public readonly int $priority,
    ) {
    }

    public static function of(string $code, string $name, int $priority = 0): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a location code: up to 64 lowercase letters, digits, "_", "." or "-".', $code));
        }

        if ('' === trim($name)) {
            throw InvalidValue::because(\sprintf('The location %s has a name.', $code));
        }

        return new self($code, $name, $priority);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * No longer reserved from; its stock stays counted.
     */
    public function close(): void
    {
        $this->active = false;
    }

    public function reopen(): void
    {
        $this->active = true;
    }
}
