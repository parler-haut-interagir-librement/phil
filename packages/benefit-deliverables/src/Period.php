<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables;

use Phil\BenefitDeliverables\Exception\InvalidDeliverable;

/**
 * A period benefits are owed for, named by the application: `2026-11` for a month, `2026-Q4` for a quarter,
 * `2026` for a year, `2026-W47` for a week. The package compares periods by name only.
 */
final readonly class Period
{
    private const string NAME = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,31}$/D';

    private function __construct(
        public string $name,
    ) {
    }

    /**
     * @param string $name 1 to 32 letters, digits, `.`, `_`, `:` or `-`, starting with a letter or a digit
     */
    public static function of(string $name): self
    {
        if (1 !== preg_match(self::NAME, $name)) {
            throw InvalidDeliverable::because('A period is named by 1 to 32 letters, digits, ".", "_", ":" or "-", starting with a letter or a digit.');
        }

        return new self($name);
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }
}
