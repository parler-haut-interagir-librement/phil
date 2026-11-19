<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Unit\Infrastructure\Privacy;

use Phil\DoctrineBehaviours\BlameSuspension;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\SuspendBlame;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\SuspendBlameWithExport;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * An eraser runs with the blame suspended, and only its erasure: the module and the export pass through as they are.
 */
#[CoversClass(SuspendBlame::class)]
#[CoversClass(SuspendBlameWithExport::class)]
final class SuspendBlameTest extends TestCase
{
    public function testTheErasureRunsWithTheBlameSuspended(): void
    {
        $suspension = new BlameSuspension();
        $eraser = $this->eraser($suspension);

        foreach ([new SuspendBlame($eraser, $suspension), new SuspendBlameWithExport($eraser, $suspension)] as $decorated) {
            self::assertSame('app.profile', $decorated->module()->value);
            self::assertSame(['app.profile' => ErasureEffect::Deleted], $decorated->erase(DataSubject::fromString('ada'))->effects);
            self::assertFalse($suspension->isSuspended(), 'the blame is written again once it returns');
        }

        self::assertSame([true, true], $eraser->suspended);
        self::assertSame(['exported' => 'ada'], new SuspendBlameWithExport($eraser, $suspension)->export(DataSubject::fromString('ada')));
        self::assertSame([true, true], $eraser->suspended, 'the export runs as it is');
    }

    /**
     * @return PersonalDataEraser&PersonalDataExporter&object{suspended: list<bool>}
     */
    private function eraser(BlameSuspension $suspension): PersonalDataEraser&PersonalDataExporter
    {
        return new class($suspension) implements PersonalDataEraser, PersonalDataExporter {
            /**
             * @var list<bool>
             */
            public array $suspended = [];

            public function __construct(
                private readonly BlameSuspension $suspension,
            ) {
            }

            public function module(): ModuleName
            {
                return ModuleName::fromString('app.profile');
            }

            public function export(DataSubject $subject): array
            {
                return ['exported' => $subject->value];
            }

            public function erase(DataSubject $subject): ErasureReport
            {
                $this->suspended[] = $this->suspension->isSuspended();

                return ErasureReport::of('app.profile', ['app.profile' => ErasureEffect::Deleted]);
            }
        };
    }
}
