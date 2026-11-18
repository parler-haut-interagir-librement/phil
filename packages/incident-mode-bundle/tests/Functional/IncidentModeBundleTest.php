<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Functional;

use Override;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\Messenger\LiftIncident;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Infrastructure\Http\AdminRequest;
use Phil\IncidentModeBundle\Infrastructure\Http\CheckpointListener;
use Phil\IncidentModeBundle\Infrastructure\Http\IncidentAdminController;
use Phil\IncidentModeBundle\Infrastructure\Http\SuspensionPage;
use Phil\IncidentModeBundle\Infrastructure\Scheduler\IncidentSchedule;
use Phil\IncidentModeBundle\Infrastructure\Translation\IncidentWording;
use Phil\IncidentModeBundle\Infrastructure\Twig\IncidentModeExtension;
use Phil\IncidentModeBundle\PhilIncidentModeBundle;
use Phil\IncidentModeBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The bundle in an application: the administration, the checkpoints of a controller, a route, a code path and a form,
 * the public pages and the banner, the delayed message, the schedule and the commands' services, with the PHIL UX kit
 * or the Shadcn kit.
 */
#[CoversClass(PhilIncidentModeBundle::class)]
#[CoversClass(IncidentAdminController::class)]
#[CoversClass(AdminRequest::class)]
#[CoversClass(CheckpointListener::class)]
#[CoversClass(SuspensionPage::class)]
#[CoversClass(IncidentModeExtension::class)]
#[CoversClass(IncidentWording::class)]
final class IncidentModeBundleTest extends WebTestCase
{
    private const array ADMIN = ['HTTP_X_MEMBER' => 'admin-7', 'HTTP_X_ROLES' => 'ROLE_INCIDENTS', 'HTTP_ORIGIN' => 'http://localhost'];

    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): TestKernel
    {
        $environment = $options['environment'] ?? 'test';

        return new TestKernel(\is_string($environment) ? $environment : 'test', false);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $kernel = $this->client->getKernel();
        $this->assertInstanceOf(TestKernel::class, $kernel);
        $kernel->resetDatabase();
    }

    public function testTheAdministrationOffersThePresetsAndAnEmptyJournal(): void
    {
        $crawler = $this->client->request('GET', '/admin/incidents/', server: self::ADMIN);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSame('Incident mode', $crawler->filter('h1')->text());
        self::assertSame(['Suspend sign-ups', 'Suspend invitations', 'Suspend new contributions', 'Read only', 'Suspend new listings', 'Pause the messages'], $crawler->filter('form.phil-incident-preset h3')->each(static fn (Crawler $node): string => $node->text()));
        self::assertStringContainsString('Proposed: 2 hours. Longest: 7 days.', $crawler->filter('form.phil-incident-preset')->first()->text());
        self::assertStringContainsString('Proposed: 30 minutes. Longest: 3 hours.', $crawler->filter('form.phil-incident-preset')->last()->text());
        self::assertSame('4', $crawler->filter('#phil-incident-start-suspend-listings-amount')->attr('value'));
        self::assertSame('hours', $crawler->filter('#phil-incident-start-suspend-listings-unit option[selected]')->attr('value'));
        self::assertSame('minutes', $crawler->filter('#phil-incident-start-suspend-messages-unit option[selected]')->attr('value'));
        self::assertStringContainsString('No incident is in force.', $crawler->text());
        self::assertStringContainsString('No incident yet.', $crawler->text());
        self::assertSame('isDanger', $crawler->filter('form.phil-incident-preset button[type=submit]')->first()->attr('class'));
    }

    public function testTheAdministrationIsForTheRoleOnly(): void
    {
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/admin/incidents/', server: ['HTTP_X_MEMBER' => 'admin-7', 'HTTP_X_ROLES' => 'ROLE_USER']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/incidents/', server: ['HTTP_X_ROLES' => 'ROLE_INCIDENTS']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/incidents/start', ['_token' => $this->token(), 'preset' => 'suspend-signups', 'amount' => 2, 'unit' => 'hours'], server: ['HTTP_X_MEMBER' => 'admin-7', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->incidents()->journal());
    }

    public function testAFormWithoutItsTokenOrFromAnotherSiteIsRefused(): void
    {
        $this->client->request('POST', '/admin/incidents/start', ['_token' => 'forged', 'preset' => 'suspend-signups', 'amount' => 2, 'unit' => 'hours'], server: self::ADMIN);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/admin/incidents/start', ['_token' => $this->token(), 'preset' => 'suspend-signups', 'amount' => 2, 'unit' => 'hours'], server: [...self::ADMIN, 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(400);
        self::assertSame([], $this->incidents()->journal());
    }

    public function testAnIncidentIsStartedExtendedAndLiftedFromTheAdministration(): void
    {
        $this->client->request('POST', '/admin/incidents/start', ['_token' => $this->token(), 'preset' => 'suspend-signups', 'amount' => 3, 'unit' => 'hours', 'reason' => '  Spam wave  '], server: self::ADMIN);
        self::assertResponseRedirects('/admin/incidents/', 303);

        [$activation] = $this->incidents()->inForceNow();
        self::assertSame('suspend-signups', $activation->preset);
        self::assertSame('admin-7', $activation->startedBy?->id);
        self::assertSame('Spam wave', $activation->reason);
        self::assertSame('2026-11-17T18:00:00+00:00', $activation->endsAt->format(\DATE_ATOM));

        $crawler = $this->client->request('GET', '/admin/incidents/', server: self::ADMIN);
        $article = $crawler->filter('article.phil-incident');
        self::assertSame('Suspend sign-ups', $article->filter('h3')->text());
        self::assertSame(['Sign-ups'], $article->filter('[data-slot=badge]')->each(static fn (Crawler $node): string => $node->text()));
        self::assertStringContainsString('Until November 17, 2026 at 1:00 PM (Eastern Standard Time)', self::plain($article->text()));
        self::assertStringContainsString('Can last up to 165 hours longer.', $article->text());
        self::assertStringContainsString('Spam wave', $article->text());

        $this->client->request('POST', '/admin/incidents/'.$activation->id.'/extend', ['_token' => $this->token(), 'amount' => 1, 'unit' => 'days'], server: self::ADMIN);
        self::assertResponseRedirects('/admin/incidents/', 303);
        self::assertSame('2026-11-18T18:00:00+00:00', $this->incidents()->find($activation->id)?->endsAt->format(\DATE_ATOM));

        $this->client->request('POST', '/admin/incidents/'.$activation->id.'/lift', ['_token' => $this->token()], server: self::ADMIN);
        self::assertResponseRedirects('/admin/incidents/', 303);
        $lifted = $this->incidents()->find($activation->id);
        self::assertSame(LiftReason::Manual, $lifted?->liftReason);
        self::assertSame('admin-7', $lifted->liftedBy?->id);

        $crawler = $this->client->request('GET', '/admin/incidents/', server: self::ADMIN);
        $row = $crawler->filter('tbody tr')->first();
        self::assertSame(['Suspend sign-ups', 'November 17, 2026 at 10:00 AM (Eastern Standard Time)', 'November 17, 2026 at 10:00 AM (Eastern Standard Time)', 'Lifted by hand', 'admin-7', 'admin-7', 'Spam wave'], $row->filter('td')->each(static fn (Crawler $node): string => self::plain($node->text())));
        self::assertSame('2026-11-17T15:00:00+00:00', $row->filter('time')->first()->attr('datetime'));
    }

    public function testWhatTheAdministrationRefusesIsShownAgainWithTheReason(): void
    {
        $refusals = [
            'error.unknown_preset' => [['preset' => 'nothing', 'amount' => 1, 'unit' => 'hours'], 'No preset has that name.'],
            'error.invalid_duration' => [['preset' => 'suspend-signups', 'amount' => 0, 'unit' => 'hours'], 'The duration is from one minute to ninety days.'],
            'error.unknown_unit' => [['preset' => 'suspend-signups', 'amount' => 2, 'unit' => 'weeks'], 'The duration is from one minute to ninety days.'],
            'error.too_long' => [['preset' => 'suspend-signups', 'amount' => 8, 'unit' => 'days'], 'The preset does not allow an incident that long.'],
            'error.reason_too_long' => [['preset' => 'suspend-signups', 'amount' => 2, 'unit' => 'hours', 'reason' => str_repeat('a', 501)], 'The reason has at most 500 characters.'],
        ];

        foreach ($refusals as [$form, $message]) {
            $crawler = $this->client->request('POST', '/admin/incidents/start', ['_token' => $this->token(), ...$form], server: self::ADMIN);
            self::assertResponseStatusCodeSame(422);
            self::assertSame($message, $crawler->filter('[data-slot=alert]')->text());
        }

        $this->client->request('POST', '/admin/incidents/start', ['_token' => $this->token(), 'preset' => 'suspend-signups', 'amount' => 7, 'unit' => 'days', 'reason' => str_repeat('a', 500)], server: self::ADMIN);
        self::assertResponseRedirects();
        [$activation] = $this->incidents()->inForceNow();

        $crawler = $this->client->request('POST', '/admin/incidents/'.$activation->id.'/extend', ['_token' => $this->token(), 'amount' => 1, 'unit' => 'minutes'], server: self::ADMIN);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('The preset does not allow an incident that long.', $crawler->filter('[data-slot=alert]')->text());
        self::assertStringContainsString('Its preset allows no longer duration.', $crawler->filter('article.phil-incident')->text());

        $crawler = $this->client->request('POST', '/admin/incidents/'.$activation->id.'/extend', ['_token' => $this->token(), 'amount' => 0, 'unit' => 'minutes'], server: self::ADMIN);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('The duration is from one minute to ninety days.', $crawler->filter('[data-slot=alert]')->text());

        $missing = str_repeat('0', 32);
        foreach (['extend', 'lift'] as $action) {
            $crawler = $this->client->request('POST', '/admin/incidents/'.$missing.'/'.$action, ['_token' => $this->token(), 'amount' => 1, 'unit' => 'hours'], server: self::ADMIN);
            self::assertResponseStatusCodeSame(422);
            self::assertSame('That incident is no longer in force.', $crawler->filter('[data-slot=alert]')->text());
        }
    }

    public function testAnActorThatIsAnAddressIsRefused(): void
    {
        $this->client->catchExceptions(true);
        $this->client->request('POST', '/admin/incidents/start', ['_token' => $this->token(), 'preset' => 'suspend-signups', 'amount' => 2, 'unit' => 'hours'], server: ['HTTP_X_MEMBER' => 'ada@example.org', 'HTTP_X_ROLES' => 'ROLE_INCIDENTS', 'HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->incidents()->journal());
    }

    public function testTheJournalIsReadAPageAtATime(): void
    {
        foreach (['suspend-signups', 'suspend-invitations', 'read-only'] as $preset) {
            $this->incidents()->start($preset, Duration::ofMinutes(30), Actor::of('admin-7'));
            $this->clock()->modify('+1 minute');
        }

        $crawler = $this->client->request('GET', '/admin/incidents/', server: self::ADMIN);
        self::assertSame(['Read only', 'Suspend invitations'], $crawler->filter('tbody tr td:first-child')->each(static fn (Crawler $node): string => $node->text()));
        self::assertSame('/admin/incidents/?page=2', $crawler->filter('a[rel=next]')->attr('href'));
        self::assertCount(0, $crawler->filter('a[rel=prev]'));

        $crawler = $this->client->request('GET', '/admin/incidents/?page=2', server: self::ADMIN);
        self::assertSame(['Suspend sign-ups'], $crawler->filter('tbody tr td:first-child')->each(static fn (Crawler $node): string => $node->text()));
        self::assertSame('/admin/incidents/?page=1', $crawler->filter('a[rel=prev]')->attr('href'));
        self::assertCount(0, $crawler->filter('a[rel=next]'));
        self::assertSame('In force or awaiting its lifting', $crawler->filter('tbody tr td')->eq(3)->text());

        $crawler = $this->client->request('GET', '/admin/incidents/?page=0', server: self::ADMIN);
        self::assertCount(2, $crawler->filter('tbody tr'));
    }

    public function testAControllerIsRefusedWhileWhatItDoesIsSuspended(): void
    {
        $this->client->request('POST', '/sign-up');
        self::assertResponseIsSuccessful();

        $this->incidents()->start('suspend-signups', Duration::ofHours(2), Actor::of('admin-7'));

        $this->client->request('GET', '/sign-up');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('POST', '/sign-up');
        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Retry-After', '7200');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSame('Temporarily suspended', $crawler->filter('h1')->text());
        self::assertSame('Sign-ups are suspended until November 17, 2026 at 12:00 PM (Eastern Standard Time).', self::plain($crawler->filter('[data-restriction=signups]')->text()));
        self::assertSame('2026-11-17T17:00:00+00:00', $crawler->filter('[data-restriction=signups]')->attr('data-until'));

        $this->client->request('DELETE', '/sign-up');
        self::assertResponseIsSuccessful();
    }

    public function testTheAttributeOfTheClassCoversEveryAction(): void
    {
        $this->incidents()->start('read-only', Duration::ofMinutes(30), Actor::of('admin-7'));

        $crawler = $this->client->request('DELETE', '/sign-up');
        self::assertResponseStatusCodeSame(503);
        self::assertStringContainsString('The site is read only until', $crawler->filter('[data-restriction=read-only]')->text());
    }

    public function testARouteIsRefusedByItsDefaults(): void
    {
        $this->client->request('GET', '/invite');
        self::assertResponseIsSuccessful();

        $this->incidents()->start('suspend-invitations', Duration::ofHours(1), Actor::of('admin-7'));
        $crawler = $this->client->request('GET', '/invite');
        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Retry-After', '3600');
        self::assertSame('Invitations are suspended until November 17, 2026 at 11:00 AM (Eastern Standard Time).', self::plain($crawler->filter('[data-restriction=invitations]')->text()));
    }

    public function testACodePathIsRefusedButAnEstablishedMemberIsSpared(): void
    {
        $this->incidents()->start('suspend-contributions', Duration::ofHours(1), Actor::of('admin-7'));

        $this->client->request('POST', '/post', server: ['HTTP_X_MEMBER' => 'newcomer']);
        self::assertResponseStatusCodeSame(503);

        $this->client->request('POST', '/post');
        self::assertResponseStatusCodeSame(503);

        $this->client->request('POST', '/post', server: ['HTTP_X_MEMBER' => 'trusted-ada']);
        self::assertResponseIsSuccessful();
        self::assertSame('Posted', $this->client->getResponse()->getContent());
    }

    public function testAFormIsInvalidWhileWhatItSendsIsSuspended(): void
    {
        $this->client->request('POST', '/contact', ['contact' => ['message' => 'Hello']]);
        self::assertSame('Sent', $this->client->getResponse()->getContent());

        $this->incidents()->start('suspend-messages', Duration::ofMinutes(30), Actor::of('admin-7'));
        $this->client->request('GET', '/contact');
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/contact', ['contact' => ['message' => 'Hello']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('New messages are suspended until November 17, 2026 at 10:30 AM (Eastern Standard Time).', self::plain((string) $this->client->getResponse()->getContent()));
    }

    public function testThePublicPagesSayWhatIsSuspendedAndUntilWhen(): void
    {
        $crawler = $this->client->request('GET', '/status/');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSame('Service status', $crawler->filter('h1')->text());
        self::assertSame('Nothing is suspended: everything works as usual.', $crawler->filter('[role=status]')->text());

        $crawler = $this->client->request('GET', '/');
        self::assertCount(0, $crawler->filter('aside'));

        $this->incidents()->start('suspend-listings', Duration::ofHours(4), Actor::of('admin-7'));
        $this->clock()->modify('+1 second');
        $this->incidents()->start('suspend-signups', Duration::ofHours(2), Actor::of('admin-7'));

        $crawler = $this->client->request('GET', '/status/');
        self::assertSame(['“new-listings” is suspended until November 17, 2026 at 2:00 PM (Eastern Standard Time).', 'Sign-ups are suspended until November 17, 2026 at 12:00 PM (Eastern Standard Time).'], $crawler->filter('li')->each(static fn (Crawler $node): string => self::plain($node->text())));
        self::assertSame('status', $crawler->filter('[data-slot=alert]')->attr('role'));

        $crawler = $this->client->request('GET', '/status/notice');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('h1'));
        self::assertCount(2, $crawler->filter('li'));

        $crawler = $this->client->request('GET', '/');
        self::assertSame('Temporary suspensions', $crawler->filter('aside')->attr('aria-label'));
        self::assertCount(2, $crawler->filter('aside li'));
    }

    public function testThePagesSpeakTheVisitorsLanguage(): void
    {
        $this->incidents()->start('suspend-signups', Duration::ofHours(2), Actor::of('admin-7'));
        $translator = self::getContainer()->get('translator');
        $this->assertInstanceOf(LocaleAwareInterface::class, $translator);
        $translator->setLocale('fr');
        $wording = self::getContainer()->get(IncidentWording::class);
        $this->assertInstanceOf(IncidentWording::class, $wording);

        self::assertSame('Les inscriptions sont suspendues jusqu’au 17 novembre 2026 à 12:00 (heure normale de l’Est nord-américain).', self::plain($wording->sentence($this->checkpoint()->suspensions()[0])));
    }

    public function testEachIncidentSendsItsDelayedMessage(): void
    {
        $activation = $this->incidents()->start('suspend-signups', Duration::ofHours(2), Actor::of('admin-7'));

        $transport = self::getContainer()->get('messenger.transport.async');
        $this->assertInstanceOf(InMemoryTransport::class, $transport);
        $sent = $transport->getSent();
        self::assertCount(1, $sent);
        self::assertEquals(new LiftIncident($activation->id), $sent[0]->getMessage());
        self::assertSame(7_200_000, $sent[0]->last(DelayStamp::class)?->getDelay());
    }

    public function testTheRecurringSweepIsAScheduleOfItsOwn(): void
    {
        self::assertTrue(self::getContainer()->has('messenger.transport.scheduler_incident_mode'));
        self::assertInstanceOf(IncidentSchedule::class, self::getContainer()->get(IncidentSchedule::class));
    }

    public function testTheShadcnKitDrawsTheSamePages(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient(['environment' => 'test_'.TestKernel::SHADCN]);
        $client->disableReboot();
        $client->catchExceptions(false);

        $kernel = $client->getKernel();
        $this->assertInstanceOf(TestKernel::class, $kernel);
        $kernel->resetDatabase();
        $incidents = self::getContainer()->get(IncidentMode::class);
        $this->assertInstanceOf(IncidentMode::class, $incidents);
        $incidents->start('suspend-signups', Duration::ofHours(2), Actor::of('admin-7'));

        $crawler = $client->request('GET', '/admin/incidents/', server: self::ADMIN);
        self::assertResponseIsSuccessful();
        self::assertSame('Suspend sign-ups', $crawler->filter('article.phil-incident h3')->text());
        self::assertCount(1, $crawler->filter('article.phil-incident [data-slot=badge]'));
        self::assertCount(6, $crawler->filter('form.phil-incident-preset [data-slot=native-select]'));
        self::assertCount(1, $crawler->filter('[data-slot=table-container] table'));

        $crawler = $client->request('GET', '/status/');
        self::assertCount(1, $crawler->filter('[data-slot=alert] li'));
    }

    /**
     * The text with the narrow and the plain no-break spaces of the dates written as spaces.
     */
    private static function plain(string $text): string
    {
        return str_replace(["\u{202F}", "\u{00A0}"], ' ', $text);
    }

    private function incidents(): IncidentMode
    {
        $incidents = self::getContainer()->get(IncidentMode::class);
        $this->assertInstanceOf(IncidentMode::class, $incidents);

        return $incidents;
    }

    private function checkpoint(): Checkpoint
    {
        $checkpoint = self::getContainer()->get(Checkpoint::class);
        $this->assertInstanceOf(Checkpoint::class, $checkpoint);

        return $checkpoint;
    }

    private function clock(): MockClock
    {
        $clock = self::getContainer()->get('clock');
        $this->assertInstanceOf(MockClock::class, $clock);

        return $clock;
    }

    private function token(): string
    {
        $tokens = self::getContainer()->get('security.csrf.token_manager');
        $this->assertInstanceOf(CsrfTokenManagerInterface::class, $tokens);

        return $tokens->getToken(AdminRequest::TOKEN_ID)->getValue();
    }
}
