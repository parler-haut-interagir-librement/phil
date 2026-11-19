<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\LibraryBundle\Infrastructure\Console\ForgetHistoryCommand;
use Phil\LibraryBundle\Infrastructure\Console\SendRemindersCommand;
use Phil\LibraryBundle\Infrastructure\Privacy\LoanHistoryPrivacy;
use Phil\LibraryBundle\Tests\Unit\Fixture\Library;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(LoanHistoryPrivacy::class)]
#[CoversClass(SendRemindersCommand::class)]
#[CoversClass(ForgetHistoryCommand::class)]
final class PrivacyAndCommandsTest extends TestCase
{
    private Library $library;

    #[Override]
    protected function setUp(): void
    {
        $this->library = new Library();
        $this->library->shelve('Azul', ['G-1', 'G-2']);
    }

    public function testTheExportListsAMembersLoans(): void
    {
        $first = $this->library->service->lend('G-1', 'card-7');
        $this->library->clock->modify('+2 days');
        $this->library->service->giveBack('G-1');

        $second = $this->library->service->lend('G-2', 'card-7');
        $this->library->service->renew($second->id);
        $this->library->service->lend('G-1', 'card-8');

        $privacy = new LoanHistoryPrivacy($this->library->loans);

        self::assertSame('library', $privacy->module()->value);
        self::assertSame(['loans' => [
            ['loan' => $second->id, 'title' => 'Azul', 'copy' => 'G-2', 'status' => 'active', 'starts_at' => '2026-11-15T15:00:00+00:00', 'due_at' => '2026-12-14T04:59:59+00:00', 'checked_out_at' => '2026-11-15T15:00:00+00:00', 'closed_at' => null, 'renewals' => 1],
            ['loan' => $first->id, 'title' => 'Azul', 'copy' => 'G-1', 'status' => 'returned', 'starts_at' => '2026-11-13T15:00:00+00:00', 'due_at' => '2026-11-28T04:59:59+00:00', 'checked_out_at' => '2026-11-13T15:00:00+00:00', 'closed_at' => '2026-11-15T15:00:00+00:00', 'renewals' => 0],
        ]], $privacy->export(DataSubject::fromString('card-7')));
        self::assertSame([], $privacy->export(DataSubject::fromString('card-9')));
        self::assertSame([], $privacy->export(DataSubject::fromString('jo@example.com')));
        self::assertNotContains('jo@example.com', $this->library->loans->read, 'what is not a member is not looked up');
    }

    public function testAReservationIsExportedNotYetCheckedOut(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $this->library->service->reserve($copy->id, 'card-7', new DateTimeImmutable('2026-11-20 10:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-25 10:00', new DateTimeZone('America/Toronto')));

        $reserved = $this->library->loans->saved[0];

        self::assertSame(['loans' => [
            ['loan' => $reserved, 'title' => 'Azul', 'copy' => 'G-1', 'status' => 'reserved', 'starts_at' => '2026-11-20T15:00:00+00:00', 'due_at' => '2026-11-25T15:00:00+00:00', 'checked_out_at' => null, 'closed_at' => null, 'renewals' => 0],
        ]], new LoanHistoryPrivacy($this->library->loans)->export(DataSubject::fromString('card-7')));
    }

    public function testTheErasureForgetsTheMemberAndRunsAgain(): void
    {
        $loan = $this->library->service->lend('G-1', 'card-7');
        $other = $this->library->service->lend('G-2', 'card-8');
        $privacy = new LoanHistoryPrivacy($this->library->loans);

        $report = $privacy->erase(DataSubject::fromString('card-7'));
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('card-7')));
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('jo@example.com')));
        self::assertSame([null, 'card-8'], [$loan->member(), $other->member()]);
        self::assertSame(['phil_library_loan.member' => ErasureEffect::Redacted], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('card-7')));
    }

    public function testTheCommandsSayWhatTheyDid(): void
    {
        $this->library->service->lend('G-1', 'card-7');
        $this->library->service->giveBack('G-1');
        $this->library->service->lend('G-2', 'card-8');
        $this->library->clock->modify('2026-11-27 12:00');

        $send = new CommandTester(new SendRemindersCommand($this->library->reminders(), 10));
        self::assertSame(0, $send->execute([]));
        self::assertSame("1 reminders sent.\n", $send->getDisplay());

        $this->library->clock->modify('+400 days');
        $forget = new CommandTester(new ForgetHistoryCommand($this->library->service));
        self::assertSame(0, $forget->execute([]));
        self::assertSame("1 loans forgot their member.\n", $forget->getDisplay());
    }
}
