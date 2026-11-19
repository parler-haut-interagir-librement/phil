<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\Report;
use Phil\DocumentImportBundle\Infrastructure\Twig\ImportReportExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The report of an import as an HTML fragment: one sentence per kind under the configured title, escaped, nothing at
 * all when nothing was left out.
 */
#[CoversClass(ImportReportExtension::class)]
final class ReportTemplateTest extends TestCase
{
    public function testTheReportIsRenderedUnderTheConfiguredTitle(): void
    {
        $report = new Report();
        $report->add(Omitted::Style, '<b>Note</b>');
        $report->add(Omitted::Style, '<b>Note</b>');
        $report->add(Omitted::Comment);

        self::assertSame(
            '<section class="document-import-report" role="status" aria-labelledby="document-import-report-title">'
            .'<h2 id="document-import-report-title">Left &lt;out&gt;</h2><ul>'
            .'<li>'.htmlspecialchars($report->summary()[0], \ENT_QUOTES).'</li>'
            .'<li>'.htmlspecialchars($report->summary()[1], \ENT_QUOTES).'</li>'
            .'</ul></section>',
            $this->render(['report' => $report]),
        );
        self::assertStringContainsString('&lt;b&gt;Note&lt;/b&gt; (2)', $this->render(['report' => $report]));
    }

    public function testTheTitleLevelAndIdentifierCanBeGiven(): void
    {
        $report = new Report();
        $report->add(Omitted::Comment);

        self::assertStringStartsWith('<section class="document-import-report" role="status" aria-labelledby="chapter-3"><h4 id="chapter-3">Skipped</h4>', $this->render(['report' => $report, 'title' => 'Skipped', 'level' => 4, 'id' => 'chapter-3']));

        foreach ([[0, 'h1'], [9, 'h6'], ['3', 'h3'], [2.7, 'h2']] as [$level, $tag]) {
            self::assertStringContainsString('<'.$tag.' id=', $this->render(['report' => $report, 'level' => $level]));
        }
    }

    public function testNothingIsRenderedWhenNothingWasLeftOut(): void
    {
        self::assertSame('', $this->render(['report' => new Report()]));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(array $context): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/templates', 'PhilDocumentImport');

        $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
        $twig->addExtension(new ImportReportExtension('Left <out>'));

        return (string) preg_replace('/>\s+</', '><', trim($twig->render('@PhilDocumentImport/report.html.twig', $context)));
    }
}
