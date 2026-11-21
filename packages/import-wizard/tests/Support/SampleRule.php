<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Support;

use DateTimeImmutable;
use Phil\ImportWizard\Contract\RowRule;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Profile\ProfileData;

/**
 * A rule of an application, read back by its name.
 */
final readonly class SampleRule implements RowRule
{
    public function __construct(public string $option)
    {
    }

    public static function name(): string
    {
        return 'sample';
    }

    public static function fromArray(array $data): static
    {
        return new self(ProfileData::string($data, 'option', 'rule'));
    }

    /**
     * @param array<string, string|int|bool|DateTimeImmutable|null> $values
     *
     * @return list<CellError>
     */
    public function check(array $values): array
    {
        return [];
    }

    public function toArray(): array
    {
        return ['option' => $this->option];
    }
}
