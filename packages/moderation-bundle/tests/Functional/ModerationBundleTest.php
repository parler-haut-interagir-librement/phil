<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use Phil\IncidentMode\Dbal\IncidentTables;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use Phil\ModerationBundle\Infrastructure\Http\AdminController;
use Phil\ModerationBundle\Infrastructure\Http\JournalController;
use Phil\ModerationBundle\Infrastructure\Http\ReportController;
use Phil\ModerationBundle\Infrastructure\Http\StatementController;
use Phil\ModerationBundle\Infrastructure\IncidentMode\IncidentProposal;
use Phil\ModerationBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(AdminController::class)]
#[CoversClass(ReportController::class)]
#[CoversClass(JournalController::class)]
#[CoversClass(StatementController::class)]
#[CoversClass(IncidentProposal::class)]
final class ModerationBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $manager = $connection->createSchemaManager();

        foreach ([...ModerationTables::tables(), IncidentTables::table()] as $table) {
            $name = $table->getObjectName()->toString();

            if ($manager->tablesExist([$name])) {
                $manager->dropTable($name);
            }

            $manager->createTable($table);
        }

        $kernel = self::$kernel;
        self::assertInstanceOf(TestKernel::class, $kernel);

        if (is_file($kernel->actionLog())) {
            unlink($kernel->actionLog());
        }
    }

    public function testAMemberReportsFromThePageAndGoesBack(): void
    {
        $crawler = $this->client->request('GET', '/forum/1', server: $this->as('m-1'));
        $html = (string) $this->client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('value="spam">Spam</option>', $html);
        self::assertStringContainsString('Personal information', $html);
        self::assertStringContainsString('data-slot="native-select"', $html);
        self::assertStringNotContainsString('Hide until reviewed', $html);
        self::assertStringNotContainsString('Sign-ups are suspended.', $html);

        $form = $crawler->filter('form[action="/moderation/report"]')->form(['reason' => 'spam', 'comment' => 'Ads']);
        $this->client->submit($form, [], $this->as('m-1'));

        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/forum/1');
        self::assertSame(1, $this->rowsIn(ModerationTables::REPORTS));

        $this->client->request('POST', '/moderation/report', ['type' => 'forum.post', 'id' => '1', 'reason' => 'spam', '_token' => $this->reportToken(), '_target_path' => '//evil.example'], server: $this->as('m-1'));
        self::assertResponseRedirects('/');
        self::assertSame(1, $this->rowsIn(ModerationTables::REPORTS), 'a member reports a content once while it is open');
    }

    public function testAReportIsRefused(): void
    {
        $token = $this->reportToken();

        $this->client->request('POST', '/moderation/report', ['type' => 'forum.post', 'id' => '1', 'reason' => 'spam', '_token' => 'forged'], server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/moderation/report', ['type' => 'forum.post', 'id' => '1', 'reason' => 'first-post', '_token' => $token], server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/moderation/report', ['type' => 'listing', 'id' => '1', 'reason' => 'spam', '_token' => $token], server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/moderation/report', ['type' => 'Bad', 'id' => '1', 'reason' => 'spam', '_token' => $token], server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/moderation/report', ['type' => 'forum.post', 'id' => '1', 'reason' => 'spam', 'comment' => str_repeat('é', 1001), '_token' => $token], server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(400);

        self::assertSame(0, $this->rowsIn(ModerationTables::REPORTS));
    }

    public function testATrustedMemberHides(): void
    {
        $crawler = $this->client->request('GET', '/forum/1', server: $this->as('trusted'));
        self::assertStringContainsString('Hide until reviewed', (string) $this->client->getResponse()->getContent());

        $this->client->submit($crawler->filter('form[action="/moderation/hide"]')->form(), [], $this->as('trusted'));
        self::assertResponseRedirects('/forum/1');
        self::assertSame("1 hide\n", $this->actions());

        $token = $this->reportToken();
        $this->client->request('POST', '/moderation/hide', ['type' => 'forum.post', 'id' => '1', '_token' => $token], server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/moderation/hide', ['type' => 'listing', 'id' => '1', '_token' => $token], server: $this->as('trusted'));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/moderation/hide', ['type' => 'forum.post', 'id' => '1', 'comment' => str_repeat('é', 1001), '_token' => $token], server: $this->as('trusted'));
        self::assertResponseStatusCodeSame(400);
    }

    public function testTheQueueIsForModeratorsOnly(): void
    {
        $this->client->request('GET', '/admin/moderation/');
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/admin/moderation/', server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/moderation/entry?type=forum.post&id=1', server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/moderation/incidents', server: $this->as('admin-1'));
        self::assertResponseStatusCodeSame(404, "the incident mode is phil/incident-mode-bundle's page, not one of the moderation");
    }

    public function testAModeratorReviewsAndDecides(): void
    {
        $this->report('m-1', 'spam');
        $this->report('trusted', 'hate');

        $crawler = $this->client->request('GET', '/admin/moderation/', server: $this->as('mod-1'));
        $html = (string) $this->client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('One target to review', $html);
        self::assertStringContainsString('You act as moderator.', $html);
        self::assertStringContainsString('Cheap watches', $html);
        self::assertStringContainsString('<table data-slot="table" class="isTable">', $html);
        self::assertStringContainsString('>Hate<', $html);
        self::assertStringNotContainsString('Start the incident mode', $html, 'only the role of the incident mode is proposed it');

        $this->client->click($crawler->filter('a:contains("Cheap watches")')->link(), $this->as('mod-1'));
        $entry = (string) $this->client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('2 open reports', $entry);
        self::assertStringContainsString('Buy &lt;b&gt;now&lt;/b&gt;', $entry);
        self::assertStringContainsString('href="/admin/moderation/member?member=author"', $entry);
        self::assertStringContainsString('reported by a member', $entry);

        $form = $this->client->getCrawler()->filter('form[action="/admin/moderation/entry"]')->form(['action' => 'hide', 'reason' => 'Advertising', 'note' => 'Second time']);
        $this->client->submit($form, [], $this->as('mod-1'));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('A decision that restricts the author states its grounds', (string) $this->client->getResponse()->getContent());
        self::assertSame('', $this->actions(), 'a decision refused for want of its grounds changes nothing');

        $form = $this->client->getCrawler()->filter('form[action="/admin/moderation/entry"]')->form([
            'action' => 'hide', 'reason' => 'Advertising', 'note' => 'Second time',
            'ground' => 'spam', 'explanation' => 'The post advertises a shop of watches.',
        ]);
        $this->client->submit($form, [], $this->as('mod-1'));

        self::assertResponseRedirects('/admin/moderation/');
        self::assertSame("1 hide\n", $this->actions());
        self::assertSame(0, $this->openReports());

        $this->client->request('GET', '/admin/moderation/entry?type=forum.post&id=1', server: $this->as('mod-1'));
        $history = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('No report is open on this content.', $history);
        self::assertStringContainsString('Advertising', $history);
        self::assertStringContainsString('Second time', $history);

        $this->client->request('GET', '/moderation/journal/');
        $journal = (string) $this->client->getResponse()->getContent();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('max-age=60', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('Advertising', $journal);
        self::assertStringContainsString('moderator', $journal);
        self::assertStringContainsString('forum.post', $journal);
        self::assertStringNotContainsString('mod-1', $journal);
        self::assertStringNotContainsString('Second time', $journal);
        self::assertStringNotContainsString('author', $journal);

        $this->assertTheAuthorReadsTheirStatement();
    }

    private function assertTheAuthorReadsTheirStatement(): void
    {
        $this->client->request('GET', '/moderation/statements/');
        self::assertResponseStatusCodeSame(401);

        $list = $this->client->request('GET', '/moderation/statements/', server: $this->as('author'));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('Statements of reasons', (string) $this->client->getResponse()->getContent());
        $link = $list->filter('a[href^="/moderation/statements/"]')->first()->attr('href');
        self::assertIsString($link);

        $this->client->request('GET', $link, server: $this->as('author'));
        $statement = (string) $this->client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('Your content was hidden', $statement);
        self::assertStringContainsString('The post advertises a shop of watches.', $statement);
        self::assertStringContainsString('>Spam<', $statement);
        self::assertStringContainsString('The terms of the site', $statement);
        self::assertStringContainsString('after a member reported it', $statement);
        self::assertStringContainsString('A person took this decision', $statement);
        self::assertStringContainsString('href="/terms"', $statement);
        self::assertStringContainsString('href="https://settlement.example"', $statement);
        self::assertStringNotContainsString('mod-1', $statement);
        self::assertStringNotContainsString('m-1', $statement);
        self::assertStringNotContainsString('Second time', $statement);

        $this->client->request('GET', $link, server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(404, 'a statement addressed to someone else is not found');

        $this->client->request('GET', '/moderation/statements/', server: $this->as('m-1'));
        self::assertStringContainsString('No statement of reasons is addressed to you.', (string) $this->client->getResponse()->getContent());
    }

    public function testADecisionIsRefused(): void
    {
        $token = $this->adminToken('mod-1');

        $this->client->request('POST', '/admin/moderation/entry', ['type' => 'forum.post', 'id' => '1', 'action' => 'hide', '_token' => 'forged'], server: $this->as('mod-1'));
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/admin/moderation/entry', ['type' => 'forum.post', 'id' => '1', 'action' => 'burn', '_token' => $token], server: $this->as('mod-1'));
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/admin/moderation/entry', ['type' => 'listing', 'id' => '1', 'action' => 'hide', '_token' => $token], server: $this->as('mod-1'));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/admin/moderation/entry', ['type' => 'Bad', 'id' => '1', 'action' => 'hide', '_token' => $token], server: $this->as('mod-1'));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/admin/moderation/entry', ['type' => 'forum.post', 'id' => '1', 'action' => 'hide', 'reason' => str_repeat('é', 501), '_token' => $token], server: $this->as('mod-1'));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('A public reason has at most 500 characters.', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('data-slot="alert"', (string) $this->client->getResponse()->getContent());

        $keeper = $this->adminToken('keeper');
        $this->client->request('GET', '/admin/moderation/entry?type=forum.post&id=1', server: $this->as('keeper'));
        self::assertStringNotContainsString('form method="post" action="/admin/moderation/entry"', (string) $this->client->getResponse()->getContent());
        $this->client->request('POST', '/admin/moderation/entry', ['type' => 'forum.post', 'id' => '1', 'action' => 'hide', '_token' => $keeper], server: $this->as('keeper'));
        self::assertResponseStatusCodeSame(403);

        self::assertSame('', $this->actions());
    }

    public function testTheNotesOnAMember(): void
    {
        $crawler = $this->client->request('GET', '/admin/moderation/member?member=author', server: $this->as('mod-1'));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('No note on this member.', (string) $this->client->getResponse()->getContent());

        $this->client->submit($crawler->filter('form[action="/admin/moderation/member/notes"]')->form(['body' => 'Warned in DM']), [], $this->as('mod-1'));
        self::assertResponseRedirects('/admin/moderation/member?member=author');

        $crawler = $this->client->request('GET', '/admin/moderation/member?member=author', server: $this->as('mod-1'));
        self::assertStringContainsString('Warned in DM', (string) $this->client->getResponse()->getContent());

        $token = $this->adminToken('mod-1');
        $this->client->request('POST', '/admin/moderation/member/notes', ['member' => 'author', 'body' => '  ', '_token' => $token], server: $this->as('mod-1'));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('A note is not empty.', (string) $this->client->getResponse()->getContent());

        $this->client->submit($crawler->filter('form[action^="/admin/moderation/member/notes/"]')->form(), [], $this->as('mod-1'));
        self::assertResponseRedirects('/admin/moderation/member?member=author');
        self::assertSame(0, $this->rowsIn(ModerationTables::NOTES));

        $this->client->request('POST', '/admin/moderation/member/notes/'.str_repeat('a', 32).'/remove', ['member' => 'author', '_token' => $token], server: $this->as('mod-1'));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/admin/moderation/member?member=someone@example.org', server: $this->as('mod-1'));
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheQueueProposesThePageOfTheIncidentModeBundleWhenReportsSurge(): void
    {
        $this->client->request('GET', '/admin/moderation/', server: $this->as('admin-1'));
        $calm = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('href="/admin/incidents/"', $calm, 'the role of the incident mode finds its page');
        self::assertStringNotContainsString('Reports are surging.', $calm);

        $this->report('m-1', 'spam');
        $this->report('trusted', 'spam');

        $this->client->request('GET', '/admin/moderation/', server: $this->as('mod-1'));
        $moderator = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('Reports are surging.', $moderator, 'only the role of the incident mode is proposed it');
        self::assertStringNotContainsString('href="/admin/incidents/"', $moderator);

        $this->client->request('GET', '/admin/moderation/', server: $this->as('admin-1'));
        $queue = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Reports are surging.', $queue);
        self::assertStringContainsString('<a href="/admin/incidents/">Start the incident mode</a>', $queue);
        self::assertStringContainsString('You act as administrator.', $queue);

        $this->client->request('GET', '/admin/incidents/', server: $this->as('admin-1'));
        $page = (string) $this->client->getResponse()->getContent();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Quiet hours', $page);
        self::assertStringNotContainsString('value="read-only"', $page);

        $this->incidents()->start('suspend-signups', Duration::ofMinutes(90), null);

        $this->client->request('GET', '/admin/moderation/', server: $this->as('admin-1'));
        self::assertStringNotContainsString('Reports are surging.', (string) $this->client->getResponse()->getContent(), 'not while an incident is in force');
    }

    public function testASuspendedSignUpInvitationOrFormIsRefusedThroughTheIncidentModeBundle(): void
    {
        $this->client->request('POST', '/sign-up');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/invite', server: $this->as('m-1'));
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/forum/new', ['post' => ['body' => 'Hello']], server: $this->as('m-1'));
        self::assertSame('Posted', $this->client->getResponse()->getContent());

        $incidents = $this->incidents();
        $incidents->start('suspend-signups', Duration::ofMinutes(90), null);
        $incidents->start('suspend-invitations', Duration::ofMinutes(30), null);
        $incidents->start('quiet', Duration::ofMinutes(60), null);

        $this->client->request('GET', '/forum/1', server: $this->as('m-1'));
        self::assertStringContainsString('Sign-ups are suspended.', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/sign-up');
        self::assertResponseIsSuccessful('the sign-up is refused on posting only');
        $this->client->request('POST', '/sign-up');
        self::assertResponseStatusCodeSame(503);
        self::assertTrue($this->client->getResponse()->headers->has('Retry-After'));

        $this->client->request('GET', '/invite', server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(503);

        $this->client->request('POST', '/forum/new', ['post' => ['body' => 'Hello']], server: $this->as('m-1'));
        self::assertResponseStatusCodeSame(422);
        self::assertNotSame('Posted', $this->client->getResponse()->getContent());
        self::assertNotSame('', $this->client->getResponse()->getContent(), 'the form says until when');
    }

    private function incidents(): IncidentMode
    {
        $incidents = self::getContainer()->get(IncidentMode::class);
        self::assertInstanceOf(IncidentMode::class, $incidents);

        return $incidents;
    }

    private function report(string $member, string $reason): void
    {
        $this->client->request('POST', '/moderation/report', ['type' => 'forum.post', 'id' => '1', 'reason' => $reason, '_token' => $this->reportToken()], server: $this->as($member));
        self::assertResponseRedirects('/');
    }

    private function reportToken(): string
    {
        $crawler = $this->client->request('GET', '/forum/1', server: $this->as('m-1'));

        return (string) $crawler->filter('form[action="/moderation/report"] input[name="_token"]')->attr('value');
    }

    private function adminToken(string $moderator): string
    {
        $crawler = $this->client->request('GET', '/admin/moderation/member?member=author', server: $this->as($moderator));

        return (string) $crawler->filter('input[name="_token"]')->first()->attr('value');
    }

    /**
     * @return array<string, string>
     */
    private function as(string $user): array
    {
        return ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'p'];
    }

    private function actions(): string
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(TestKernel::class, $kernel);

        return is_file($kernel->actionLog()) ? (string) file_get_contents($kernel->actionLog()) : '';
    }

    private function rowsIn(string $table): int
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        $count = $connection->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function openReports(): int
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        $count = $connection->fetchOne('SELECT COUNT(*) FROM '.ModerationTables::REPORTS.' WHERE decision_id IS NULL');
        self::assertIsNumeric($count);

        return (int) $count;
    }
}
