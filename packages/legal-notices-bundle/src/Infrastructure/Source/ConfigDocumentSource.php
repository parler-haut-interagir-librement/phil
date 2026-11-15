<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Source;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Override;
use Phil\LegalNotices\Contract\DocumentSource;
use Phil\LegalNotices\DocumentHistory;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\PublicationState;

/**
 * The versions written in the configuration, built once when the service is: nothing is read again, nothing changes
 * between two requests. A date is `2026-12-01` or `2026-12-01T09:00`, maybe with an offset, read in UTC when it has
 * none; the effective date is the publication date when it is left out.
 *
 * @phpstan-type VersionConfig array{number: int, format: string, state: string, published_at: string|null, effective_at: string|null, bodies: array<string, string>, summaries: array<string, string>}
 */
final readonly class ConfigDocumentSource implements DocumentSource
{
    public const string DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})?)?$/D';

    /**
     * @var array<string, DocumentHistory> by type
     */
    private array $histories;

    /**
     * @param array<string, list<VersionConfig>> $documents the versions of each type
     *
     * @throws InvalidLegalNotice
     */
    public function __construct(array $documents)
    {
        $histories = [];

        foreach (DocumentType::cases() as $type) {
            $histories[$type->value] = new DocumentHistory($type, array_map(static fn (array $version): LegalDocumentVersion => self::version($type, $version), $documents[$type->value] ?? []));
        }

        $this->histories = $histories;
    }

    #[Override]
    public function history(DocumentType $type): DocumentHistory
    {
        return $this->histories[$type->value];
    }

    /**
     * @param VersionConfig $version
     */
    private static function version(DocumentType $type, array $version): LegalDocumentVersion
    {
        $state = PublicationState::tryFrom($version['state']) ?? throw InvalidLegalNotice::because('state', \sprintf('Version %d of the %s is in no state named %s.', $version['number'], $type->value, InvalidLegalNotice::quote($version['state'])));
        $publishedAt = null === $version['published_at'] ? null : self::date($version['published_at']);
        $effectiveAt = null === $version['effective_at'] ? $publishedAt : self::date($version['effective_at']);

        if (!$effectiveAt instanceof DateTimeImmutable) {
            throw InvalidLegalNotice::because('no_effective_date', \sprintf('Version %d of the %s has an effective date, or a publication date it takes effect on.', $version['number'], $type->value));
        }

        return new LegalDocumentVersion($type, $version['number'], $version['bodies'], $version['format'], $effectiveAt, $state, $publishedAt, $version['summaries']);
    }

    private static function date(string $date): DateTimeImmutable
    {
        try {
            if (1 === preg_match(self::DATE_PATTERN, $date)) {
                return new DateTimeImmutable($date, new DateTimeZone('UTC'));
            }
        } catch (Exception) {
            // Refused below, as a date of the wrong shape.
        }

        throw InvalidLegalNotice::because('date', \sprintf('%s is not a date such as "2026-12-01" or "2026-12-01T09:00".', InvalidLegalNotice::quote($date)));
    }
}
