<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit\Fixture;

use Phil\LegalNotices\Contract\ConsentCategories;
use Phil\LegalNotices\Contract\RetentionDurations;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNotices\PlainTextBody;
use Phil\LegalNotices\SiteIdentity;
use Phil\LegalNoticesBundle\Infrastructure\Source\ConfigDocumentSource;

/**
 * The documents the tests read: a legal notice in force since 1 November 2026 with a second version to come on
 * 1 January 2027, a privacy policy in force since 1 December 2026 after a withdrawn version, and a draft.
 */
final class Kit
{
    private function __construct()
    {
    }

    public static function notices(?RetentionDurations $retention = null, ?ConsentCategories $consent = null): LegalNotices
    {
        return new LegalNotices(self::source(), new PlainTextBody(), new SiteIdentity(['publisher_name' => 'Example']), $retention, $consent);
    }

    public static function source(): ConfigDocumentSource
    {
        return new ConfigDocumentSource([
            'legal_notice' => [
                self::version(1, 'published', '2026-11-01', null, ['en' => 'Published by ${publisher_name}.', 'fr' => 'Publié par ${publisher_name}.']),
                self::version(2, 'published', '2026-12-01', '2027-01-01', ['en' => 'Second'], ['en' => 'New host', 'fr' => 'Nouvel hébergeur']),
                self::version(3, 'draft', null, '2027-02-01', ['en' => 'Draft']),
            ],
            'privacy_policy' => [
                self::version(1, 'withdrawn', '2026-11-01', null, ['en' => 'Withdrawn']),
                self::version(2, 'published', '2026-12-01', null, ['en' => 'Policy']),
            ],
        ]);
    }

    /**
     * @param array<string, string> $bodies
     * @param array<string, string> $summaries
     *
     * @return array{number: int, format: string, state: string, published_at: string|null, effective_at: string|null, bodies: array<string, string>, summaries: array<string, string>}
     */
    public static function version(int $number, string $state, ?string $publishedAt, ?string $effectiveAt, array $bodies, array $summaries = [], string $format = 'text'): array
    {
        return ['number' => $number, 'format' => $format, 'state' => $state, 'published_at' => $publishedAt, 'effective_at' => $effectiveAt, 'bodies' => $bodies, 'summaries' => $summaries];
    }
}
