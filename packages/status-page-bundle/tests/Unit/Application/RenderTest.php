<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Dom\XMLDocument;
use Dom\XPath;
use Phil\StatusPageBundle\Application\Render\AtomFeed;
use Phil\StatusPageBundle\Application\Render\JsonDocument;
use Phil\StatusPageBundle\Application\Render\PageLinks;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\Status\IncidentState;
use Phil\StatusPageBundle\Domain\ValueObject\Component;
use Phil\StatusPageBundle\Domain\ValueObject\ComponentState;
use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\IncidentUpdate;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;
use Phil\StatusPageBundle\Domain\ValueObject\StatusPage;
use Phil\StatusPageBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonDocument::class)]
#[CoversClass(AtomFeed::class)]
#[CoversClass(PageLinks::class)]
final class RenderTest extends TestCase
{
    public function testTheJsonDescribesTheWholePageInUtc(): void
    {
        $json = new JsonDocument()->render($this->page(), $this->links());

        self::assertStringEndsWith("}\n", $json);
        self::assertStringContainsString("\n    \"version\": \"1\"", $json);
        self::assertStringContainsString('"url": "https://status.example.org/"', $json);
        self::assertStringContainsString('"title": "Statut — exemple"', $json);
        self::assertSame([
            'version' => '1',
            'page' => ['title' => 'Statut — exemple', 'url' => 'https://status.example.org/', 'generated_at' => '2026-11-12T12:00:00+00:00'],
            'status' => 'partial_outage',
            'components' => [
                ['id' => 'website', 'name' => 'Website', 'description' => 'The public pages', 'group' => null, 'status' => 'maintenance'],
                ['id' => 'api', 'name' => 'API', 'description' => null, 'group' => 'Platform', 'status' => 'partial_outage'],
            ],
            'incidents' => [[
                'id' => 'api-errors',
                'title' => 'Errors <on> the API',
                'impact' => 'partial_outage',
                'state' => 'identified',
                'components' => ['api'],
                'started_at' => '2026-11-12T11:00:00+00:00',
                'updated_at' => '2026-11-12T11:30:00+00:00',
                'resolved_at' => null,
                'updates' => [
                    ['at' => '2026-11-12T11:30:00+00:00', 'state' => 'identified', 'message' => 'A bad deployment & a fix.'],
                    ['at' => '2026-11-12T11:00:00+00:00', 'state' => 'investigating', 'message' => 'Looking.'],
                ],
            ]],
            'maintenances' => [
                ['id' => 'now', 'title' => 'Maintenance now', 'description' => 'Planned work.', 'state' => 'in_progress', 'components' => ['website'], 'starts_at' => '2026-11-12T11:00:00+00:00', 'ends_at' => '2026-11-12T13:00:00+00:00'],
                ['id' => 'later', 'title' => 'Maintenance later', 'description' => 'Planned work.', 'state' => 'upcoming', 'components' => ['payments'], 'starts_at' => '2026-11-13T12:00:00+00:00', 'ends_at' => '2026-11-13T13:00:00+00:00'],
            ],
            'history' => [[
                'id' => 'past',
                'title' => 'Incident past',
                'impact' => 'major_outage',
                'state' => 'resolved',
                'components' => ['website'],
                'started_at' => '2026-11-10T12:00:00+00:00',
                'updated_at' => '2026-11-10T13:00:00+00:00',
                'resolved_at' => '2026-11-10T13:00:00+00:00',
                'updates' => [
                    ['at' => '2026-11-10T13:00:00+00:00', 'state' => 'resolved', 'message' => 'It works again.'],
                    ['at' => '2026-11-10T12:00:00+00:00', 'state' => 'investigating', 'message' => 'We are looking into it.'],
                ],
            ]],
        ], json_decode($json, true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testTheFeedHasAnEntryPerIncidentAndMaintenance(): void
    {
        $xml = new AtomFeed()->render($this->page(), $this->links());

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>'."\n".'<feed xmlns="http://www.w3.org/2005/Atom">'."\n  <id>", $xml);
        self::assertSame(['https://status.example.org/'], $this->xpath($xml, '/a:feed/a:id'));
        self::assertSame(['Statut — exemple'], $this->xpath($xml, '/a:feed/a:title'));
        self::assertSame(['Statut — exemple'], $this->xpath($xml, '/a:feed/a:author/a:name'));
        // The newest of the updates and the starts of the maintenances.
        self::assertSame(['2026-11-13T12:00:00+00:00'], $this->xpath($xml, '/a:feed/a:updated'));
        self::assertSame(['self', 'alternate', 'alternate'], $this->xpath($xml, '/a:feed/a:link/@rel'));
        self::assertSame(['https://status.example.org/feed.atom', 'https://status.example.org/', 'https://status.example.org/status.json'], $this->xpath($xml, '/a:feed/a:link/@href'));
        self::assertSame(['application/atom+xml', 'text/html', 'application/json'], $this->xpath($xml, '/a:feed/a:link/@type'));

        self::assertSame([
            'https://status.example.org/#incident-api-errors',
            'https://status.example.org/#incident-past',
            'https://status.example.org/#maintenance-now',
            'https://status.example.org/#maintenance-later',
        ], $this->xpath($xml, '/a:feed/a:entry/a:id'));
        self::assertSame(['Errors <on> the API', 'Incident past', 'Maintenance now', 'Maintenance later'], $this->xpath($xml, '/a:feed/a:entry/a:title'));
        self::assertSame(['2026-11-12T11:00:00+00:00', '2026-11-10T12:00:00+00:00'], $this->xpath($xml, '/a:feed/a:entry/a:published'));
        self::assertSame(['2026-11-12T11:30:00+00:00', '2026-11-10T13:00:00+00:00', '2026-11-12T11:00:00+00:00', '2026-11-13T12:00:00+00:00'], $this->xpath($xml, '/a:feed/a:entry/a:updated'));
        self::assertSame(['identified', 'resolved', 'maintenance', 'maintenance'], $this->xpath($xml, '/a:feed/a:entry/a:category/@term'));
        self::assertSame($this->xpath($xml, '/a:feed/a:entry/a:id'), $this->xpath($xml, '/a:feed/a:entry/a:link/@href'));
        self::assertSame(['alternate', 'alternate', 'alternate', 'alternate'], $this->xpath($xml, '/a:feed/a:entry/a:link/@rel'));
        self::assertSame(['text/html', 'text/html', 'text/html', 'text/html'], $this->xpath($xml, '/a:feed/a:entry/a:link/@type'));
        self::assertSame(['text', 'text', 'text', 'text'], $this->xpath($xml, '/a:feed/a:entry/a:content/@type'));
        self::assertSame([
            "2026-11-12T11:30:00+00:00 — identified\nA bad deployment & a fix.\n\n2026-11-12T11:00:00+00:00 — investigating\nLooking.",
            "2026-11-10T13:00:00+00:00 — resolved\nIt works again.\n\n2026-11-10T12:00:00+00:00 — investigating\nWe are looking into it.",
            "2026-11-12T11:00:00+00:00 — 2026-11-12T13:00:00+00:00\nWebsite\n\nPlanned work.",
            "2026-11-13T12:00:00+00:00 — 2026-11-13T13:00:00+00:00\npayments\n\nPlanned work.",
        ], $this->xpath($xml, '/a:feed/a:entry/a:content'));
        self::assertStringContainsString('<title>Errors &lt;on&gt; the API</title>', $xml);
    }

    public function testAnEmptyFeedIsDatedByThePage(): void
    {
        $page = new StatusPage('Status', new DateTimeImmutable('2026-11-12T08:00:00-04:00'), [], [], [], [], []);
        $xml = new AtomFeed()->render($page, $this->links());

        self::assertSame(['2026-11-12T12:00:00+00:00'], $this->xpath($xml, '/a:feed/a:updated'));
        self::assertSame([], $this->xpath($xml, '/a:feed/a:entry'));
    }

    public function testTheFeedIsDatedByItsLatestEntryWhateverTheirOrder(): void
    {
        $older = Fixtures::incident('older', ComponentStatus::Degraded, [], '-1 hour');
        $newerFirst = new StatusPage('Status', Fixtures::at('+5 days'), [], [Fixtures::incident('newer', ComponentStatus::Degraded, [], '-10 minutes'), $older], [], [], []);

        self::assertSame(['2026-11-12T11:50:00+00:00'], $this->xpath(new AtomFeed()->render($newerFirst, $this->links()), '/a:feed/a:updated'));
    }

    public function testAMaintenanceWithoutDescriptionHasItsWindowAndComponentsOnly(): void
    {
        $maintenance = new ScheduledMaintenance('m', 'M', ['a', 'b'], Fixtures::now(), Fixtures::at('+1 hour'));
        $page = new StatusPage('Status', Fixtures::now(), [], [], [], [$maintenance], []);

        self::assertSame(["2026-11-12T12:00:00+00:00 — 2026-11-12T13:00:00+00:00\na, b"], $this->xpath(new AtomFeed()->render($page, $this->links()), '/a:feed/a:entry/a:content'));
    }

    /**
     * @return list<string>
     */
    private function xpath(string $xml, string $query): array
    {
        $xpath = new XPath(XMLDocument::createFromString($xml));
        $xpath->registerNamespace('a', 'http://www.w3.org/2005/Atom');

        $values = [];

        foreach ($xpath->query($query) as $node) {
            $values[] = (string) $node->textContent;
        }

        return $values;
    }

    private function links(): PageLinks
    {
        return new PageLinks('https://status.example.org/', 'https://status.example.org/status.json', 'https://status.example.org/feed.atom');
    }

    public function testTheJsonListsEveryMaintenanceInProgressThenEveryUpcomingOne(): void
    {
        $page = new StatusPage(
            'Example',
            Fixtures::now(),
            [],
            [],
            [Fixtures::maintenance('now-1', [], '-2 hours', '+1 hour'), Fixtures::maintenance('now-2', [], '-1 hour', '+1 hour')],
            [Fixtures::maintenance('later-1', [], '+1 day', '+2 days'), Fixtures::maintenance('later-2', [], '+3 days', '+4 days')],
            [],
        );

        $document = json_decode(new JsonDocument()->render($page, $this->links()), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($document);
        self::assertIsArray($document['maintenances']);
        self::assertSame(['now-1', 'now-2', 'later-1', 'later-2'], array_column($document['maintenances'], 'id'));
        self::assertSame(['in_progress', 'in_progress', 'upcoming', 'upcoming'], array_column($document['maintenances'], 'state'));
    }

    private function page(): StatusPage
    {
        $api = new Incident('api-errors', 'Errors <on> the API', ComponentStatus::PartialOutage, ['api'], [
            new IncidentUpdate(new DateTimeImmutable('2026-11-12T07:00:00-04:00'), IncidentState::Investigating, 'Looking.'),
            new IncidentUpdate(Fixtures::at('-30 minutes'), IncidentState::Identified, 'A bad deployment & a fix.'),
        ]);

        return new StatusPage(
            'Statut — exemple',
            Fixtures::now(),
            [
                new ComponentState(new Component('website', 'Website', 'The public pages'), ComponentStatus::Maintenance),
                new ComponentState(new Component('api', 'API', null, 'Platform'), ComponentStatus::PartialOutage),
            ],
            [$api],
            [Fixtures::maintenance('now', ['website'], '-1 hour', '+1 hour')],
            [Fixtures::maintenance('later', ['payments'], '+1 day', '+1 day +1 hour')],
            [Fixtures::incident('past', ComponentStatus::MajorOutage, ['website'], '-2 days', '-2 days +1 hour')],
        );
    }
}
