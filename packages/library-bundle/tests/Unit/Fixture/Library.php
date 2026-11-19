<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Fixture;

use Phil\LibraryBundle\Application\Catalogue;
use Phil\LibraryBundle\Application\LoanPolicy;
use Phil\LibraryBundle\Application\Loans;
use Phil\LibraryBundle\Application\Reminders;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Symfony\Component\Clock\MockClock;

/**
 * A library on the stores in memory, in Montréal, its clock stopped at 2026-11-13 10:00 there: loans of 14 days,
 * renewed once, three at a time per member.
 */
final readonly class Library
{
    public InMemoryTitles $titles;

    public InMemoryCopies $copies;

    public InMemoryLoans $loans;

    public Recorders $recorders;

    public MockClock $clock;

    public LoanPolicy $policy;

    public Catalogue $catalogue;

    public Loans $service;

    public function __construct(?LoanPolicy $policy = null)
    {
        $this->titles = new InMemoryTitles();
        $this->copies = new InMemoryCopies();
        $this->loans = new InMemoryLoans();
        $this->recorders = new Recorders();
        $this->clock = new MockClock('2026-11-13 10:00:00', 'America/Toronto');
        $this->policy = $policy ?? new LoanPolicy(14, 1, 3, true, 30, 365, 'America/Toronto');
        $this->catalogue = new Catalogue($this->titles, $this->copies, $this->clock);
        $this->service = new Loans($this->copies, $this->loans, $this->recorders, $this->policy, $this->clock, $this->recorders);
    }

    /**
     * @param list<int> $beforeDue
     */
    public function reminders(array $beforeDue = [2880, 1440], int $everyDays = 7, int $maxNotices = 2): Reminders
    {
        return new Reminders($this->loans, $this->recorders, $this->clock, $beforeDue, $everyDays, $maxNotices);
    }

    /**
     * A title with copies of the barcodes given, each linked to the stockroom item `item-<barcode>` when asked.
     *
     * @param list<string> $barcodes
     */
    public function shelve(string $name, array $barcodes, bool $linked = false): string
    {
        $title = $this->catalogue->add(new Record(TitleKind::Game, $name, minPlayers: 2, maxPlayers: 4));

        foreach ($barcodes as $barcode) {
            $this->catalogue->addCopy($title->id, $barcode, stockroomItem: $linked ? 'item-'.$barcode : null);
        }

        return $title->id;
    }
}
