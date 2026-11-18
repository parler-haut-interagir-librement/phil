<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Integration;

use Override;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentModeBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The bundle in an application whose incidents are kept on a real server, read from the environment: an incident
 * started from the administration, the checkpoint that refuses while it is in force, its extension, the journal,
 * and the sweep that records its end once — the moments in UTC whatever the server's time zone.
 */
abstract class ServerTestCase extends TestCase
{
    private TestKernel $kernel;

    private KernelBrowser $client;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $this->kernel = new TestKernel('test', false, $url);
        $this->kernel->boot();
        $this->kernel->resetDatabase();

        $client = $this->kernel->getContainer()->get('test.client');
        $this->assertInstanceOf(KernelBrowser::class, $client);
        $this->client = $client;
        $this->client->disableReboot();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->kernel)) {
            $this->kernel->shutdown();
        }
    }

    public function testAnIncidentIsRunFromTheAdministrationAndLiftedAtItsEnd(): void
    {
        $admin = ['HTTP_X_MEMBER' => 'admin-7', 'HTTP_X_ROLES' => 'ROLE_INCIDENTS', 'HTTP_ORIGIN' => 'http://localhost'];
        $crawler = $this->client->request('GET', '/admin/incidents/', server: $admin);
        $token = (string) $crawler->filter('input[name=_token]')->first()->attr('value');

        $this->client->request('POST', '/admin/incidents/start', ['_token' => $token, 'preset' => 'suspend-signups', 'amount' => 2, 'unit' => 'hours', 'reason' => 'Spam wave'], server: $admin);
        self::assertSame(303, $this->client->getResponse()->getStatusCode());

        [$activation] = $this->incidents()->inForceNow();
        self::assertSame('2026-11-17T17:00:00+00:00', $activation->endsAt->format(\DATE_ATOM));

        $this->client->request('POST', '/sign-up');
        self::assertSame(503, $this->client->getResponse()->getStatusCode());
        self::assertSame('7200', $this->client->getResponse()->headers->get('Retry-After'));

        $this->client->request('POST', '/admin/incidents/'.$activation->id.'/extend', ['_token' => $token, 'amount' => 30, 'unit' => 'minutes'], server: $admin);
        self::assertSame('2026-11-17T17:30:00+00:00', $this->incidents()->find($activation->id)?->endsAt->format(\DATE_ATOM));

        $clock = $this->kernel->getContainer()->get('clock');
        $this->assertInstanceOf(MockClock::class, $clock);
        $clock->modify('+1 minute');
        $this->incidents()->start('suspend-invitations', Duration::ofHours(1), Actor::of('admin-7'));
        $clock->modify('+3 hours');
        $this->client->request('POST', '/sign-up');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertSame(2, $this->incidents()->liftExpired());
        self::assertSame(0, $this->incidents()->liftExpired());
        $lifted = $this->incidents()->find($activation->id);
        self::assertSame(LiftReason::Expired, $lifted?->liftReason);
        self::assertSame('2026-11-17T17:30:00+00:00', $lifted->liftedAt?->format(\DATE_ATOM));

        $crawler = $this->client->request('GET', '/admin/incidents/', server: $admin);
        self::assertSame(['Suspend invitations', 'Suspend sign-ups'], $crawler->filter('tbody tr td:first-child')->each(static fn (Crawler $node): string => $node->text()));
        self::assertSame('At its end', $crawler->filter('tbody tr td')->eq(3)->text());
    }

    private function incidents(): IncidentMode
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        $this->assertInstanceOf(ContainerInterface::class, $container);
        $incidents = $container->get(IncidentMode::class);
        $this->assertInstanceOf(IncidentMode::class, $incidents);

        return $incidents;
    }
}
