<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use RuntimeException;

final class RecordingEraser implements PersonalDataEraser
{
    public int $calls = 0;

    public function __construct(
        private readonly string $module,
        private readonly bool $fails = false,
        private readonly ?string $reportsAs = null,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString($this->module);
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        ++$this->calls;

        if ($this->fails) {
            throw new RuntimeException(\sprintf('%s is down', $this->module));
        }

        return ErasureReport::of($this->reportsAs ?? $this->module, ['records' => ErasureEffect::Deleted]);
    }
}
