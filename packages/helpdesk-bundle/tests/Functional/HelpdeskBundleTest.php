<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\HelpdeskTables;
use Phil\HelpdeskBundle\Tests\Functional\App\TestKernel;
use Phil\HelpdeskBundle\Tests\Unit\Fixture\Desk;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The help desk in an application: a member opens a request, an agent of its queue reads it on the desk, answers with a
 * canned reply, the member answers back, the agent resolves and the member closes it; an agent of another queue does not
 * see it, a member sees only their own requests, the forms refuse a request without the token; the desk grafted on an
 * EasyAdmin dashboard.
 */
#[CoversNothing]
final class HelpdeskBundleTest extends WebTestCase
{
    private const array SAME_SITE = ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_REFERER' => 'http://localhost/help/'];

    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->assertInstanceOf(Connection::class, $connection);
        $schemaManager = $connection->createSchemaManager();

        foreach ([HelpdeskTables::TICKET, ConversationTables::BLOCK, ConversationTables::RECEPTION, ConversationTables::MESSAGE, ConversationTables::PARTICIPANT, ConversationTables::CONVERSATION] as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $schemaManager->dropTable($table);
            }
        }

        Desk::create($connection);
    }

    public function testARequestGoesFromTheMemberToTheAgentAndBack(): void
    {
        $this->signIn('ada');
        $crawler = $this->client->request('GET', '/help/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[data-slot="native-select"][name="queue"] option[value="tech"]');
        self::assertSelectorExists('input[data-slot="input"][name="subject"]');

        $form = $crawler->filter('form.phil-helpdesk__new')->form(['queue' => 'billing', 'subject' => 'Wrong invoice', 'body' => 'I was charged twice.']);
        $this->client->submit($form, [], self::SAME_SITE);
        self::assertResponseStatusCodeSame(303);
        $request = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/help/[0-9a-f-]{36}$#', $request);
        $id = substr($request, \strlen('/help/'));

        $this->client->request('GET', '/help/');
        self::assertSelectorTextContains('.phil-helpdesk__request [data-slot="badge"]', 'New');

        $this->signIn('agent-1');
        $this->client->request('GET', '/desk/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.phil-helpdesk__desk tbody', 'ada');
        self::assertSelectorTextContains('.phil-helpdesk__desk tbody', 'Billing');
        self::assertSelectorTextContains('.phil-helpdesk__desk tbody', 'Nobody');

        $crawler = $this->client->request('GET', '/desk/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.phil-conversation__body', 'I was charged twice.');
        self::assertSelectorExists('select[name="canned_reply"] option[value="thanks"]');
        self::assertSelectorNotExists('select[name="queue"]', 'an agent of one queue moves a request nowhere');

        $form = $crawler->filter('form.phil-helpdesk__reply')->form(['canned_reply' => 'thanks']);
        $this->client->submit($form, [], self::SAME_SITE);
        self::assertResponseRedirects('/desk/'.$id, 303);
        $this->client->request('GET', '/desk/'.$id);
        self::assertSelectorTextContains('.phil-conversation__message:last-child .phil-conversation__body', 'Thank you for your patience: your request about billing is in good hands.');
        self::assertSelectorTextContains('.phil-helpdesk__ticket', 'Waiting for an answer');
        self::assertSelectorTextContains('.phil-helpdesk__ticket', 'agent-1');
        self::assertSelectorTextContains('.phil-helpdesk__actions', 'Unassign');

        $this->signIn('ada');
        $crawler = $this->client->request('GET', $request);
        self::assertSelectorCount(2, '.phil-conversation__message');
        $form = $crawler->filter('form.phil-helpdesk__answer')->form(['body' => 'Thanks, any news?']);
        $this->client->submit($form, [], self::SAME_SITE);
        self::assertResponseRedirects($request, 303);
        $this->client->request('GET', $request);
        self::assertSelectorTextContains('.phil-helpdesk__summary', 'Open');

        $this->signIn('agent-1');
        $this->client->request('POST', '/desk/'.$id.'/transition/resolve', ['_token' => 'csrf-token'], server: self::SAME_SITE);
        self::assertResponseRedirects('/desk/'.$id, 303);

        $this->signIn('ada');
        $this->client->request('POST', $request.'/withdraw', ['_token' => 'csrf-token'], server: self::SAME_SITE);
        self::assertResponseRedirects($request, 303);
        $this->client->request('GET', $request);
        self::assertSelectorTextContains('.phil-helpdesk__closed', 'This request is closed.');
        self::assertSelectorNotExists('form.phil-helpdesk__answer');

        $this->client->request('POST', $request.'/withdraw', ['_token' => 'csrf-token'], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $request.'/answer', ['_token' => 'csrf-token', 'body' => 'Late'], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheAgentsTakeAssignMoveAndFilter(): void
    {
        $id = $this->open('ada', 'billing');

        $this->signIn('lead');
        $this->client->request('POST', '/desk/'.$id.'/assign', ['_token' => 'csrf-token'], server: self::SAME_SITE);
        self::assertResponseRedirects('/desk/'.$id, 303);
        $this->client->request('GET', '/desk/?mine=1&status=open&queue=billing');
        self::assertSelectorCount(1, '.phil-helpdesk__desk tbody tr');
        $this->client->request('GET', '/desk/?status=closed');
        self::assertSelectorTextContains('.phil-helpdesk__empty', 'No request here.');
        $this->client->request('GET', '/desk/?queue=NOT%20A%20QUEUE');
        self::assertSelectorCount(1, '.phil-helpdesk__desk tbody tr');

        $crawler = $this->client->request('GET', '/desk/'.$id);
        self::assertSelectorExists('select[name="queue"] option[value="tech"]');
        $this->client->submit($crawler->filter('.phil-helpdesk__actions form')->last()->form(['queue' => 'tech']), [], self::SAME_SITE);
        self::assertResponseRedirects('/desk/'.$id, 303);
        $this->client->request('POST', '/desk/'.$id.'/move', ['_token' => 'csrf-token', 'queue' => 'Sales'], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'The name of a queue holds');

        $this->signIn('agent-1');
        $this->client->request('GET', '/desk/'.$id);
        self::assertResponseStatusCodeSame(404, 'the request left the queue of the agent');

        $this->signIn('lead');
        $this->client->request('POST', '/desk/'.$id.'/assign', ['_token' => 'csrf-token', 'unassign' => '1'], server: self::SAME_SITE);
        $this->client->request('GET', '/desk/'.$id);
        self::assertSelectorTextContains('.phil-helpdesk__actions', 'Assign to me');
        $this->client->request('POST', '/desk/'.$id.'/transition/take', ['_token' => 'csrf-token'], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'A request that is open cannot be changed by "take".');
        $this->client->request('POST', '/desk/'.$id.'/reply', ['_token' => 'csrf-token', 'body' => ' '], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/desk/'.$id.'/reply', ['_token' => 'csrf-token', 'canned_reply' => 'unknown'], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'No canned reply "unknown" for this request.');
    }

    public function testNobodySeesTheRequestsOfOthers(): void
    {
        $id = $this->open('ada', 'tech');

        $this->signIn('agent-2');
        $this->client->request('GET', '/desk/');
        self::assertSelectorTextContains('.phil-helpdesk__empty', 'No request here.');
        foreach ([['GET', '/desk/'.$id], ['POST', '/desk/'.$id.'/reply'], ['POST', '/desk/'.$id.'/assign'], ['POST', '/desk/'.$id.'/transition/close'], ['POST', '/desk/'.$id.'/move'], ['GET', '/help/'.$id], ['POST', '/help/'.$id.'/answer'], ['GET', '/desk/0192f0c1-5c5e-7a3b-9e1d-0123456789ab']] as [$method, $url]) {
            $this->client->request($method, $url, ['_token' => 'csrf-token', 'body' => 'Hi'], server: self::SAME_SITE);
            self::assertResponseStatusCodeSame(404, $method.' '.$url);
        }

        $this->signIn('ada');
        $this->client->request('GET', '/desk/');
        self::assertResponseStatusCodeSame(403, 'a member who works no queue has no desk');
        $this->client->request('POST', '/help/new', ['_token' => 'csrf-token', 'queue' => 'sales', 'subject' => 'Hi', 'body' => 'Hello'], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'No queue "sales" is declared.');
        self::assertSelectorTextContains('textarea', 'Hello');
        $this->client->request('POST', '/help/new', ['queue' => 'tech', 'subject' => 'Hi', 'body' => 'Hello'], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/help/'.$id.'/answer', ['_token' => 'csrf-token', 'body' => ''], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/help/');
        self::assertResponseStatusCodeSame(401, 'without a participant, the pages meet the firewall');
    }

    public function testTheDeskIsGraftedOnTheDashboard(): void
    {
        $id = $this->open('ada', 'billing');
        $router = self::getContainer()->get('router');
        $this->assertInstanceOf(RouterInterface::class, $router);
        self::assertSame('/admin/helpdesk', $router->generate('admin_helpdesk_desk'));
        self::assertSame('/admin/helpdesk/'.$id, $router->generate('admin_helpdesk_ticket', ['id' => $id]));

        $this->signIn('agent-1');
        $crawler = $this->client->request('GET', '/admin/helpdesk');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href="/admin/helpdesk/'.$id.'"]'));
        self::assertCount(1, $crawler->filter('.ea-sidebar-group-items a[href="http://localhost/admin/helpdesk"]'), 'the menu of the dashboard links to the desk');

        $crawler = $this->client->request('GET', '/admin/helpdesk/'.$id);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form.phil-helpdesk__reply')->form(['body' => 'On it.']), [], self::SAME_SITE);
        self::assertResponseRedirects('/admin/helpdesk/'.$id, 303);
    }

    private function open(string $requester, string $queue): string
    {
        $this->signIn($requester);
        $this->client->request('POST', '/help/new', ['_token' => 'csrf-token', 'queue' => $queue, 'subject' => 'Help', 'body' => 'Please help.'], server: self::SAME_SITE);
        self::assertResponseStatusCodeSame(303);

        return substr((string) $this->client->getResponse()->headers->get('Location'), \strlen('/help/'));
    }

    private function signIn(string $user): void
    {
        $provider = self::getContainer()->get('security.user.provider.concrete.people');
        $this->assertInstanceOf(UserProviderInterface::class, $provider);
        $this->client->loginUser($provider->loadUserByIdentifier($user));
    }
}
