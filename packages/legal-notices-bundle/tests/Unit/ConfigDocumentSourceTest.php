<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use DateTimeImmutable;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\PublicationState;
use Phil\LegalNoticesBundle\Infrastructure\Source\ConfigDocumentSource;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigDocumentSource::class)]
final class ConfigDocumentSourceTest extends TestCase
{
    public function testTheVersionsOfTheConfiguration(): void
    {
        $source = Kit::source();
        $legal = $source->history(DocumentType::LegalNotice);

        self::assertCount(3, $legal->versions);
        self::assertEquals(new DateTimeImmutable('2026-11-01T00:00:00+00:00'), $legal->versions[0]->effectiveAt);
        self::assertEquals(new DateTimeImmutable('2026-11-01T00:00:00+00:00'), $legal->versions[0]->publishedAt);
        self::assertEquals(new DateTimeImmutable('2027-01-01T00:00:00+00:00'), $legal->versions[1]->effectiveAt);
        self::assertSame(['en' => 'New host', 'fr' => 'Nouvel hébergeur'], $legal->versions[1]->summaries);
        self::assertSame([PublicationState::Published, PublicationState::Published, PublicationState::Draft], array_map(static fn (LegalDocumentVersion $version): PublicationState => $version->state, $legal->versions));
        self::assertNull($legal->versions[2]->publishedAt);
        self::assertSame('UTC', $legal->versions[2]->effectiveAt->getTimezone()->getName());
        self::assertSame(PublicationState::Withdrawn, $source->history(DocumentType::PrivacyPolicy)->versions[0]->state);
        self::assertSame([], new ConfigDocumentSource([])->history(DocumentType::PrivacyPolicy)->versions);
    }

    public function testTheShapesOfADate(): void
    {
        foreach (['2026-12-01' => '2026-12-01T00:00:00+00:00', '2026-12-01T09:30' => '2026-12-01T09:30:00+00:00', '2026-12-01 09:30:15' => '2026-12-01T09:30:15+00:00', '2026-12-01T09:30Z' => '2026-12-01T09:30:00+00:00', '2026-12-01T09:30:00-05:00' => '2026-12-01T09:30:00-05:00'] as $date => $expected) {
            $source = new ConfigDocumentSource(['legal_notice' => [Kit::version(1, 'draft', null, $date, ['en' => 'a'])]]);

            self::assertSame($expected, $source->history(DocumentType::LegalNotice)->versions[0]->effectiveAt->format(\DATE_ATOM));
        }
    }

    /**
     * @return iterable<string, array{array{number: int, format: string, state: string, published_at: string|null, effective_at: string|null, bodies: array<string, string>, summaries: array<string, string>}, string, string}>
     */
    public static function badVersions(): iterable
    {
        yield 'an unknown state' => [Kit::version(1, 'gone', null, '2026-12-01', ['en' => 'a']), 'state', 'Version 1 of the legal_notice is in no state named "gone".'];
        yield 'no date' => [Kit::version(2, 'draft', null, null, ['en' => 'a']), 'no_effective_date', 'Version 2 of the legal_notice has an effective date, or a publication date it takes effect on.'];
        yield 'a word' => [Kit::version(1, 'draft', null, 'tomorrow', ['en' => 'a']), 'date', '"tomorrow" is not a date such as "2026-12-01" or "2026-12-01T09:00".'];
        yield 'a month that is none' => [Kit::version(1, 'draft', null, '2026-13-01', ['en' => 'a']), 'date', '"2026-13-01" is not a date such as "2026-12-01" or "2026-12-01T09:00".'];
        yield 'a trailing line' => [Kit::version(1, 'draft', null, "2026-12-01\n", ['en' => 'a']), 'date', "\"2026-12-01\n\" is not a date such as \"2026-12-01\" or \"2026-12-01T09:00\"."];
        yield 'a prefix' => [Kit::version(1, 'draft', null, 'x2026-12-01', ['en' => 'a']), 'date', '"x2026-12-01" is not a date such as "2026-12-01" or "2026-12-01T09:00".'];
        yield 'a bad publication date' => [Kit::version(1, 'published', '2026-12', null, ['en' => 'a']), 'date', '"2026-12" is not a date such as "2026-12-01" or "2026-12-01T09:00".'];
        yield 'a rule of the version' => [Kit::version(1, 'draft', '2026-12-01', null, ['en' => 'a']), 'draft_published_at', 'A draft has no publication date.'];
    }

    /**
     * @param array{number: int, format: string, state: string, published_at: string|null, effective_at: string|null, bodies: array<string, string>, summaries: array<string, string>} $version
     */
    #[DataProvider('badVersions')]
    public function testABadVersionIsRefused(array $version, string $reason, string $message): void
    {
        try {
            new ConfigDocumentSource(['legal_notice' => [$version]]);
            self::fail('The version was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame([$reason, $message], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }
    }
}
