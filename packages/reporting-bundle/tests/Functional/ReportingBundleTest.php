<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Functional;

use Locale;
use Override;
use Phil\ReportingBundle\Application\Contract\ExportStore;
use Phil\ReportingBundle\Application\Contract\JobStore;
use Phil\ReportingBundle\Application\Exception\AccessDenied;
use Phil\ReportingBundle\Application\Message\RunScheduledReport;
use Phil\ReportingBundle\Application\Periods;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Sources;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryExportStore;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryJobStore;
use Phil\ReportingBundle\Infrastructure\Messenger\ReportQueue;
use Phil\ReportingBundle\PhilReportingBundle;
use Phil\ReportingBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\RuntimeError;

/**
 * A dashboard rendered through the components of a booted kernel, and a scheduled report exported and mailed through
 * Messenger's synchronous transport.
 */
#[CoversClass(PhilReportingBundle::class)]
final class ReportingBundleTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    /** The default locale before the test: the components read it when they are given none. */
    private string $locale = '';

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        $this->locale = Locale::getDefault();
        Locale::setDefault('en');
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ('' !== $this->locale) {
            Locale::setDefault($this->locale);
        }
    }

    public function testTheSourcesOfTheApplication(): void
    {
        $sources = self::getContainer()->get(Sources::class);
        self::assertInstanceOf(Sources::class, $sources);

        self::assertSame(['renewals'], $sources->metricKeys());
        self::assertSame(['signups'], $sources->seriesKeys());
    }

    public function testADashboard(): void
    {
        $html = $this->twig()->createTemplate("{{ component('PhilReporting:Dashboard', {name: 'members'}) }}")->render();

        self::assertStringContainsString('<section class="phil-reporting-dashboard" aria-labelledby="phil-reporting-members-title"', $html);
        self::assertStringContainsString('<h2 id="phil-reporting-members-title">Members</h2>', $html);
        self::assertStringContainsString('From 2026-11-01 to 2026-11-30', $html);
        self::assertStringContainsString('<option value="this_month" selected>This month</option>', $html);
        self::assertStringContainsString('<option value="last_month">Last month</option>', $html);
        self::assertStringContainsString('<button type="submit">Show</button>', $html);
        self::assertStringNotContainsString('data-model', $html);
        self::assertStringContainsString('<h3 class="phil-reporting-metric__title">Renewals</h3>', $html);
        self::assertStringContainsString('<data value="0.6">60%</data>', $html);
        self::assertStringContainsString('data-direction="up"', $html);
        self::assertStringContainsString('Up 20% from the previous period', $html);
        self::assertStringContainsString('<figcaption class="phil-reporting-chart__title">Sign-ups &lt;weekly&gt;</figcaption>', $html);
        self::assertStringContainsString('<span class="phil-reporting-bars__bar" data-share="100"></span>', $html);
        self::assertStringContainsString('<th scope="row">Nov 1</th>', $html);
        self::assertStringContainsString('<svg xmlns="http://www.w3.org/2000/svg"', $html);
        self::assertStringContainsString('<title>Sign-ups drawn</title>', $html);
        self::assertStringContainsString('<summary>Values</summary>', $html);
        self::assertStringContainsString('<caption>Sign-ups listed</caption>', $html);
        self::assertStringNotContainsString(' style=', $html);

        $last = $this->twig()->createTemplate("{{ component('PhilReporting:Dashboard', {name: 'members', period: 'last_month'}) }}")->render();
        self::assertStringContainsString('From 2026-10-01 to 2026-10-31', $last);
        self::assertStringContainsString('<data value="0.5">50%</data>', $last);
    }

    public function testALiveDashboard(): void
    {
        $html = $this->twig()->createTemplate("{{ component('PhilReporting:LiveDashboard', {name: 'members', period: 'last_month'}) }}")->render();

        self::assertStringContainsString('data-controller="live"', $html);
        self::assertStringContainsString('<select id="phil-reporting-members-period" name="period" data-model="period">', $html);
        self::assertStringNotContainsString('<button type="submit">', $html);
        self::assertStringContainsString('From 2026-10-01 to 2026-10-31', $html);
    }

    public function testARestrictedDashboardWithoutSecurity(): void
    {
        try {
            $this->twig()->createTemplate("{{ component('PhilReporting:Dashboard', {name: 'board'}) }}")->render();
            self::fail('Rendered.');
        } catch (RuntimeError $runtimeError) {
            self::assertInstanceOf(AccessDenied::class, $runtimeError->getPrevious());
        }
    }

    public function testAMetricAndAChartOnTheirOwn(): void
    {
        $html = $this->twig()->createTemplate("{{ component('PhilReporting:Metric', {source: 'renewals', period: 'last_month', title: 'Renewed'}) }}{{ component('PhilReporting:Chart', {source: 'signups', render: 'table'}) }}")->render();

        self::assertStringContainsString('<h3 class="phil-reporting-metric__title">Renewed</h3>', $html);
        self::assertStringNotContainsString('phil-reporting-metric__change', $html);
        self::assertStringContainsString('<table class="phil-reporting-table">', $html);
    }

    public function testAScheduledReportIsExportedAndMailed(): void
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new RunScheduledReport('monthly_members'));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Members', $email->getSubject());
        self::assertSame('ada@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('members-20261001-20261031.csv', $email->getAttachments()[0]->getFilename());
        self::assertSame("Name,Since\r\nAda,2026-10-01\r\n\"'=HYPERLINK(\"\"http://example.com\"\")\",2026-10-01\r\n", $email->getAttachments()[0]->getBody());

        $jobs = self::getContainer()->get(JobStore::class);
        self::assertInstanceOf(InMemoryJobStore::class, $jobs);
        self::assertSame([], $jobs->ids());
        $exports = self::getContainer()->get(ExportStore::class);
        self::assertInstanceOf(InMemoryExportStore::class, $exports);
        self::assertSame([], $exports->contents());
    }

    public function testAQueuedReportWithoutDeliveryStaysInTheStore(): void
    {
        $queue = self::getContainer()->get(ReportQueue::class);
        self::assertInstanceOf(ReportQueue::class, $queue);
        $reports = self::getContainer()->get(Reports::class);
        self::assertInstanceOf(Reports::class, $reports);
        $periods = self::getContainer()->get(Periods::class);
        self::assertInstanceOf(Periods::class, $periods);

        $queue->queue(new ReportJob('members', 'json', new ReportRequest($periods->period($periods->preset('yesterday')))));

        $exports = self::getContainer()->get(ExportStore::class);
        self::assertInstanceOf(InMemoryExportStore::class, $exports);
        self::assertSame(["[\n{\"Name\":\"Ada\",\"Since\":\"2026-11-07\"},\n{\"Name\":\"=HYPERLINK(\\\"http://example.com\\\")\",\"Since\":\"2026-11-07\"}\n]\n"], array_values($exports->contents()));
        self::assertSame(['members'], $reports->keys());
    }

    private function twig(): Environment
    {
        $twig = self::getContainer()->get('test.twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}
