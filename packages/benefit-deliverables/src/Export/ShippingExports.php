<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Export;

use DateTimeImmutable;
use Generator;
use Phil\Address\Address;
use Phil\Address\AddressField;
use Phil\BenefitDeliverables\Checklist;
use Phil\BenefitDeliverables\ChecklistItem;
use Phil\BenefitDeliverables\Contract\DeliveryAddressProvider;
use Phil\BenefitDeliverables\Contract\ExportFileStore;
use Phil\BenefitDeliverables\Contract\ExportJournal;
use Phil\BenefitDeliverables\Exception\ExportRefused;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use Phil\BenefitDeliverables\Period;
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Contract\SpreadsheetWriter;
use Phil\Spreadsheet\Csv\CsvWriter;
use Phil\Spreadsheet\Headings;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\WriteOptions;
use SensitiveParameter;

/**
 * The shipping export of a benefit that ships an object: a CSV file of the deliverables still to be served, with only
 * what the shipper needs, kept a short while, downloaded through a signed link.
 *
 * - **Minimal**: each row holds the recipient's name, the lines of the address, the city, the region, the postal code,
 *   the country and the code of the benefit. Never the member's email address, identifier or tier.
 * - **Guarded**: the file is written by `phil/spreadsheet`, which keeps as text a value a spreadsheet would run as a
 *   formula.
 * - **Journaled**: each export is recorded, who asked for it, when, how many rows, how many members were left out for
 *   want of an address.
 * - **Short-lived**: the link and the file expire after `$ttlSeconds`; `purge()` erases the files expired, the records
 *   staying as the trace.
 */
final readonly class ShippingExports
{
    /** How many members each read of the checklist and of the addresses covers. */
    private const int BATCH = 500;

    /**
     * @param int $ttlSeconds how long the link and the file of an export last, 60 seconds to a day
     * @param int $maxRows    the most rows an export holds
     */
    public function __construct(
        private Checklist $checklist,
        private DeliveryAddressProvider $addresses,
        private ExportJournal $journal,
        private ExportFileStore $files,
        private DownloadLinks $links,
        private int $ttlSeconds = 3_600,
        private int $maxRows = 50_000,
        private SpreadsheetWriter $writer = new CsvWriter(),
    ) {
        if ($ttlSeconds < 60 || $ttlSeconds > 86_400) {
            throw InvalidDeliverable::because('An export lasts 60 seconds to a day.');
        }

        if ($maxRows < 1 || $maxRows > 1_000_000) {
            throw InvalidDeliverable::because('An export holds 1 to 1000000 rows at most.');
        }
    }

    /**
     * Writes the export of the deliverables of `$benefit` still to be served in `$period`, records it, and returns
     * it with the token of its download link.
     *
     * @param string $requestedBy the identifier of the person on the team who asks for it
     */
    public function export(Period $period, string $benefit, string $requestedBy, DateTimeImmutable $at): ExportResult
    {
        $id = sodium_bin2hex(random_bytes(16));
        $expiresAt = $at->modify(\sprintf('+%d seconds', $this->ttlSeconds));
        // Checked before anything is written.
        new ExportRecord($id, $period, $benefit, $requestedBy, $at, $expiresAt, 0, 0);
        $counts = new ExportCounts();
        $columns = [
            new Column('name'),
            new Column('address_line_1'),
            new Column('address_line_2'),
            new Column('address_line_3'),
            new Column('city'),
            new Column('region'),
            new Column('postal_code'),
            new Column('country'),
            new Column('deliverable'),
        ];

        $this->files->write($id, function (mixed $stream) use ($period, $benefit, $counts, $columns): void {
            $this->writer->write(Workbook::single('shipping', $this->rows($period, $benefit, $counts), $columns), $stream, new WriteOptions(Headings::Codes));
        });

        $record = new ExportRecord($id, $period, $benefit, $requestedBy, $at, $expiresAt, $counts->rows, $counts->withoutAddress);
        $this->journal->record($record);

        return new ExportResult($record, $this->links->sign($id, $expiresAt));
    }

    /**
     * The file of the export the token names, open for reading.
     *
     * @return resource
     *
     * @throws ExportRefused when the token is wrong or expired, the export unknown, or its file erased
     */
    public function download(#[SensitiveParameter] string $token, DateTimeImmutable $at): mixed
    {
        $id = $this->links->verify($token, $at);
        $record = null === $id ? null : $this->journal->find($id);

        if (!$record instanceof ExportRecord || !$record->isAvailable($at)) {
            throw ExportRefused::unavailable();
        }

        return $this->files->open($record->id) ?? throw ExportRefused::unavailable();
    }

    /**
     * Erases the files of up to `$limit` exports expired at `$at`; run again, it finds nothing left.
     *
     * @return int how many files were erased
     */
    public function purge(DateTimeImmutable $at, int $limit = 500): int
    {
        if ($limit < 1) {
            throw InvalidDeliverable::because('A purge reads one export or more.');
        }

        $erased = 0;

        foreach ($this->journal->expired($at, $limit) as $record) {
            $this->files->delete($record->id);
            $this->journal->fileErased($record->id, $at);
            ++$erased;
        }

        return $erased;
    }

    /**
     * @return Generator<int, list<string>>
     */
    private function rows(Period $period, string $benefit, ExportCounts $counts): Generator
    {
        $after = null;

        do {
            $page = $this->checklist->remaining($period, $benefit, $after, self::BATCH);
            $members = [];

            foreach ($page->items as $item) {
                if (!$item->benefit->ships) {
                    throw ExportRefused::shipsNothing($benefit);
                }

                $members[] = $item->key->member;
            }

            $addresses = [] === $members ? [] : $this->addresses->addressesOf($members);

            foreach ($page->items as $item) {
                $address = $addresses[$item->key->member] ?? null;

                if (!$address instanceof Address) {
                    ++$counts->withoutAddress;

                    continue;
                }

                if (++$counts->rows > $this->maxRows) {
                    throw ExportRefused::tooManyRows($this->maxRows);
                }

                yield $this->row($address, $item);
            }

            $after = $page->next;
        } while (null !== $after);
    }

    /**
     * @return list<string>
     */
    private function row(Address $address, ChecklistItem $item): array
    {
        $person = implode(' ', array_filter([
            $address->get(AddressField::GivenName),
            $address->get(AddressField::AdditionalName),
            $address->get(AddressField::FamilyName),
        ], static fn (?string $part): bool => null !== $part));

        return [
            '' !== $person ? $person : ($address->get(AddressField::Organization) ?? ''),
            $address->get(AddressField::AddressLine1) ?? '',
            $address->get(AddressField::AddressLine2) ?? '',
            $address->get(AddressField::AddressLine3) ?? '',
            $address->get(AddressField::Locality) ?? '',
            $address->get(AddressField::AdministrativeArea) ?? '',
            $address->get(AddressField::PostalCode) ?? '',
            $address->country,
            $item->benefit->code,
        ];
    }
}
