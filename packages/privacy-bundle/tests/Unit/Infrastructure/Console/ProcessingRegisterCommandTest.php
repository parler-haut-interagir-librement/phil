<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console;

use Phil\PrivacyBundle\Application\Register\ProcessingRegister;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\Processing;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Infrastructure\Console\ProcessingRegisterCommand;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedScheduleSource;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\BaseModel;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\Member;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ProcessingRegisterCommand::class)]
final class ProcessingRegisterCommandTest extends TestCase
{
    public function testTheJsonHoldsEachActivityAsItsLineWithAccentsAndSlashesKept(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(Command::SUCCESS, $tester->execute(['--format' => 'json']));
        $decoded = json_decode($tester->getDisplay(), true);
        self::assertIsArray($decoded);
        self::assertIsArray($decoded['activities']);
        self::assertIsArray($decoded['activities'][0]);
        self::assertSame('R-002', $decoded['activities'][0]['rule']);
        self::assertSame([Member::class.'::$email'], $decoded['activities'][0]['fields']);
        self::assertStringContainsString('"purpose": "Café / renewals",', $tester->getDisplay());
    }

    public function testTheGapsAreSetApartByABlankLine(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString(" |\n\n## Gaps\n\n- ", $tester->getDisplay());
    }

    private function command(): ProcessingRegisterCommand
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-002', 'members', [DataCategory::fromString('email')], RetentionPeriod::fixed('P24M'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned, new Processing('Café / renewals', location: 'Canada')),
        ]);

        return new ProcessingRegisterCommand(new ProcessingRegister(new FixedScheduleSource($schedule)), [Member::class, BaseModel::class]);
    }
}
