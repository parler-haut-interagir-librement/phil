<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Infrastructure\Console\ExportReportCommand;
use Phil\ReportingBundle\Infrastructure\EasyAdmin\ReportingAdmin;
use Phil\ReportingBundle\Infrastructure\Http\ReportResponses;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ReportResponses::class)]
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

    public function testTheCommand(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('* sales', $tester->getDisplay());

        $file = $this->directory.'/sales.csv';
        self::assertSame(Command::SUCCESS, $tester->execute(['report' => 'sales', '--output' => $file]));
        self::assertStringContainsString('3 rows written to', $tester->getDisplay());
        self::assertSame("Day,Amount,Paid,Note\r\n2026-10-01,1.5,false,'=SUM(A1)\r\n", explode('2026-10-01,3,', (string) file_get_contents($file))[0]);

        $json = $this->directory.'/sales.json';
        self::assertSame(Command::SUCCESS, $tester->execute(['report' => 'sales', '--format' => 'json', '--period' => 'this_month', '--output' => $json]));
        self::assertStringStartsWith("[\n{\"Day\":\"2026-11-01\"", (string) file_get_contents($json));

        self::assertSame(Command::FAILURE, $tester->execute(['report' => 'sales', '--output' => $file]));
        self::assertStringContainsString('cannot be created', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));

        /** @var array<string, array<string, string>> $failures */
        $failures = [
            'There is no report "nope".' => ['report' => 'nope', '--output' => $this->directory.'/a'],
            'There is no report format "pdf".' => ['report' => 'sales', '--format' => 'pdf', '--output' => $this->directory.'/b'],
            'There is no period "soon".' => ['report' => 'sales', '--period' => 'soon', '--output' => $this->directory.'/c'],
        ];

        $statuses = [];

        foreach ($failures as $message => $input) {
            $statuses[] = $tester->execute($input);
            self::assertStringContainsString($message, $tester->getDisplay());
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
}
