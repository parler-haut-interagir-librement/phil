<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Bridge;

use Phil\SeoCheck\Bridge\SocialCards\ShareTagsRule;
use Phil\SeoCheck\Bridge\StructuredData\StructuredDataRule;
use Phil\SeoCheck\Tests\Support\Pages;
use Phil\SeoCheck\Tests\Unit\Rule\RuleTestCase;
use Phil\StructuredData\Validation\TypeRules;
use Phil\StructuredData\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StructuredDataRule::class)]
#[CoversClass(ShareTagsRule::class)]
final class BridgesTest extends RuleTestCase
{
    private const string EVENT = '<script type="application/ld+json">{"@context": "https://schema.org", "@type": "Event", "name": "Ouverture", "startDate": "2026-12-05T10:00:00-05:00", "location": {"@type": "Place", "name": "La boutique", "address": "1 rue Principale"}, "image": "https://example.org/a.jpg", "description": "Ouverture de la boutique", "endDate": "2026-12-05T17:00:00-05:00", "eventStatus": "https://schema.org/EventScheduled", "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode", "organizer": {"@type": "Organization", "name": "La boutique", "url": "https://example.org/", "logo": "https://example.org/logo.png"}, "offers": {"@type": "Offer", "price": "0", "priceCurrency": "CAD", "url": "https://example.org/fr/news/opening", "availability": "https://schema.org/InStock", "validFrom": "2026-11-01"}, "performer": {"@type": "Person", "name": "Ada", "url": "https://example.org/ada", "sameAs": "https://ada.example.net/"}}</script>';

    private const string SHARE = '<link rel="canonical" href="https://example.org/fr/news/opening">'
        .'<meta property="og:title" content="Ouverture"><meta property="og:type" content="article">'
        .'<meta property="og:url" content="https://example.org/fr/news/opening"><meta property="og:image" content="https://example.org/a.jpg">'
        .'<meta property="og:image:alt" content="La vitrine"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">'
        .'<meta property="og:description" content="La boutique ouvre samedi."><meta name="twitter:card" content="summary_large_image">';

    public function testTheRulesHaveTheirNames(): void
    {
        self::assertSame('structured-data', new StructuredDataRule()->name());
        self::assertSame('share-tags', new ShareTagsRule()->name());
    }

    public function testValidStructuredDataPasses(): void
    {
        self::assertSame([], self::lines(new StructuredDataRule(), Pages::checked(Pages::html(self::EVENT))));
    }

    public function testAPageWithoutStructuredDataIsAnInformation(): void
    {
        self::assertSame(['info structured-data.none'], self::lines(new StructuredDataRule(), Pages::checked(Pages::html())));
    }

    public function testTheIssuesOfTheValidatorAreReported(): void
    {
        $html = Pages::html('<script type="application/ld+json">{"@context": "https://schema.org", "@type": "Event", "startDate": "tomorrow"}</script><script type="application/ld+json">{not json</script>');
        $lines = self::lines(new StructuredDataRule(), Pages::checked($html));

        self::assertStringStartsWith('error structured-data.unreadable (reason=JSON-LD element 2: ', $lines[0]);
        self::assertContains('error structured-data.issue (code=missing-property, node=Event @graph[0], message=Event @graph[0] needs name.) @ Event @graph[0]', $lines);
        self::assertContains('error structured-data.issue (code=invalid-date, node=Event @graph[0], message=Event @graph[0] › startDate is an ISO 8601 date or date and time; "tomorrow" is not.) @ Event @graph[0]', $lines);
        self::assertTrue(array_any($lines, static fn (string $line): bool => str_starts_with($line, 'warning structured-data.issue (code=missing-recommended')));
    }

    public function testTheValidatorIsChosen(): void
    {
        $validator = new Validator([new TypeRules('Thing', required: ['sameAs'])]);
        $html = Pages::html('<script type="application/ld+json">{"@context": "https://schema.org", "@type": "Thing"}</script>');

        self::assertSame(
            ['error structured-data.issue (code=missing-property, node=Thing @graph[0], message=Thing @graph[0] needs sameAs.) @ Thing @graph[0]'],
            self::lines(new StructuredDataRule($validator), Pages::checked($html)),
        );
    }

    public function testAPageTooLargeForTheReaderIsAWarning(): void
    {
        $lines = self::lines(new StructuredDataRule(), Pages::checked(Pages::html().str_repeat(' ', 5_000_001)));

        self::assertSame(['warning structured-data.unreadable (reason=A page read for its structured data is 5000000 bytes at most.)'], $lines);
    }

    public function testCompleteShareTagsPass(): void
    {
        self::assertSame([], self::lines(new ShareTagsRule(), Pages::checked(Pages::html(self::SHARE))));
    }

    public function testTheFindingsOfTheTagCheckAreReported(): void
    {
        $lines = self::lines(new ShareTagsRule(), Pages::checked(Pages::html('<meta property="og:title" content="Ouverture"><meta property="og:url" content="/fr/">')));

        self::assertContains('error share-tags.issue (tag=og:type, message=The page has no og:type, which Open Graph requires.) @ og:type', $lines);
        self::assertContains('error share-tags.issue (tag=og:url, message=The og:url "/fr/" is not an absolute HTTP or HTTPS address.) @ og:url', $lines);
        self::assertContains('warning share-tags.issue (tag=og:description, message=The page has no og:description: readers show a link without a summary.) @ og:description', $lines);
    }
}
