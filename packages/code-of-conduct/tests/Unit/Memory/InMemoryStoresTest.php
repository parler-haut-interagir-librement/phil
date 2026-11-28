<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Tests\Unit\Memory;

use DateTimeImmutable;
use Phil\CodeOfConduct\Acceptance;
use Phil\CodeOfConduct\ChangeKind;
use Phil\CodeOfConduct\CodeVersion;
use Phil\CodeOfConduct\Exception\ActionRefused;
use Phil\CodeOfConduct\Memory\InMemoryAcceptanceStore;
use Phil\CodeOfConduct\Memory\InMemoryCodeVersionStore;
use Phil\CodeOfConduct\Memory\InMemoryReportStore;
use Phil\CodeOfConduct\Report;
use Phil\CodeOfConduct\ReportNote;
use Phil\CodeOfConduct\ReportStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryCodeVersionStore::class)]
#[CoversClass(InMemoryAcceptanceStore::class)]
#[CoversClass(InMemoryReportStore::class)]
final class InMemoryStoresTest extends TestCase
{
    public function testVersionsAreReadByScopeAndNumber(): void
    {
        $store = new InMemoryCodeVersionStore();
        $at = new DateTimeImmutable();
        $one = new CodeVersion('club', 1, 'Code', 'One', ChangeKind::Major, $at);
        $two = new CodeVersion('club', 2, 'Code', 'Two', ChangeKind::Major, $at);
        $three = new CodeVersion('club', 3, 'Code', 'Three', ChangeKind::Minor, $at);

        self::assertNull($store->current('club'));
        self::assertNull($store->latestMajor('club'));

        $store->add($three);
        $store->add($one);

        self::assertSame($three, $store->current('club'));
        self::assertSame(1, $store->latestMajor('club'));

        $store->add($two);

        self::assertSame(2, $store->latestMajor('club'));
        self::assertSame($two, $store->version('club', 2));
        self::assertNull($store->version('fest', 2));

        $this->expectException(ActionRefused::class);
        $this->expectExceptionMessageIsOrContains('This version of the code was already published.');

        $store->add(new CodeVersion('club', 2, 'Code', 'Again', ChangeKind::Major, $at));
    }

    public function testTheLatestAcceptanceIsTheOneOfTheHighestVersion(): void
    {
        $store = new InMemoryAcceptanceStore();
        $at = new DateTimeImmutable();
        $two = new Acceptance('club', 'sam', 2, 'h2', $at);
        $twoAgain = new Acceptance('club', 'sam', 2, 'h2', $at);
        $store->add($two);
        $store->add(new Acceptance('club', 'sam', 1, 'h1', $at));

        self::assertSame($two, $store->latest('club', 'sam'));

        $store->add($twoAgain);

        self::assertSame($twoAgain, $store->latest('club', 'sam'));
        self::assertNull($store->latest('club', 'alex'));
        self::assertNull($store->latest('fest', 'sam'));
    }

    public function testReportsAreReadByScopeStatusAndPage(): void
    {
        $store = new InMemoryReportStore();
        $at = new DateTimeImmutable();
        $a = new Report('a', 'club', null, [], 's', null, $at);
        $b = new Report('b', 'fest', null, [], 's', null, $at);
        $c = new Report('c', 'club', null, [], 's', null, $at, ReportStatus::InReview);
        $d = new Report('d', 'club', null, [], 's', null, $at);

        foreach ([$a, $b, $c, $d] as $report) {
            $store->save($report);
        }

        self::assertSame($a, $store->get('a'));
        self::assertNull($store->get('z'));
        self::assertSame([$a, $c, $d], $store->inScope('club', null, null, 10));
        self::assertSame([$a, $c], $store->inScope('club', null, null, 2));
        self::assertSame([$c, $d], $store->inScope('club', null, 'a', 10));
        self::assertSame([$d], $store->inScope('club', null, 'c', 10));
        self::assertSame([$a, $d], $store->inScope('club', ReportStatus::Received, null, 10));
        self::assertSame([], $store->inScope('club', null, 'z', 10));
    }

    public function testNotesAreReadOldestFirst(): void
    {
        $store = new InMemoryReportStore();
        $at = new DateTimeImmutable();
        $one = new ReportNote('1', 'a', 'lead', 's', $at);
        $two = new ReportNote('2', 'a', 'lead', 's', $at);
        $store->addNote($one);
        $store->addNote(new ReportNote('3', 'b', 'lead', 's', $at));
        $store->addNote($two);

        self::assertSame([$one, $two], $store->notes('a', 10));
        self::assertSame([$one], $store->notes('a', 1));
        self::assertSame([], $store->notes('c', 10));
    }
}
