<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

final readonly class FixedExporter implements PersonalDataExporter
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private string $module,
        private array $data,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString($this->module);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        return ['subject' => $subject->value] + $this->data;
    }
}
