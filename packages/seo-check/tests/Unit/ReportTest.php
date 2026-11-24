<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit;

use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Report;
use Phil\SeoCheck\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

#[CoversClass(Report::class)]
#[CoversClass(Finding::class)]
#[CoversClass(Message::class)]
#[CoversClass(Severity::class)]
final class ReportTest extends TestCase
{
    private function report(string $locale = 'en'): Report
    {
        return new Report([
            new Finding('document-title', Severity::Error, new Message('document-title.missing')),
            new Finding('image-alt', Severity::Warning, new Message('image-alt.missing', ['src' => '/a.png']), '/a.png'),
            new Finding('meta-description', Severity::Info, new Message('meta-description.short', ['length' => 12, 'min' => 50])),
            new Finding('image-alt', Severity::Warning, new Message('image-alt.missing', ['src' => '/b.png']), '/b.png'),
        ], locale: $locale);
    }

    public function testTheFindingsAreSelectedBySeverityAndRule(): void
    {
        $report = $this->report();

        self::assertCount(4, $report);
        self::assertFalse($report->isEmpty());
        self::assertTrue(new Report()->isEmpty());
        self::assertSame($report->findings, $report->findings());
        self::assertSame([$report->findings[0], $report->findings[1], $report->findings[3]], $report->findings(Severity::Warning));
        self::assertSame([$report->findings[0]], $report->findings(Severity::Error));
        self::assertSame([$report->findings[1], $report->findings[3]], $report->withSeverity(Severity::Warning));
        self::assertSame([$report->findings[2]], $report->withSeverity(Severity::Info));
        self::assertSame([$report->findings[1], $report->findings[3]], $report->ofRule('image-alt'));
        self::assertSame([], $report->ofRule('canonical'));
    }

    public function testItsMessagesAreWrittenInItsLocale(): void
    {
        $report = $this->report();

        self::assertSame('The page has no title element: results pages show its address instead.', $report->message($report->findings[0]));
        self::assertSame('L’image /a.png n’a pas d’attribut alt : décrivez-la, ou donnez-lui alt="" si elle n’est qu’une décoration.', $report->inLocale('fr-CA')->message($report->findings[1]));
        self::assertSame('fr-CA', $report->inLocale('fr-CA')->locale);
        self::assertSame($report->findings, $report->inLocale('fr')->findings);
        self::assertSame($report->translator, $report->inLocale('fr')->translator);
    }

    public function testAnApplicationGivesItsTranslator(): void
    {
        $translator = new IdentityTranslator();
        $report = new Report([new Finding('image-alt', Severity::Warning, new Message('image-alt.missing', ['src' => '/a.png']))], $translator, 'fr');

        self::assertSame('image-alt.missing', $report->message($report->findings[0]));
    }

    public function testItIsAnArrayWithoutScore(): void
    {
        self::assertSame([
            'locale' => 'en',
            'counts' => ['error' => 1, 'warning' => 2, 'info' => 1],
            'findings' => [
                ['rule' => 'document-title', 'severity' => 'error', 'message' => 'The page has no title element: results pages show its address instead.', 'messageKey' => 'document-title.missing', 'parameters' => [], 'subject' => null],
                ['rule' => 'image-alt', 'severity' => 'warning', 'message' => 'The image /a.png has no alt attribute: describe it, or give it alt="" when it is only decoration.', 'messageKey' => 'image-alt.missing', 'parameters' => ['src' => '/a.png'], 'subject' => '/a.png'],
                ['rule' => 'meta-description', 'severity' => 'info', 'message' => 'The meta description has 12 characters: below 50, it says little of the page.', 'messageKey' => 'meta-description.short', 'parameters' => ['length' => 12, 'min' => 50], 'subject' => null],
                ['rule' => 'image-alt', 'severity' => 'warning', 'message' => 'The image /b.png has no alt attribute: describe it, or give it alt="" when it is only decoration.', 'messageKey' => 'image-alt.missing', 'parameters' => ['src' => '/b.png'], 'subject' => '/b.png'],
            ],
        ], $this->report()->toArray());
    }

    public function testASeverityIsGraverThanAnother(): void
    {
        self::assertTrue(Severity::Error->isAtLeast(Severity::Error));
        self::assertTrue(Severity::Error->isAtLeast(Severity::Warning));
        self::assertTrue(Severity::Error->isAtLeast(Severity::Info));
        self::assertFalse(Severity::Warning->isAtLeast(Severity::Error));
        self::assertTrue(Severity::Warning->isAtLeast(Severity::Warning));
        self::assertTrue(Severity::Warning->isAtLeast(Severity::Info));
        self::assertFalse(Severity::Info->isAtLeast(Severity::Error));
        self::assertFalse(Severity::Info->isAtLeast(Severity::Warning));
        self::assertTrue(Severity::Info->isAtLeast(Severity::Info));
    }

    public function testAMessageIsTranslatedInTheReportsLocale(): void
    {
        self::assertSame('The page has no title element: results pages show its address instead.', $this->report()->translate(new Message('document-title.missing')));
    }
}
