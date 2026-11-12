<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit;

use DateTimeImmutable;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\PublicationState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegalDocumentVersion::class)]
final class LegalDocumentVersionTest extends TestCase
{
    public function testAPublishedVersion(): void
    {
        $version = new LegalDocumentVersion(
            DocumentType::PrivacyPolicy,
            2,
            ['en' => ' Hello ', 'fr' => 'Bonjour'],
            'markdown',
            new DateTimeImmutable('2026-12-01'),
            PublicationState::Published,
            new DateTimeImmutable('2026-11-01'),
            ['fr' => 'Durées ajoutées'],
        );

        self::assertSame(['en' => 'Hello', 'fr' => 'Bonjour'], $version->bodies);
        self::assertSame(['en', 'fr'], $version->locales());
        self::assertSame('Bonjour', $version->body('fr_CA', 'en'));
        self::assertSame('Hello', $version->body('de', 'en'));
        self::assertSame('Durées ajoutées', $version->summary('en', 'fr'));
        self::assertTrue($version->isPublic());
        self::assertFalse($version->isInForceAt(new DateTimeImmutable('2026-11-30 23:59:59')));
        self::assertTrue($version->isInForceAt(new DateTimeImmutable('2026-12-01')));
        self::assertSame([DocumentType::PrivacyPolicy, 2, 'markdown', PublicationState::Published], [$version->type, $version->number, $version->format, $version->state]);
    }

    public function testADraftIsPublishedThenWithdrawn(): void
    {
        $draft = new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => 'Hello'], 'text', new DateTimeImmutable('2026-12-01'));

        self::assertSame(PublicationState::Draft, $draft->state);
        self::assertNull($draft->publishedAt);
        self::assertNull($draft->summary('en', 'en'));
        self::assertFalse($draft->isPublic());
        self::assertFalse($draft->isInForceAt(new DateTimeImmutable('2027-01-01')));

        $published = $draft->publish(new DateTimeImmutable('2026-12-01'));
        self::assertSame(PublicationState::Published, $published->state);
        self::assertEquals(new DateTimeImmutable('2026-12-01'), $published->publishedAt);
        self::assertEquals([DocumentType::LegalNotice, 1, ['en' => 'Hello'], 'text', new DateTimeImmutable('2026-12-01'), []], [$published->type, $published->number, $published->bodies, $published->format, $published->effectiveAt, $published->summaries]);

        $withdrawn = new LegalDocumentVersion(DocumentType::LegalNotice, 3, ['en' => 'Hello'], 'text', new DateTimeImmutable('2026-12-02'), summaries: ['en' => 'Fixed'])->publish(new DateTimeImmutable('2026-12-01'))->withdraw();
        self::assertSame(PublicationState::Withdrawn, $withdrawn->state);
        self::assertEquals(new DateTimeImmutable('2026-12-01'), $withdrawn->publishedAt);
        self::assertEquals([DocumentType::LegalNotice, 3, ['en' => 'Hello'], 'text', new DateTimeImmutable('2026-12-02'), ['en' => 'Fixed']], [$withdrawn->type, $withdrawn->number, $withdrawn->bodies, $withdrawn->format, $withdrawn->effectiveAt, $withdrawn->summaries]);
        self::assertTrue($withdrawn->isPublic());
        self::assertFalse($withdrawn->isInForceAt(new DateTimeImmutable('2027-01-01')));
    }

    public function testOnlyADraftIsPublishedAndOnlyAPublishedVersionWithdrawn(): void
    {
        $published = new LegalDocumentVersion(DocumentType::LegalNotice, 4, ['en' => 'a'], 'text', new DateTimeImmutable('2026-12-01'), PublicationState::Published, new DateTimeImmutable('2026-12-01'));

        try {
            $published->publish(new DateTimeImmutable('2026-12-01'));
            self::fail('A published version was published again.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame(['not_a_draft', 'Version 4 is published: only a draft is published.'], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }

        try {
            $published->withdraw()->withdraw();
            self::fail('A withdrawn version was withdrawn again.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame(['not_published', 'Version 4 is withdrawn: only a published version is withdrawn.'], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }

        $this->expectExceptionMessageIsOrContains('A version takes effect when it is published or later.');
        new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => 'a'], 'text', new DateTimeImmutable('2026-12-01'))->publish(new DateTimeImmutable('2026-12-01 00:00:01'));
    }

    public function testTheBounds(): void
    {
        $at = new DateTimeImmutable('2026-12-01');

        self::assertSame(999999, new LegalDocumentVersion(DocumentType::LegalNotice, 999999, ['en' => 'a'], 'text', $at)->number);
        self::assertSame(str_repeat('a', 262144), new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => str_repeat('a', 262144)], 'text', $at)->bodies['en']);
        self::assertSame(str_repeat('a', 1000), new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => 'a'], 'text', $at, summaries: ['en' => str_repeat('a', 1000)])->summaries['en']);
        self::assertSame('a1_-'.str_repeat('b', 28), new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => 'a'], 'a1_-'.str_repeat('b', 28), $at)->format);
    }

    /**
     * @return iterable<string, array{int, array<array-key, mixed>, string, PublicationState, ?string, array<array-key, mixed>, string, string}>
     */
    public static function badVersions(): iterable
    {
        yield 'number zero' => [0, ['en' => 'a'], 'text', PublicationState::Draft, null, [], 'version_number', 'A version number goes from 1 to 999999.'];
        yield 'number too high' => [1000000, ['en' => 'a'], 'text', PublicationState::Draft, null, [], 'version_number', 'A version number goes from 1 to 999999.'];
        yield 'a bad format' => [1, ['en' => 'a'], 'Text', PublicationState::Draft, null, [], 'format', '"Text" is not the name of a format such as "text" or "markdown".'];
        yield 'a format too long' => [1, ['en' => 'a'], 'a'.str_repeat('b', 32), PublicationState::Draft, null, [], 'format', '"a'.str_repeat('b', 32).'" is not the name of a format such as "text" or "markdown".'];
        yield 'a format with a line' => [1, ['en' => 'a'], "text\n", PublicationState::Draft, null, [], 'format', "\"text\n\" is not the name of a format such as \"text\" or \"markdown\"."];
        yield 'a format with a prefix' => [1, ['en' => 'a'], '-text', PublicationState::Draft, null, [], 'format', '"-text" is not the name of a format such as "text" or "markdown".'];
        yield 'a draft with a date' => [1, ['en' => 'a'], 'text', PublicationState::Draft, '2026-11-01', [], 'draft_published_at', 'A draft has no publication date.'];
        yield 'published without a date' => [1, ['en' => 'a'], 'text', PublicationState::Published, null, [], 'no_publication_date', 'A published or withdrawn version has its publication date.'];
        yield 'withdrawn without a date' => [1, ['en' => 'a'], 'text', PublicationState::Withdrawn, null, [], 'no_publication_date', 'A published or withdrawn version has its publication date.'];
        yield 'effective before it is published' => [1, ['en' => 'a'], 'text', PublicationState::Published, '2026-12-01 00:00:01', [], 'effective_before_publication', 'A version takes effect when it is published or later.'];
        yield 'no body' => [1, [], 'text', PublicationState::Draft, null, [], 'no_body', 'A version has the body of one locale at least.'];
        yield 'a body too long' => [1, ['en' => str_repeat('a', 262145)], 'text', PublicationState::Draft, null, [], 'invalid_text', 'The body in "en" is UTF-8 text of 262144 bytes at most.'];
        yield 'a summary too long' => [1, ['en' => 'a'], 'text', PublicationState::Draft, null, ['en' => str_repeat('a', 1001)], 'invalid_text', 'The summary in "en" is UTF-8 text of 1000 bytes at most.'];
    }

    /**
     * @param array<array-key, mixed> $bodies
     * @param array<array-key, mixed> $summaries
     */
    #[DataProvider('badVersions')]
    public function testABadVersionIsRefused(int $number, array $bodies, string $format, PublicationState $state, ?string $publishedAt, array $summaries, string $reason, string $message): void
    {
        try {
            new LegalDocumentVersion(DocumentType::LegalNotice, $number, $bodies, $format, new DateTimeImmutable('2026-12-01'), $state, null === $publishedAt ? null : new DateTimeImmutable($publishedAt), $summaries);
            self::fail('The version was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame([$reason, $message], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }
    }

    public function testALongFormatIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('X', 80).'" is not the name');

        new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => 'a'], str_repeat('X', 90), new DateTimeImmutable());
    }
}
