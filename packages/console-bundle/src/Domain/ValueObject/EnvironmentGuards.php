<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain\ValueObject;

use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;

/**
 * The guards of one command: the environments it is forbidden in, and those it asks a confirmation in.
 */
final readonly class EnvironmentGuards
{
    /**
     * @param list<string> $forbidden    The environments the command refuses to run in
     * @param list<string> $confirmed    The environments that ask for a confirmation
     * @param bool         $confirmEvery Whether every environment asks for a confirmation
     */
    private function __construct(
        public array $forbidden,
        public array $confirmed,
        public bool $confirmEvery,
    ) {
    }

    /**
     * @param iterable<ForbidEnvironment|ConfirmEnvironment> $attributes
     */
    public static function of(iterable $attributes): self
    {
        $forbidden = [];
        $confirmed = [];
        $confirmEvery = false;

        foreach ($attributes as $attribute) {
            if ($attribute instanceof ForbidEnvironment) {
                $forbidden = [...$forbidden, ...$attribute->environments];

                continue;
            }

            $confirmEvery = $confirmEvery || [] === $attribute->environments;
            $confirmed = [...$confirmed, ...$attribute->environments];
        }

        return new self(array_values(array_unique($forbidden)), array_values(array_unique($confirmed)), $confirmEvery);
    }

    public function forbids(string $environment): bool
    {
        return \in_array($environment, $this->forbidden, true);
    }

    public function asksConfirmationIn(string $environment): bool
    {
        return $this->confirmEvery || \in_array($environment, $this->confirmed, true);
    }

    public function isEmpty(): bool
    {
        return [] === $this->forbidden && [] === $this->confirmed && !$this->confirmEvery;
    }
}
