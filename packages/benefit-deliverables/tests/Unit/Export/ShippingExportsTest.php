<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Unit\Export;

use DateTimeImmutable;
use Phil\Address\Address;
use Phil\BenefitDeliverables\Benefit;
use Phil\BenefitDeliverables\Checklist;
use Phil\BenefitDeliverables\Entitlement;
use Phil\BenefitDeliverables\Exception\ExportRefused;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use Phil\BenefitDeliverables\Export\DownloadLinks;
use Phil\BenefitDeliverables\Export\ExportCounts;
use Phil\BenefitDeliverables\Export\ExportRecord;
use Phil\BenefitDeliverables\Export\ExportResult;
use Phil\BenefitDeliverables\Export\ShippingExports;
use Phil\BenefitDeliverables\Period;
use Phil\BenefitDeliverables\Tests\Support\InMemoryAddresses;
use Phil\BenefitDeliverables\Tests\Support\InMemoryDeliverables;
use Phil\BenefitDeliverables\Tests\Support\InMemoryEntitlements;
use Phil\BenefitDeliverables\Tests\Support\InMemoryFiles;
use Phil\BenefitDeliverables\Tests\Support\InMemoryJournal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShippingExports::class)]
#[CoversClass(ExportCounts::class)]
#[CoversClass(ExportResult::class)]
#[CoversClass(ExportRefused::class)]
final class ShippingExportsTest extends TestCase
{
    private Period $period;

    private InMemoryEntitlements $entitlements;

    private InMemoryDeliverables $deliveries;

    private InMemoryAddresses $addresses;

    private InMemoryJournal $journal;

    private InMemoryFiles $files;

    private DownloadLinks $links;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->period = Period::of('2026-11');
        $this->at = new DateTimeImmutable('2026-11-20T10:00:00+00:00');
        $postcard = Benefit::of('postcard', 'Postcard', true);
        $credits = Benefit::of('credits', 'Credits');
        $this->entitlements = new InMemoryEntitlements()->owe(
            $this->period,
            new Entitlement('m-1', [$postcard, $credits]),
            new Entitlement('m-2', [$postcard]),
            new Entitlement('m-3', [$postcard]),
            new Entitlement('m-4', [$credits]),
        );
        $this->deliveries = new InMemoryDeliverables();
        $this->addresses = new InMemoryAddresses([
            'm-1' => Address::of('CA', ['givenName' => 'Ada', 'additionalName' => 'B.', 'familyName' => 'Lovelace', 'organization' => 'Engines', 'addressLine1' => '1 rue Saint-Paul', 'addressLine2' => 'App. 2', 'addressLine3' => 'Rear', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2Y 1G6']),
            'm-2' => Address::of('FR', ['organization' => '=HYPERLINK("x")', 'addressLine1' => '2 rue de Rivoli', 'locality' => 'Paris', 'postalCode' => '75001']),
            'm-4' => Address::of('CA', ['givenName' => 'Bob']),
        ]);
        $this->journal = new InMemoryJournal();
        $this->files = new InMemoryFiles();
        $this->links = new DownloadLinks(str_repeat('k', 32));
    }

    public function testTheFileHoldsWhatTheShipperNeedsOnly(): void
    {
        $result = $this->exports()->export($this->period, 'postcard', 'staff-1', $this->at);

        self::assertSame(
            "\u{FEFF}name,address_line_1,address_line_2,address_line_3,city,region,postal_code,country,deliverable\r\n"
            ."\"Ada B. Lovelace\",\"1 rue Saint-Paul\",\"App. 2\",Rear,Montréal,QC,\"H2Y 1G6\",CA,postcard\r\n"
            ."\"'=HYPERLINK(\"\"x\"\")\",\"2 rue de Rivoli\",,,Paris,,75001,FR,postcard\r\n",
            $this->files->files[$result->record->id],
        );
        self::assertStringNotContainsString('m-1', $this->files->files[$result->record->id]);
    }

    public function testEachExportIsJournaled(): void
    {
        $result = $this->exports()->export($this->period, 'postcard', 'staff-1', $this->at);
        $record = $result->record;

        self::assertSame([$record->id => $record], $this->journal->records);
        self::assertTrue(ExportRecord::isId($record->id));
        self::assertSame(['2026-11', 'postcard', 'staff-1', 2, 1], [$record->period->name, $record->benefit, $record->requestedBy, $record->rows, $record->withoutAddress]);
        self::assertEquals($this->at, $record->createdAt);
        self::assertEquals(new DateTimeImmutable('2026-11-20T11:00:00+00:00'), $record->expiresAt);
        self::assertSame($record->id, $this->links->verify($result->token, $this->at));
        self::assertNull($this->links->verify($result->token, $record->expiresAt));
        self::assertNotSame($record->id, $this->exports()->export($this->period, 'postcard', 'staff-1', $this->at)->record->id);
    }

    public function testDeliverablesDoneAreLeftOut(): void
    {
        new Checklist($this->entitlements, $this->deliveries)->markDone($this->period, 'postcard', ['m-1'], $this->at, 'staff-1');

        $result = $this->exports()->export($this->period, 'postcard', 'staff-1', $this->at);

        self::assertSame(1, $result->record->rows);
        self::assertStringNotContainsString('Lovelace', $this->files->files[$result->record->id]);
    }

    public function testTheMembersAreReadByPages(): void
    {
        $entitlements = new InMemoryEntitlements();
        $addresses = [];

        foreach (range(1, 501) as $i) {
            $member = \sprintf('m-%04d', $i);
            $entitlements->owe($this->period, new Entitlement($member, [Benefit::of('postcard', 'Postcard', true)]));
            $addresses[$member] = Address::of('CA', ['givenName' => 'M'.$i]);
        }

        $this->addresses = new InMemoryAddresses($addresses);
        $exports = new ShippingExports(new Checklist($entitlements, $this->deliveries), $this->addresses, $this->journal, $this->files, $this->links);

        self::assertSame(501, $exports->export($this->period, 'postcard', 'staff-1', $this->at)->record->rows);
        self::assertSame([[null, 500], ['m-0500', 500]], $entitlements->pages);
        self::assertSame([500, 1], array_map(count(...), $this->addresses->reads));
    }

    public function testAnExportOfNothingHoldsItsHeadings(): void
    {
        $result = $this->exports()->export(Period::of('2026-12'), 'postcard', 'staff-1', $this->at);

        self::assertSame(0, $result->record->rows);
        self::assertSame([], $this->addresses->reads);
        self::assertSame("\u{FEFF}name,address_line_1,address_line_2,address_line_3,city,region,postal_code,country,deliverable\r\n", $this->files->files[$result->record->id]);
    }

    public function testABenefitThatShipsNothingHasNoExport(): void
    {
        $this->expectException(ExportRefused::class);
        $this->expectExceptionMessageIsOrContains('The benefit "credits" ships nothing: it has no shipping export.');

        try {
            $this->exports()->export($this->period, 'credits', 'staff-1', $this->at);
        } finally {
            self::assertSame([], $this->journal->records);
        }
    }

    public function testTheRowsAreBounded(): void
    {
        $exports = new ShippingExports(new Checklist($this->entitlements, $this->deliveries), $this->addresses, $this->journal, $this->files, $this->links, 3_600, 2);
        self::assertSame(2, $exports->export($this->period, 'postcard', 'staff-1', $this->at)->record->rows);

        $this->addresses = new InMemoryAddresses(['m-1' => Address::of('CA'), 'm-2' => Address::of('CA'), 'm-3' => Address::of('CA')]);

        $this->expectException(ExportRefused::class);
        $this->expectExceptionMessageIsOrContains('A shipping export holds 2 rows at most.');

        new ShippingExports(new Checklist($this->entitlements, $this->deliveries), $this->addresses, $this->journal, $this->files, $this->links, 3_600, 2)->export($this->period, 'postcard', 'staff-1', $this->at);
    }

    public function testTheRequestIsCheckedBeforeAnythingIsWritten(): void
    {
        try {
            $this->exports()->export($this->period, 'postcard', ' ', $this->at);
            self::fail('The export was written.');
        } catch (InvalidDeliverable) {
            self::assertSame([], $this->files->files);
            self::assertSame([], $this->addresses->reads);
        }
    }

    public function testTheFileIsDownloadedThroughItsLinkUntilItExpires(): void
    {
        $exports = $this->exports();
        $result = $exports->export($this->period, 'postcard', 'staff-1', $this->at);

        $stream = $exports->download($result->token, $this->at->modify('+59 minutes'));
        self::assertSame($this->files->files[$result->record->id], stream_get_contents($stream));

        foreach ([
            [$result->token, $this->at->modify('+1 hour')],
            ['wrong', $this->at],
            [$this->links->sign('0123456789abcdef0123456789abcdef', $this->at->modify('+1 hour')), $this->at],
        ] as [$token, $at]) {
            try {
                $exports->download($token, $at);
                self::fail('The file was handed out.');
            } catch (ExportRefused $exportRefused) {
                self::assertSame('This export is not available: its link is wrong or expired.', $exportRefused->getMessage());
            }
        }
    }

    public function testAFileGoneIsUnavailable(): void
    {
        $exports = $this->exports();
        $result = $exports->export($this->period, 'postcard', 'staff-1', $this->at);
        unset($this->files->files[$result->record->id]);

        $this->expectException(ExportRefused::class);

        $exports->download($result->token, $this->at);
    }

    public function testAFileErasedIsUnavailableBeforeItsExpiry(): void
    {
        $exports = $this->exports();
        $result = $exports->export($this->period, 'postcard', 'staff-1', $this->at);
        $this->journal->fileErased($result->record->id, $this->at);

        $this->expectException(ExportRefused::class);

        $exports->download($result->token, $this->at);
    }

    public function testThePurgeErasesTheExpiredFilesAndKeepsTheRecords(): void
    {
        $exports = $this->exports();
        $old = $exports->export($this->period, 'postcard', 'staff-1', $this->at);
        $new = $exports->export($this->period, 'postcard', 'staff-1', $this->at->modify('+2 hours'));

        self::assertSame(1, $exports->purge($this->at->modify('+2 hours')));
        self::assertSame(0, $exports->purge($this->at->modify('+2 hours')));
        self::assertSame([$old->record->id], $this->files->deleted);
        self::assertArrayHasKey($new->record->id, $this->files->files);
        self::assertEquals($this->at->modify('+2 hours'), $this->journal->records[$old->record->id]->fileErasedAt);
        self::assertSame(2, $this->journal->records[$old->record->id]->rows);
    }

    public function testThePurgeReadsItsLimit(): void
    {
        $exports = $this->exports();
        $exports->export($this->period, 'postcard', 'staff-1', $this->at);
        $exports->export($this->period, 'postcard', 'staff-1', $this->at);

        self::assertSame(1, $exports->purge($this->at->modify('+1 day'), 1));
        self::assertSame(1, $exports->purge($this->at->modify('+1 day'), 1));

        $this->expectException(InvalidDeliverable::class);
        $this->expectExceptionMessageIsOrContains('A purge reads one export or more.');

        $exports->purge($this->at, 0);
    }

    public function testTheLifetimeAndTheRowsAreBounded(): void
    {
        $checklist = new Checklist($this->entitlements, $this->deliveries);
        $last = new ShippingExports($checklist, $this->addresses, $this->journal, $this->files, $this->links, 86_400, 1_000_000)->export($this->period, 'postcard', 's', $this->at);
        self::assertEquals($this->at->modify('+1 day'), $last->record->expiresAt);
        self::assertEquals($this->at->modify('+60 seconds'), new ShippingExports($checklist, $this->addresses, $this->journal, $this->files, $this->links, 60, 1)->export(Period::of('none'), 'postcard', 's', $this->at)->record->expiresAt);

        $refused = 0;

        foreach ([[59, 1], [86_401, 1], [3_600, 0], [3_600, 1_000_001]] as [$ttl, $rows]) {
            try {
                new ShippingExports($checklist, $this->addresses, $this->journal, $this->files, $this->links, $ttl, $rows);
                self::fail(\sprintf('%d seconds and %d rows were taken.', $ttl, $rows));
            } catch (InvalidDeliverable) {
                ++$refused;
            }
        }

        self::assertSame(4, $refused);
    }

    private function exports(): ShippingExports
    {
        return new ShippingExports(new Checklist($this->entitlements, $this->deliveries), $this->addresses, $this->journal, $this->files, $this->links);
    }
}
