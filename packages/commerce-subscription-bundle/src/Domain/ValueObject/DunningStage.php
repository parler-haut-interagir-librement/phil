<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\ValueObject;

use Phil\CommerceSubscriptionBundle\Domain\Dunning\DunningAccess;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * One stage of an offer's dunning: from so many days past due on, the subscriber keeps every feature, some of them,
 * or none.
 */
final readonly class DunningStage
{
    /**
     * @param list<string> $features the features kept, for a reduced access
     */
    private function __construct(
        public int $afterDays,
        public DunningAccess $access,
        public array $features,
    ) {
    }

    public static function full(int $afterDays): self
    {
        return new self(self::checkedDays($afterDays), DunningAccess::Full, []);
    }

    /**
     * @param list<string> $features the features kept, at least one
     */
    public static function reduced(int $afterDays, array $features): self
    {
        if ([] === $features) {
            throw InvalidValue::because('A reduced access keeps one feature or more; a stage that keeps none cuts access.');
        }

        return new self(self::checkedDays($afterDays), DunningAccess::Reduced, array_values(array_unique($features)));
    }

    public static function cut(int $afterDays): self
    {
        return new self(self::checkedDays($afterDays), DunningAccess::None, []);
    }

    /**
     * @param array{after_days: int, access: string, features?: list<string>} $stored
     */
    public static function fromArray(array $stored): self
    {
        return match (DunningAccess::from($stored['access'])) {
            DunningAccess::Full => self::full($stored['after_days']),
            DunningAccess::Reduced => self::reduced($stored['after_days'], $stored['features'] ?? []),
            DunningAccess::None => self::cut($stored['after_days']),
        };
    }

    /**
     * @return array{after_days: int, access: string, features: list<string>}
     */
    public function toArray(): array
    {
        return ['after_days' => $this->afterDays, 'access' => $this->access->value, 'features' => $this->features];
    }

    private static function checkedDays(int $days): int
    {
        if ($days < 0 || $days > 365) {
            throw InvalidValue::because('A dunning stage starts 0 to 365 days past due.');
        }

        return $days;
    }
}
