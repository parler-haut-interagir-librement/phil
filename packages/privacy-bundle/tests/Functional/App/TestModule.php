<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * One module of the application, which answers for a person and remembers who it erased.
 */
final class TestModule implements PersonalDataExporter, PersonalDataEraser
{
    /**
     * @var list<string>
     */
    public array $erased = [];

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString('members');
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        return ['subject' => $subject->value];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->erased[] = $subject->value;

        return ErasureReport::of('members', ['members' => ErasureEffect::Deleted]);
    }
}
