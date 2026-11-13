<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Functional;

use DateTimeImmutable;
use Override;
use Phil\StatusPageBundle\Application\Message\PushStatusSnapshot;
use Phil\StatusPageBundle\Infrastructure\Console\PushCommand;
use Phil\StatusPageBundle\Infrastructure\Http\StatusPageController;
use Phil\StatusPageBundle\Infrastructure\Messenger\PushStatusSnapshotHandler;
use Phil\StatusPageBundle\Infrastructure\Scheduler\StatusPageSchedule;
use Phil\StatusPageBundle\Infrastructure\Twig\KitComponents;
use Phil\StatusPageBundle\Infrastructure\Twig\StatusPageExtension;
use Phil\StatusPageBundle\Infrastructure\Twig\TwigPageRenderer;
use Phil\StatusPageBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

#[CoversClass(StatusPageController::class)]
#[CoversClass(TwigPageRenderer::class)]
#[CoversClass(StatusPageExtension::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(PushCommand::class)]
#[CoversClass(PushStatusSnapshotHandler::class)]
#[CoversClass(StatusPageSchedule::class)]
final class StatusPageTest extends TestCase
{
    private ?TestKernel $kernel = null;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->kernel instanceof TestKernel) {
            new Filesystem()->remove($this->kernel->snapshotDirectory());
            $this->kernel->shutdown();
        }
    }

    public function testThePageShowsComponentsIncidentsMaintenancesAndHistoryWithoutScript(): void
    {
        $response = $this->kernel()->handle(Request::create('/status'));
        $html = (string) $response->getContent();
        $page = new Crawler($html);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('max-age=60, public', $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertNull($response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame([], $response->headers->getCookies());
        self::assertStringNotContainsString('<script', $html);

        self::assertSame('Example status', $page->filter('title')->text());
        self::assertSame('Example status', $page->filter('h1')->text());
        self::assertSame('Partial outage Some systems are partly down.', $page->filter('.phil-status-page__overall')->text());
        self::assertSame('Partial outage', $page->filter('.phil-status-page__overall [data-slot="badge"]')->text());
        self::assertSame('destructive', $page->filter('.phil-status-page__overall [data-slot="badge"]')->attr('data-variant'));

        $incident = $page->filter('#incident-api-errors');
        self::assertSame('li', $incident->nodeName());
        self::assertStringContainsString('isCard', (string) $incident->attr('class'));
        self::assertSame('Errors <b>on</b> the API', $incident->filter('h3')->text());
        self::assertStringContainsString('Errors &lt;b&gt;on&lt;/b&gt; the API', $html);
        self::assertStringContainsString('Affects: API', $incident->text());
        self::assertStringContainsString('Looking.<br>', $incident->html());
        self::assertSame('2026-11-12T11:00:00+00:00', $incident->filter('time')->attr('datetime'));
        self::assertSame('2026-11-12 11:00 UTC', $incident->filter('time')->text());

        self::assertStringContainsString('Upcoming', $page->filter('#maintenance-upgrade')->text());
        self::assertStringContainsString('Affects: Website', $page->filter('#maintenance-upgrade')->text());

        self::assertSame('Operational', $page->filter('#component-website [data-slot="badge"]')->text());
        self::assertSame('outline', $page->filter('#component-website [data-slot="badge"]')->attr('data-variant'));
        self::assertStringContainsString('The public pages', $page->filter('#component-website')->text());
        self::assertSame('Degraded', $page->filter('#component-sign-in [data-slot="badge"]')->text());
        self::assertSame('secondary', $page->filter('#component-sign-in [data-slot="badge"]')->attr('data-variant'));
        self::assertSame('Platform', $page->filter('#phil-status-components ~ h3, section[aria-labelledby="phil-status-components"] h3')->text());

        self::assertCount(1, $page->filter('section[aria-labelledby="phil-status-history"] #incident-past'));
        self::assertSame('/status.atom', $page->filter('footer a[type="application/atom+xml"]')->attr('href'));
        self::assertSame('/status.json', $page->filter('footer a[type="application/json"]')->attr('href'));
    }

    public function testTheChecksOfTheMonitoringBundleGiveTheStatesWhenItIsThere(): void
    {
        $page = new Crawler((string) $this->kernel(monitoring: true)->handle(Request::create('/status'))->getContent());

        self::assertSame('Major outage', $page->filter('#component-website [data-slot="badge"]')->text());
        self::assertSame('Degraded', $page->filter('#component-sign-in [data-slot="badge"]')->text());
    }

    public function testTheJsonAndTheFeedCarryAbsoluteAddressesAndMayBeReadByOtherSites(): void
    {
        $json = $this->kernel()->handle(Request::create('https://example.org/status.json'));
        self::assertSame('application/json', $json->headers->get('Content-Type'));
        self::assertSame('*', $json->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('max-age=60, public', $json->headers->get('Cache-Control'));
        $data = json_decode((string) $json->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('partial_outage', $data['status']);
        self::assertSame(['title' => 'Example status', 'url' => 'https://example.org/status', 'generated_at' => '2026-11-12T12:00:00+00:00'], $data['page']);

        $atom = $this->kernel()->handle(Request::create('https://example.org/status.atom'));
        self::assertSame('application/atom+xml; charset=UTF-8', $atom->headers->get('Content-Type'));
        self::assertSame('*', $atom->headers->get('Access-Control-Allow-Origin'));
        self::assertStringContainsString('<link rel="self" href="https://example.org/status.atom" type="application/atom+xml"/>', (string) $atom->getContent());
        self::assertStringContainsString('<id>https://example.org/status#incident-api-errors</id>', (string) $atom->getContent());
    }

    public function testTheCommandPushesTheSnapshot(): void
    {
        $kernel = $this->kernel();
        $tester = new CommandTester(new Application($kernel)->find(PushCommand::NAME));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Published to mirror.', $tester->getDisplay());

        $html = (string) file_get_contents($kernel->snapshotDirectory().'/index.html');
        self::assertStringContainsString('<link rel="alternate" type="application/atom+xml" href="feed.atom">', $html);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $html);
        self::assertStringContainsString('<style>', $html);
        self::assertStringContainsString('<a href="status.json" type="application/json">', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringContainsString('"url": "https://status.example.org/"', (string) file_get_contents($kernel->snapshotDirectory().'/status.json'));
        self::assertStringContainsString('<id>https://status.example.org/#incident-api-errors</id>', (string) file_get_contents($kernel->snapshotDirectory().'/feed.atom'));
    }

    public function testTheCommandRefusesAnUnknownTarget(): void
    {
        $tester = new CommandTester(new Application($this->kernel())->find(PushCommand::NAME));

        self::assertSame(2, $tester->execute(['target' => 'elsewhere']));
        self::assertStringContainsString('No snapshot target is named "elsewhere"', $tester->getDisplay());
    }

    public function testTheCommandFailsWhenATargetDoesNotTakeTheSnapshot(): void
    {
        $kernel = $this->kernel();
        new Filesystem()->dumpFile($kernel->snapshotDirectory(), 'a file where the directory should be');
        $tester = new CommandTester(new Application($kernel)->find(PushCommand::NAME));

        self::assertSame(1, $tester->execute(['target' => 'mirror']));
        self::assertStringContainsString('Not published to mirror: IOException.', $tester->getDisplay());
    }

    public function testAMessagePushesTheSnapshotAndTheScheduleSendsOne(): void
    {
        $kernel = $this->kernel();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $bus = $container->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new PushStatusSnapshot('mirror'));
        self::assertFileExists($kernel->snapshotDirectory().'/feed.atom');

        $schedule = $container->get(StatusPageSchedule::class);
        self::assertInstanceOf(StatusPageSchedule::class, $schedule);
        $messages = $schedule->getSchedule()->getRecurringMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(RecurringMessage::class, $messages[0]);
        self::assertSame('every 5 minutes', (string) $messages[0]->getTrigger());
        self::assertEquals([new PushStatusSnapshot()], iterator_to_array($messages[0]->getProvider()->getMessages(new MessageContext('n', 'i', $messages[0]->getTrigger(), new DateTimeImmutable()))));
    }

    private function kernel(bool $monitoring = false): TestKernel
    {
        $this->kernel?->shutdown();
        $this->kernel = new TestKernel('test', true, $monitoring);
        $this->kernel->boot();

        return $this->kernel;
    }
}
