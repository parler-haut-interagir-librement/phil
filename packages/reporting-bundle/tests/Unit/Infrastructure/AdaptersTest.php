<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\Exception\PngUnavailable;
use Phil\ReportingBundle\Application\View\ChartDrawing;
use Phil\ReportingBundle\Infrastructure\Console\ExportReportCommand;
use Phil\ReportingBundle\Infrastructure\EasyAdmin\ReportingAdmin;
use Phil\ReportingBundle\Infrastructure\Http\ChartResponses;
use Phil\ReportingBundle\Infrastructure\Http\ReportResponses;
use Phil\ReportingBundle\Tests\Unit\Fixture\RecordingRasterizer;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use Phil\SvgChart\ChartType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ReportResponses::class)]
#[CoversClass(ChartResponses::class)]
#[CoversClass(ExportReportCommand::class)]
#[CoversClass(ReportingAdmin::class)]
final class AdaptersTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-reporting-'.sodium_bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testADownload(): void
    {
        $response = new ReportResponses(Reporting::reports())->download('sales', 'csv', Reporting::request(['rows' => 1]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=sales-20261001-20261031.csv', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));

        ob_start();
        $response->sendContent();
        self::assertSame("Day,Amount,Paid,Note\r\n2026-10-01,1.5,false,'=SUM(A1)\r\n", ob_get_clean());

        $json = new ReportResponses(Reporting::reports())->download('sales', 'json', Reporting::request());
        self::assertSame('application/json', $json->headers->get('Content-Type'));
    }

    public function testADownloadOfNothingFailsBeforeAnythingIsSent(): void
    {
        foreach (['There is no report "nope".' => ['nope', 'csv'], 'There is no report format "pdf".' => ['sales', 'pdf']] as $message => [$report, $format]) {
            try {
                new ReportResponses(Reporting::reports())->download($report, $format, Reporting::request());
                self::fail($message);
            } catch (NotFound $notFound) {
                self::assertSame($message, $notFound->getMessage());
            }
        }
    }

    public function testAChartAsAPngDownload(): void
    {
        $rasterizer = new RecordingRasterizer();
        $responses = new ChartResponses(Reporting::sources(), new ChartDrawing(rasterizer: $rasterizer));
        $period = Reporting::request()->period;

        self::assertTrue($responses->hasPng());
        $response = $responses->png('visits', $period, ChartType::Line);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('PNG 2', $response->getContent());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=visits-20261001-20261031.png', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<title>Visits</title>', $rasterizer->drawn[0][0]);
        self::assertStringContainsString('<path ', $rasterizer->drawn[0][0]);

        $responses->png('visits', $period, title: 'Pages seen');
        self::assertStringContainsString('<title>Pages seen</title>', $rasterizer->drawn[1][0]);
        self::assertStringNotContainsString('<path ', $rasterizer->drawn[1][0]);
    }

    public function testAPngDownloadOfNothingFailsBeforeAnythingIsDrawn(): void
    {
        $rasterizer = new RecordingRasterizer();
        $period = Reporting::request()->period;

        try {
            new ChartResponses(Reporting::sources(), new ChartDrawing(rasterizer: $rasterizer))->png('nope', $period);
            self::fail('Drawn.');
        } catch (NotFound $notFound) {
            self::assertSame('There is no series source "nope".', $notFound->getMessage());
        }

        $without = new ChartResponses(Reporting::sources(), new ChartDrawing());
        self::assertFalse($without->hasPng());

        try {
            $without->png('nope', $period);
            self::fail('Drawn.');
        } catch (PngUnavailable $pngUnavailable) {
            self::assertStringStartsWith('The charts are drawn as SVG only', $pngUnavailable->getMessage());
        }

        self::assertSame([], $rasterizer->drawn);
    }

    public function testTheCommand(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('* sales', $this->display($tester));

        $file = $this->directory.'/sales.csv';
        self::assertSame(Command::SUCCESS, $tester->execute(['report' => 'sales', '--output' => $file]));
        self::assertStringContainsString('3 rows written to', $this->display($tester));
        self::assertSame("Day,Amount,Paid,Note\r\n2026-10-01,1.5,false,'=SUM(A1)\r\n", explode('2026-10-01,3,', (string) file_get_contents($file))[0]);

        $json = $this->directory.'/sales.json';
        self::assertSame(Command::SUCCESS, $tester->execute(['report' => 'sales', '--format' => 'json', '--period' => 'this_month', '--output' => $json]));
        self::assertStringStartsWith("[\n{\"Day\":\"2026-11-01\"", (string) file_get_contents($json));

        self::assertSame(Command::FAILURE, $tester->execute(['report' => 'sales', '--output' => $file]));
        self::assertStringContainsString('cannot be created', $this->display($tester));

        /** @var array<string, array<string, string>> $failures */
        $failures = [
            'There is no report "nope".' => ['report' => 'nope', '--output' => $this->directory.'/a'],
            'There is no report format "pdf".' => ['report' => 'sales', '--format' => 'pdf', '--output' => $this->directory.'/b'],
            'There is no period "soon".' => ['report' => 'sales', '--period' => 'soon', '--output' => $this->directory.'/c'],
        ];

        $statuses = [];

        foreach ($failures as $message => $input) {
            $statuses[] = $tester->execute($input);
            self::assertStringContainsString($message, $this->display($tester));
        }

        // Command::FAILURE, which PHPStan narrows to nothing after the assertions above.
        self::assertSame([1, 1, 1], $statuses);

        self::assertFileDoesNotExist($this->directory.'/a');
        self::assertFileDoesNotExist($this->directory.'/b');
        self::assertFileDoesNotExist($this->directory.'/c');
    }

    public function testTheEasyAdminLink(): void
    {
        $item = ReportingAdmin::menuItem('Sales', 'sales', 'admin_reports')->getAsDto();

        self::assertSame('@PhilReporting/easyadmin/dashboard.html.twig', ReportingAdmin::TEMPLATE);
        self::assertSame(['Sales', 'fa fa-chart-column', 'admin_reports', ['dashboard' => 'sales']], [$item->getLabel(), $item->getIcon(), $item->getRouteName(), $item->getRouteParameters()]);
        self::assertNull(ReportingAdmin::menuItem('Sales', 'sales', 'admin_reports', null)->getAsDto()->getIcon());
    }

    private function tester(): CommandTester
    {
        $application = new Application();
        $application->addCommand(new ExportReportCommand(Reporting::reports(), Reporting::periods()));

        return new CommandTester($application->find('phil:reporting:export'));
    }

    /**
     * What the command wrote, on one line: the blocks of SymfonyStyle wrap at the width of the console, which varies.
     */
    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
