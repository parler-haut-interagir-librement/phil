<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\LegalNotices\ConsentCategory;
use Phil\LegalNotices\Contract\BodyRenderer;
use Phil\LegalNotices\DocumentHistory;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Event\DocumentPublished;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNotices\PlainTextBody;
use Phil\LegalNotices\Problem;
use Phil\LegalNotices\PublicationState;
use Phil\LegalNotices\RetentionDuration;
use Phil\LegalNotices\RetentionKind;
use Phil\LegalNotices\SiteIdentity;
use Phil\LegalNotices\Tests\Unit\Fixture\InMemorySource;
use Phil\LegalNotices\Tests\Unit\Fixture\ListedConsent;
use Phil\LegalNotices\Tests\Unit\Fixture\ListedRetention;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegalNotices::class)]
#[CoversClass(Problem::class)]
#[CoversClass(DocumentPublished::class)]
final class LegalNoticesTest extends TestCase
{
    public function testTheVersionInForceRenderedWithItsPlaceholders(): void
    {
        $notices = $this->notices();
        $version = $notices->inForce(DocumentType::PrivacyPolicy, new DateTimeImmutable('2026-12-15'));

        self::assertInstanceOf(LegalDocumentVersion::class, $version);
        self::assertSame(2, $version->number);
        self::assertSame('<p>Example garde vos comptes 2 ans ; Audience.</p>', $notices->render($version, 'fr'));
        self::assertSame('<p>Example keeps your accounts 2 years; Audience.</p>', $notices->render($version, 'de'));
        self::assertNull($notices->inForce(DocumentType::LegalNotice, new DateTimeImmutable('2026-12-15')));
        self::assertSame('Example', $notices->identity()->values()['publisher_name']);
        self::assertCount(1, $notices->retention());
        self::assertCount(1, $notices->consent());
        self::assertSame(DocumentType::LegalNotice, $notices->history(DocumentType::LegalNotice)->type);
    }

    public function testWithoutRetentionNorConsent(): void
    {
        $notices = new LegalNotices(new InMemorySource(), new PlainTextBody(), new SiteIdentity());

        self::assertSame([], $notices->retention());
        self::assertSame([], $notices->consent());
        self::assertSame([], $notices->check());
        self::assertSame('<p>v1 ${retention.R-1}</p>', $notices->render(new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['fr' => 'v${version} ${retention.R-1}', 'en' => 'English'], 'text', new DateTimeImmutable()), 'fr_FR'));
    }

    public function testADocumentWithoutAPublicVersionIsLeftOut(): void
    {
        $at = new DateTimeImmutable('2026-12-01');
        $notices = new LegalNotices(new InMemorySource([
            'legal_notice' => new DocumentHistory(DocumentType::LegalNotice, [new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => '${nothing}'], 'text', $at)]),
            'privacy_policy' => new DocumentHistory(DocumentType::PrivacyPolicy, [new LegalDocumentVersion(DocumentType::PrivacyPolicy, 1, ['en' => 'Policy'], 'text', $at, PublicationState::Published, $at)]),
        ]), new PlainTextBody(), new SiteIdentity(['publisher_name' => 'Example', 'contact_email' => 'a@example.com', 'dpo_name' => 'Jo']));

        self::assertSame(['The privacy_policy requires the field "dpo_contact" of the identity.'], array_map(static fn (Problem $problem): string => $problem->message, $notices->check()));
    }

    public function testTheFallbackLocaleIsChosen(): void
    {
        $notices = new LegalNotices(new InMemorySource(), new PlainTextBody(), new SiteIdentity(), fallbackLocale: 'fr');

        self::assertSame('<p>Français</p>', $notices->render(new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => 'English', 'fr' => 'Français'], 'text', new DateTimeImmutable()), 'de'));
    }

    public function testAHistoryOfAnotherDocumentIsRefused(): void
    {
        $notices = new LegalNotices(new InMemorySource(['legal_notice' => new DocumentHistory(DocumentType::PrivacyPolicy)]), new PlainTextBody(), new SiteIdentity());

        try {
            $notices->history(DocumentType::LegalNotice);
            self::fail('The history was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame(['other_type', 'The source gave the history of the privacy_policy for the legal_notice.'], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }
    }

    public function testTheVersionsPublishedInASpanOfTime(): void
    {
        $published = $this->notices()->publishedBetween(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-12-01'));

        self::assertSame([['legal_notice', 1], ['privacy_policy', 1], ['privacy_policy', 2]], array_map(static fn (LegalDocumentVersion $version): array => [$version->type->value, $version->number], $published));
        self::assertSame([], $this->notices()->publishedBetween(new DateTimeImmutable('2026-11-01 00:00:01'), new DateTimeImmutable('2026-11-30 23:59:59')));
        self::assertCount(2, $this->notices()->publishedBetween(new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2027-01-01')));

        $event = new DocumentPublished('privacy_policy', 2);
        self::assertSame(['privacy_policy', 2], [$event->type, $event->version]);
    }

    public function testWhatThePublicDocumentsLack(): void
    {
        $problems = $this->notices()->check();

        self::assertSame([
            ['legal_notice', 'missing_field', 'The legal_notice requires the field "publisher_address" of the identity.', null, null],
            ['legal_notice', 'missing_field', 'The legal_notice requires the field "publication_director" of the identity.', null, null],
            ['legal_notice', 'missing_field', 'The legal_notice requires the field "host_name" of the identity.', null, null],
            ['legal_notice', 'missing_field', 'The legal_notice requires the field "host_address" of the identity.', null, null],
            ['legal_notice', 'missing_field', 'The legal_notice requires the field "host_contact" of the identity.', null, null],
            ['legal_notice', 'unreadable_body', 'The format "markdown" has no renderer.', 3, 'en'],
            ['legal_notice', 'unknown_placeholder', 'Version 1 in "en" names "${host_name}", which has no value.', 1, 'en'],
            ['legal_notice', 'unknown_placeholder', 'Version 1 in "en" names "${retention.R-9}", which has no value.', 1, 'en'],
            ['privacy_policy', 'missing_field', 'The privacy_policy requires the field "dpo_name" of the identity.', null, null],
            ['privacy_policy', 'unknown_placeholder', 'Version 1 in "fr" names "${nope}", which has no value.', 1, 'fr'],
        ], array_map(static fn (Problem $problem): array => [$problem->type->value, $problem->code, $problem->message, $problem->version, $problem->locale], $problems));
    }

    public function testAPlaceholderInATagIsNeverFilledAndReported(): void
    {
        $published = new DateTimeImmutable('2026-11-01');
        $version = new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => '<a href="${site_name}">${site_name}</a> ${nope}'], 'html', $published, PublicationState::Published, $published);
        $notices = new LegalNotices(
            new InMemorySource(['legal_notice' => new DocumentHistory(DocumentType::LegalNotice, [$version])]),
            new class implements BodyRenderer {
                #[Override]
                public function toHtml(string $format, string $body): string
                {
                    return $body;
                }
            },
            new SiteIdentity(['site_name' => 'javascript:alert(1)', 'publisher_name' => 'E', 'contact_email' => 'e@example.com', 'publisher_address' => 'A', 'publication_director' => 'D', 'host_name' => 'H', 'host_address' => 'HA', 'host_contact' => 'HC']),
        );

        self::assertSame('<a href="${site_name}">javascript:alert(1)</a> ${nope}', $notices->render($version, 'en'));
        self::assertSame([
            ['unknown_placeholder', 'Version 1 in "en" names "${nope}", which has no value.', 1, 'en'],
            ['misplaced_placeholder', 'Version 1 in "en" names "${site_name}" outside the text, in a tag, a comment or a script, where it is not filled.', 1, 'en'],
        ], array_map(static fn (Problem $problem): array => [$problem->code, $problem->message, $problem->version, $problem->locale], $notices->check()));
    }

    private function notices(): LegalNotices
    {
        $published = new DateTimeImmutable('2026-11-01');

        return new LegalNotices(
            new InMemorySource([
                'legal_notice' => new DocumentHistory(DocumentType::LegalNotice, [
                    new LegalDocumentVersion(DocumentType::LegalNotice, 1, ['en' => 'By ${publisher_name}, hosted by ${host_name}, ${retention.R-9}.'], 'text', new DateTimeImmutable('2027-01-01'), PublicationState::Published, $published),
                    new LegalDocumentVersion(DocumentType::LegalNotice, 2, ['en' => 'Draft ${nothing}'], 'text', new DateTimeImmutable('2027-01-01')),
                    new LegalDocumentVersion(DocumentType::LegalNotice, 3, ['en' => '# Title'], 'markdown', new DateTimeImmutable('2027-02-01'), PublicationState::Withdrawn, new DateTimeImmutable('2026-12-01')),
                ]),
                'privacy_policy' => new DocumentHistory(DocumentType::PrivacyPolicy, [
                    new LegalDocumentVersion(DocumentType::PrivacyPolicy, 1, ['fr' => 'Ancienne ${nope}', 'en' => 'Old'], 'text', $published, PublicationState::Published, $published),
                    new LegalDocumentVersion(DocumentType::PrivacyPolicy, 2, ['en' => '${publisher_name} keeps your accounts ${retention.R-1}; ${consent.analytics}.', 'fr' => '${publisher_name} garde vos comptes ${retention.R-1} ; ${consent.analytics}.'], 'text', new DateTimeImmutable('2026-12-01'), PublicationState::Published, new DateTimeImmutable('2026-12-01')),
                    new LegalDocumentVersion(DocumentType::PrivacyPolicy, 3, ['en' => 'Next'], 'text', new DateTimeImmutable('2027-12-01'), PublicationState::Published, new DateTimeImmutable('2026-12-31')),
                ]),
            ]),
            new PlainTextBody(),
            new SiteIdentity(['publisher_name' => 'Example', 'contact_email' => 'hello@example.com', 'dpo_contact' => 'dpo@example.com']),
            new ListedRetention([new RetentionDuration('R-1', 'Accounts', RetentionKind::Fixed, 'P2Y')]),
            new ListedConsent([new ConsentCategory('analytics', false, ['en' => 'Audience'])]),
        );
    }
}
