<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A code that unlocks a promotion asking for one: printed on a flyer, sent in a batch, given to one buyer. Its uses
 * are counted, up to its own limit.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_promotion_code')]
#[ORM\Index(columns: ['promotion'])]
final class PromotionCode
{
    #[ORM\Column(name: 'uses', type: 'integer')]
    private int $uses = 0;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'promotion', length: 64)]
        public readonly string $promotion,
        #[ORM\Column(name: 'usage_limit', type: 'integer', nullable: true)]
        public readonly ?int $usageLimit,
    ) {
    }

    /**
     * @param int|null $usageLimit `1` for a code given to one buyer; `null` for a code anyone may use
     */
    public static function of(string $code, Promotion $promotion, ?int $usageLimit = null): self
    {
        if (1 !== preg_match('/^[A-Z0-9][A-Z0-9_-]{2,63}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a promotion code: 3 to 64 uppercase letters, digits, "_" or "-".', $code));
        }

        if (null !== $usageLimit && $usageLimit < 1) {
            throw InvalidValue::because(\sprintf('The code %s allows one use or more.', $code));
        }

        return new self($code, $promotion->code, $usageLimit);
    }

    public function uses(): int
    {
        return $this->uses;
    }

    public function isUsable(): bool
    {
        return null === $this->usageLimit || $this->uses < $this->usageLimit;
    }

    /**
     * Counts one use, once the order it unlocked a promotion for is placed: `false`, and nothing counted, when its
     * limit is reached. The Doctrine adapter of `PromotionRepository` claims the same way in one atomic `UPDATE`.
     */
    public function claim(): bool
    {
        if (!$this->isUsable()) {
            return false;
        }

        ++$this->uses;

        return true;
    }
}
