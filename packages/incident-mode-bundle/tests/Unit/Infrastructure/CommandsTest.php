<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\Presets;
use Phil\IncidentModeBundle\Infrastructure\Console\ExtendIncidentCommand;
use Phil\IncidentModeBundle\Infrastructure\Console\LiftIncidentCommand;
use Phil\IncidentModeBundle\Infrastructure\Console\ListIncidentsCommand;
use Phil\IncidentModeBundle\Infrastructure\Console\Moments;
use Phil\IncidentModeBundle\Infrastructure\Console\StartIncidentCommand;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(StartIncidentCommand::class)]
#[CoversClass(ExtendIncidentCommand::class)]
#[CoversClass(LiftIncidentCommand::class)]
#[CoversClass(ListIncidentsCommand::class)]
#[CoversClass(Moments::class)]
final class CommandsTest extends TestCase
{
    private Stage $stage;

    private Application $console;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->console = new Application();
        $this->console->addCommand(new StartIncidentCommand($this->stage->incidents));
        $this->console->addCommand(new ExtendIncidentCommand($this->stage->incidents));
        $this->console->addCommand(new LiftIncidentCommand($this->stage->incidents));
        $this->console->addCommand(new ListIncidentsCommand($this->stage->incidents));
    }

    public function testAnIncidentIsStartedForADurationByAnActor(): void
    {
        $tester = $this->execute('phil:incident:start', ['preset' => Presets::SUSPEND_SIGNUPS, '--duration' => '90m', '--actor' => 'ops-1', '--reason' => 'Spam wave']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        [$activation] = $this->stage->incidents->inForceNow();
        self::assertSame('ops-1', $activation->startedBy?->id);
        self::assertSame('Spam wave', $activation->reason);
        self::assertSame('2026-11-17 11:30', Moments::utc($activation->endsAt));
        self::assertStringContainsString('The incident '.$activation->id.' suspends signups until 2026-11-17 11:30 (UTC).', $this->flat($tester));
    }

    public function testTheDurationAndTheActorAreRequired(): void
    {
        self::assertSame(Command::INVALID, $this->execute('phil:incident:start', ['preset' => Presets::SUSPEND_SIGNUPS, '--actor' => 'ops-1'])->getStatusCode());
        $tester = $this->execute('phil:incident:start', ['preset' => Presets::SUSPEND_SIGNUPS, '--duration' => '1h']);
        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Give how long the incident lasts with --duration, and who starts it with --actor.', $this->flat($tester));
        self::assertSame([], $this->stage->incidents->journal());
    }

    public function testWhatTheLibraryRefusesIsAFailure(): void
    {
        $tester = $this->execute('phil:incident:start', ['preset' => Presets::SUSPEND_SIGNUPS, '--duration' => '8d', '--actor' => 'ops-1']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('lasts at most', $this->flat($tester));

        self::assertSame(Command::FAILURE, $this->execute('phil:incident:start', ['preset' => Presets::SUSPEND_SIGNUPS, '--duration' => '1h', '--actor' => 'ops@example.org'])->getStatusCode());
        self::assertSame(Command::FAILURE, $this->execute('phil:incident:start', ['preset' => 'nothing', '--duration' => '1h', '--actor' => 'ops-1'])->getStatusCode());
        self::assertSame(Command::FAILURE, $this->execute('phil:incident:start', ['preset' => Presets::SUSPEND_SIGNUPS, '--duration' => 'soon', '--actor' => 'ops-1'])->getStatusCode());
        self::assertSame([], $this->stage->incidents->journal());
    }

    public function testAnIncidentIsExtended(): void
    {
        $activation = $this->stage->start(Presets::SUSPEND_SIGNUPS, '2h');

        $tester = $this->execute('phil:incident:extend', ['id' => $activation->id, '--by' => '1d', '--actor' => 'ops-1']);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('now ends at 2026-11-18 12:00 (UTC).', $this->flat($tester));

        self::assertSame(Command::INVALID, $this->execute('phil:incident:extend', ['id' => $activation->id, '--actor' => 'ops-1'])->getStatusCode());
        $tester = $this->execute('phil:incident:extend', ['id' => $activation->id, '--by' => '1h']);
        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Give how much longer the incident lasts with --by, and who extends it with --actor.', $this->flat($tester));
        $tester = $this->execute('phil:incident:extend', ['id' => $activation->id, '--by' => '7d', '--actor' => 'ops-1']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('cannot last longer than its preset allows', $this->flat($tester));
        self::assertSame('2026-11-18 12:00', Moments::utc($this->stage->incidents->find($activation->id)->endsAt ?? throw new LogicException()));
    }

    public function testAnIncidentIsLiftedOrTheExpiredOnesRecorded(): void
    {
        $first = $this->stage->start(Presets::SUSPEND_SIGNUPS, '1h');
        $second = $this->stage->start(Presets::SUSPEND_INVITATIONS, '2h');
        $third = $this->stage->start(Presets::READ_ONLY, '30m');

        $tester = $this->execute('phil:incident:lift', ['id' => $second->id, '--actor' => 'ops-1']);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The incident '.$second->id.' is lifted.', $this->flat($tester));
        self::assertSame(LiftReason::Manual, $this->stage->incidents->find($second->id)?->liftReason);
        $tester = $this->execute('phil:incident:lift', ['id' => $second->id, '--actor' => 'ops-1']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString($second->id, $this->flat($tester));

        $tester = $this->execute('phil:incident:lift', ['id' => $first->id]);
        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Give the incident to lift and who lifts it with --actor, or --expired alone.', $this->flat($tester));
        self::assertSame(Command::INVALID, $this->execute('phil:incident:lift', ['--actor' => 'ops-1'])->getStatusCode());

        $this->stage->clock->modify('+90 minutes');
        $tester = $this->execute('phil:incident:lift', ['--expired' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('2 expired incidents recorded as lifted.', $this->flat($tester));
        self::assertSame(LiftReason::Expired, $this->stage->incidents->find($first->id)?->liftReason);
        self::assertSame(LiftReason::Expired, $this->stage->incidents->find($third->id)?->liftReason);
    }

    public function testTheIncidentsInForceTheJournalAndThePresetsAreListed(): void
    {
        $lifted = $this->stage->start(Presets::SUSPEND_SIGNUPS, '1h');
        $this->stage->incidents->lift($lifted->id, Actor::of('ops-9'));
        $this->stage->clock->modify('+1 minute');

        $inForce = $this->stage->start(Presets::SUSPEND_INVITATIONS, '2h');

        $tester = $this->execute('phil:incident:list', []);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString($inForce->id, $display);
        self::assertStringNotContainsString($lifted->id, $display);
        self::assertStringContainsString('2026-11-17 10:01', $display);
        self::assertStringContainsString('2026-11-17 12:01', $display);
        self::assertStringContainsString('admin-1', $display);
        self::assertStringContainsString('Incident Preset Suspends Started (UTC) Ends (UTC) Started by Lifted (UTC) Lifted by How', $this->flat($tester));

        $display = $this->execute('phil:incident:list', ['--journal' => true])->getDisplay();
        self::assertStringContainsString($inForce->id, $display);
        self::assertStringContainsString('ops-9', $display);
        self::assertLessThan(strpos($display, $lifted->id), strpos($display, $inForce->id));
        self::assertStringContainsString('manual', $display);

        $display = $this->execute('phil:incident:list', ['--journal' => true, '--limit' => '1', '--offset' => '1'])->getDisplay();
        self::assertStringContainsString($lifted->id, $display);
        self::assertStringNotContainsString($inForce->id, $display);

        $display = $this->execute('phil:incident:list', ['--presets' => true])->getDisplay();
        self::assertStringContainsString('Preset Suspends Proposed Longest', preg_replace('/\s+/', ' ', $display) ?? '');
        self::assertStringNotContainsString($inForce->id, $display);
        self::assertMatchesRegularExpression('/suspend-contributions +signups, invitations, new-messages, new-posts, new-comments, uploads +1h +3d/', $display);
        self::assertMatchesRegularExpression('/read-only +read-only +30m +2d/', $display);
    }

    public function testTheBoundsOfTheJournalAreChecked(): void
    {
        foreach ([['--limit' => '0'], ['--limit' => '501'], ['--offset' => '-1']] as $options) {
            $tester = $this->execute('phil:incident:list', ['--journal' => true, ...$options]);
            self::assertSame(Command::INVALID, $tester->getStatusCode());
            self::assertStringContainsString('The limit is from 1 to 500, and the offset is not negative.', $this->flat($tester));
        }

        self::assertSame(Command::SUCCESS, $this->execute('phil:incident:list', ['--journal' => true, '--limit' => '500', '--offset' => '0'])->getStatusCode());
        self::assertSame(Command::SUCCESS, $this->execute('phil:incident:list', ['--journal' => true, '--limit' => '1'])->getStatusCode());
    }

    public function testTheMomentsAreWrittenInUtc(): void
    {
        self::assertSame('2026-11-17 15:00', Moments::utc(new DateTimeImmutable('2026-11-17 10:00', new DateTimeZone('America/Toronto'))));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(string $command, array $input): CommandTester
    {
        $tester = new CommandTester($this->console->find($command));
        $tester->execute($input);

        return $tester;
    }

    private function flat(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', str_replace(['[OK]', '[ERROR]', '!'], '', $tester->getDisplay()));
    }
}
