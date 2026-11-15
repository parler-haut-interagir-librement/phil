<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use Phil\LegalNotices\ConsentCategory;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\RetentionDuration;
use Phil\LegalNotices\RetentionKind;
use Phil\LegalNoticesBundle\Infrastructure\Body\MarkupBody;
use Phil\LegalNoticesBundle\Infrastructure\Consent\ConfiguredConsentCategories;
use Phil\LegalNoticesBundle\Infrastructure\Privacy\ScheduleRetentionDurations;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\FixedSchedule;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Exception\UnknownFormat;
use Phil\Markup\Markup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkupBody::class)]
#[CoversClass(ScheduleRetentionDurations::class)]
#[CoversClass(ConfiguredConsentCategories::class)]
final class AdaptersTest extends TestCase
{
    public function testTheBodiesAreRenderedByMarkup(): void
    {
        $body = new MarkupBody(new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies()));

        self::assertStringContainsString('<strong>Example</strong>', $body->toHtml('markdown', 'By **Example**, ${publisher_name}.'));

        foreach (['markdown', 'html', 'bbcode', 'mediawiki', 'dokuwiki', 'creole', 'text'] as $format) {
            self::assertStringContainsString('${publisher_name}, ${retention.R-001} and ${consent.analytics}', $body->toHtml($format, 'By ${publisher_name}, ${retention.R-001} and ${consent.analytics}.'), $format);
        }

        self::assertStringNotContainsString('<script', $body->toHtml('html', '<p>a</p><script>alert(1)</script>'));

        try {
            $body->toHtml('nope', 'a');
            self::fail('An unknown format was rendered.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame('unreadable_body', $invalidLegalNotice->reason);
            self::assertInstanceOf(UnknownFormat::class, $invalidLegalNotice->getPrevious());
            self::assertSame($invalidLegalNotice->getPrevious()->getMessage(), $invalidLegalNotice->getMessage());
        }
    }

    public function testTheRetentionPeriodsOfTheSchedule(): void
    {
        $durations = new ScheduleRetentionDurations(new FixedSchedule(), ['R-001' => ['fr' => 'Comptes']])->durations();

        self::assertEquals([
            new RetentionDuration('R-001', 'users', RetentionKind::Fixed, 'P2Y', null, ['fr' => 'Comptes']),
            new RetentionDuration('R-002', 'orders', RetentionKind::UntilEvent, null, 'the end of the warranty'),
            new RetentionDuration('R-003', 'logs', RetentionKind::NotYetFixed),
        ], $durations);
        self::assertCount(4, new ScheduleRetentionDurations(new FixedSchedule(), personalOnly: false)->durations());
        self::assertSame(RetentionKind::Indefinite, new ScheduleRetentionDurations(new FixedSchedule(), personalOnly: false)->durations()[3]->kind);
    }

    public function testTheConsentCategoriesOfTheConfiguration(): void
    {
        $categories = new ConfiguredConsentCategories([
            'necessary' => ['necessary' => true, 'labels' => ['en' => 'Necessary'], 'descriptions' => [], 'cookies' => ['PHPSESSID']],
            'analytics' => ['necessary' => false, 'labels' => ['en' => 'Audience'], 'descriptions' => ['en' => 'Pages seen'], 'cookies' => []],
        ])->categories();

        self::assertSame(['necessary', 'analytics'], array_map(static fn (ConsentCategory $category): string => $category->key, $categories));
        self::assertSame([true, false], [$categories[0]->necessary, $categories[1]->necessary]);
        self::assertSame([['PHPSESSID'], []], [$categories[0]->cookies, $categories[1]->cookies]);
        self::assertSame('Pages seen', $categories[1]->description('en', 'en'));
        self::assertSame([], new ConfiguredConsentCategories([])->categories());

        $this->expectExceptionMessageIsOrContains('"0" is not a consent category key');
        new ConfiguredConsentCategories([['necessary' => true, 'labels' => ['en' => 'Necessary'], 'descriptions' => [], 'cookies' => []]]);
    }
}
