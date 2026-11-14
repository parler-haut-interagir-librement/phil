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

final class SubjectRecordingEraser implements PersonalDataEraser
{
    /**
     * @var list<string>
     */
    public array $erased = [];

    public bool $fails = false;

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString('newsletter');
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        if ($this->fails) {
            throw new RuntimeException('The newsletter is down.');
        }

        $this->erased[] = $subject->value;

        return ErasureReport::of('newsletter', ['subscribers' => ErasureEffect::Deleted]);
    }
}
