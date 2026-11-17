<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use DOMElement;
use LogicException;
use Override;
use Phil\Lti\Contract\KeyStore;
use Phil\Lti\Contract\LineItemStore;
use Phil\Lti\Contract\ServiceClient;
use Phil\Lti\DeepLinking\LinkItem;
use Phil\Lti\Http\FormPost;
use Phil\Lti\Http\SafeHttpServiceClient;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Message\DeepLinkingSettings;
use Phil\Lti\Message\Launch;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\Ags\StoredLineItem;
use Phil\Lti\Platform\DeepLinkingResult;
use Phil\Lti\Platform\LaunchStarter;
use Phil\Lti\Registration\InMemoryToolRegistrations;
use Phil\Lti\Service\AccessTokenProvider;
use Phil\Lti\Service\Ags\LineItem;
use Phil\Lti\Service\Scope;
use Phil\Lti\Tool\DeepLinkingResponder;
use Phil\LtiBundle\Application\Contract\DeepLinkingReturns;
use Phil\LtiBundle\Application\Event\DeepLinkingReturned;
use Phil\LtiBundle\Application\Event\LaunchReceived;
use Phil\LtiBundle\Application\Event\LineItemDeleted;
use Phil\LtiBundle\Application\Event\LineItemSaved;
use Phil\LtiBundle\Application\Event\ScoreReceived;
use Phil\LtiBundle\Infrastructure\Console\SchemaCommand;
use Phil\LtiBundle\Infrastructure\Controller\AgsController;
use Phil\LtiBundle\Infrastructure\Controller\JwksController;
use Phil\LtiBundle\Infrastructure\Controller\NrpsController;
use Phil\LtiBundle\Infrastructure\Controller\PlatformLaunchController;
use Phil\LtiBundle\Infrastructure\Controller\ServiceResponses;
use Phil\LtiBundle\Infrastructure\Controller\TokenController;
use Phil\LtiBundle\Infrastructure\Controller\ToolController;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Phil\LtiBundle\Infrastructure\DependencyInjection\LtiServices;
use Phil\LtiBundle\Infrastructure\Http\FormPostResponder;
use Phil\LtiBundle\Infrastructure\Routing\PlatformUrls;
use Phil\LtiBundle\Infrastructure\Security\LtiLaunchAuthenticator;
use Phil\LtiBundle\Infrastructure\Security\LtiToken;
use Phil\LtiBundle\Infrastructure\Security\LtiUser;
use Phil\LtiBundle\Infrastructure\Security\LtiUserProvider;
use Phil\LtiBundle\PhilLtiBundle;
use Phil\LtiBundle\Tests\Functional\App\RecordingListener;
use Phil\LtiBundle\Tests\Functional\App\TestKernel;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The bundle in an application that launches itself: the platform starts a launch, the tool initiates the login, the
 * platform signs the launch for its member, the tool verifies it and signs the member in; then the tool asks for a
 * token and calls the grades and the roster of the platform, and answers a deep linking request.
 */
#[CoversClass(PhilLtiBundle::class)]
#[CoversClass(ToolController::class)]
#[CoversClass(PlatformLaunchController::class)]
#[CoversClass(TokenController::class)]
#[CoversClass(AgsController::class)]
#[CoversClass(NrpsController::class)]
#[CoversClass(JwksController::class)]
#[CoversClass(ServiceResponses::class)]
#[CoversClass(FormPostResponder::class)]
#[CoversClass(PlatformUrls::class)]
#[CoversClass(LtiLaunchAuthenticator::class)]
#[CoversClass(LtiToken::class)]
#[CoversClass(LtiUser::class)]
#[CoversClass(LtiUserProvider::class)]
#[CoversClass(SchemaCommand::class)]
final class LtiBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->client->setServerParameter('HTTPS', 'on');
        $this->client->setServerParameter('HTTP_HOST', 'localhost');

        $connection = $this->service('doctrine.dbal.default_connection');
        $this->assertInstanceOf(Connection::class, $connection);

        foreach ($this->service(LtiTables::class)->names() as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $tester = new CommandTester(new Application($this->client->getKernel())->find('phil:lti:schema'));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('8 tables missing, 0 tables to change', $this->flat($tester->getDisplay()));
        $tester->execute(['--force' => true]);
        self::assertStringContainsString('Created: phil_lti_nonce', $this->flat($tester->getDisplay()));
        self::assertStringContainsString('phil_lti_deep_linking. Changed: none.', $this->flat($tester->getDisplay()));
        $tester->execute([]);
        self::assertStringContainsString('The LTI tables are up to date.', $this->flat($tester->getDisplay()));
    }

    public function testTheSchemaCommandBringsTablesOfTheFirstVersionUpToDate(): void
    {
        $connection = $this->service('doctrine.dbal.default_connection');
        $this->assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('DROP TABLE phil_lti_pending_score');
        $connection->executeStatement('DROP TABLE phil_lti_deep_linking');
        $connection->executeStatement('CREATE TABLE phil_lti_pending_score (id VARCHAR(64) NOT NULL, issuer VARCHAR(1024) NOT NULL, client_id VARCHAR(255) NOT NULL, line_item VARCHAR(2048) NOT NULL, payload CLOB NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY (id))');
        $connection->executeStatement("INSERT INTO phil_lti_pending_score VALUES ('kept', 'https://lms.example.org', 'c', 'https://lms.example.org/items/1', '{}', 1)");

        $tester = new CommandTester(new Application($this->client->getKernel())->find('phil:lti:schema'));
        self::assertSame(0, $tester->execute([]));
        $display = $this->flat($tester->getDisplay());
        self::assertStringContainsString('1 tables missing, 1 tables to change', $display);
        self::assertStringContainsString('CREATE TABLE phil_lti_deep_linking', $display);
        self::assertStringContainsString('dispatched_at', $display);
        self::assertStringContainsString('CREATE INDEX phil_lti_pending_score_refused', $display);
        self::assertStringNotContainsString('DROP TABLE phil_lti_nonce', $display);

        self::assertSame(0, $tester->execute(['--force' => true]));
        self::assertStringContainsString('Created: phil_lti_deep_linking. Changed: phil_lti_pending_score.', $this->flat($tester->getDisplay()));
        $tester->execute([]);
        self::assertStringContainsString('The LTI tables are up to date.', $this->flat($tester->getDisplay()));
        self::assertSame([['id' => 'kept', 'created_at' => 1, 'dispatched_at' => null, 'claimed_at' => null, 'refused_at' => null, 'refusal' => null]], array_map(static fn (array $row): array => ['id' => $row['id'], 'created_at' => is_numeric($row['created_at']) ? (int) $row['created_at'] : null] + array_intersect_key($row, ['dispatched_at' => 1, 'claimed_at' => 1, 'refused_at' => 1, 'refusal' => 1]), $connection->fetchAllAssociative('SELECT * FROM phil_lti_pending_score')));
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testThePlatformLaunchesTheToolAndTheToolSignsItsMemberIn(): void
    {
        $login = $this->service(LaunchStarter::class)->loginUrl($this->service(InMemoryToolRegistrations::class)->find(TestKernel::CLIENT) ?? throw new LogicException(), '1', 'hint-ada', Url::fromString('https://localhost/course/1', true), 'link-1');

        $this->client->request('GET', $login->toString());
        $redirect = $this->client->getResponse();
        self::assertSame(302, $redirect->getStatusCode());
        $authorize = (string) $redirect->headers->get('Location');
        self::assertStringStartsWith('https://localhost/lti/platform/authorize?scope=openid&response_type=id_token&response_mode=form_post&prompt=none&client_id=client-42', $authorize);
        self::assertSame('no-store, private', $redirect->headers->get('Cache-Control'));

        $this->client->request('GET', $authorize, server: ['PHP_AUTH_USER' => 'ada', 'PHP_AUTH_PW' => 'ada']);
        $page = $this->client->getResponse();
        self::assertSame(200, $page->getStatusCode(), (string) $page->getContent());
        self::assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        self::assertStringContainsString("form-action https://localhost; frame-ancestors 'self' https://localhost", (string) $page->headers->get('Content-Security-Policy'));
        $form = new Crawler((string) $page->getContent(), 'https://localhost/');
        self::assertSame('https://localhost/lti/launch', $form->filter('form')->attr('action'));
        self::assertSame('phil--lti-bundle--auto-submit', $form->filter('form')->attr('data-controller'));
        self::assertStringContainsString('Continue', $form->filter('button[type=submit]')->text());
        $fields = [];

        foreach ($form->filter('input[type=hidden]') as $input) {
            $this->assertInstanceOf(DOMElement::class, $input);
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        self::assertSame(['id_token', 'state'], array_keys($fields));

        $this->client->request('POST', 'https://localhost/lti/launch', $fields);
        $launched = $this->client->getResponse();
        self::assertSame(303, $launched->getStatusCode(), (string) $launched->getContent());
        self::assertSame('https://localhost/course/1', $launched->headers->get('Location'));

        $storage = $this->service('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $storage);
        $token = $storage->getToken();
        self::assertInstanceOf(LtiToken::class, $token);
        self::assertSame('lti_launch', $token->getFirewallName());
        self::assertSame('sub-ada', $token->launch->subject());
        self::assertSame(['ROLE_LTI', 'ROLE_LTI_LEARNER'], $token->getRoleNames());
        self::assertSame('https://localhost/lti/platform/contexts/course-1/lineitems', $token->launch->message->ags?->lineItems?->toString());
        self::assertSame('https://localhost/lti/platform/contexts/course-1/memberships', $token->launch->message->nrps?->membershipsUrl->toString());
        self::assertSame([Scope::LINE_ITEM, Scope::LINE_ITEM_READ_ONLY, Scope::RESULT_READ_ONLY, Scope::SCORE], $token->launch->message->ags->scopes);

        $copy = new ReflectionClass(LtiToken::class)->newInstanceWithoutConstructor();
        $copy->__unserialize($token->__serialize());
        self::assertEquals($token->launch, $copy->launch);
        self::assertSame('lti_launch', $copy->getFirewallName());

        $events = $this->service(RecordingListener::class)->events;
        self::assertInstanceOf(LaunchReceived::class, $events[0] ?? null);

        $this->client->request('POST', 'https://localhost/lti/launch', $fields);
        self::assertSame(401, $this->client->getResponse()->getStatusCode(), substr((string) $this->client->getResponse()->getContent(), -400));
        self::assertSame('The launch is refused: The state of the launch is unknown, already used or expired.', $this->client->getResponse()->getContent());
    }

    public function testRefusedRequestsSayWhy(): void
    {
        $this->client->request('GET', 'https://localhost/lti/login?iss=https://other.example.org&login_hint=h&target_link_uri=https://localhost/course/1');
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertStringStartsWith('The launch is refused: No platform is registered', (string) $this->client->getResponse()->getContent());

        $this->client->request('POST', 'https://localhost/lti/launch', ['id_token' => 'a.b.c', 'state' => 'nope']);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', 'https://localhost/lti/launch', ['id_token' => 'a.b.c']);
        self::assertSame('The launch is refused: A launch carries its id_token and its state.', $this->client->getResponse()->getContent());

        $this->client->request('POST', 'https://localhost/lti/launch', []);
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('lacks the authenticator', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', 'https://localhost/lti/platform/authorize?scope=openid');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', 'https://localhost/lti/platform/authorize?scope=openid', server: ['PHP_AUTH_USER' => 'ada', 'PHP_AUTH_PW' => 'ada']);
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertStringStartsWith('The authentication request is refused: ', (string) $this->client->getResponse()->getContent());

        $this->client->request('POST', 'https://localhost/lti/platform/authorize', $this->authentication(['lti_message_hint' => 'link-9']), server: ['PHP_AUTH_USER' => 'ada', 'PHP_AUTH_PW' => 'ada']);
        $form = new Crawler((string) $this->client->getResponse()->getContent());
        self::assertSame('invalid_request', $form->filter('input[name=error]')->attr('value'));
        self::assertSame('The resource link is unknown.', $form->filter('input[name=error_description]')->attr('value'));
        self::assertSame('s', $form->filter('input[name=state]')->attr('value'));

        $this->client->request('POST', 'https://localhost/lti/platform/authorize', $this->authentication(['login_hint' => 'hint-bob', 'state' => '']), server: ['PHP_AUTH_USER' => 'ada', 'PHP_AUTH_PW' => 'ada']);
        $form = new Crawler((string) $this->client->getResponse()->getContent());
        self::assertSame('invalid_request', $form->filter('input[name=error]')->attr('value'));
        self::assertCount(0, $form->filter('input[name=state]'));

        $this->client->request('POST', 'https://localhost/lti/platform/deep-linking', []);
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', 'https://localhost/lti/platform/deep-linking', ['JWT' => 'a.b.c']);
        self::assertStringStartsWith('The deep linking response is refused: ', (string) $this->client->getResponse()->getContent());
    }

    public function testTheKeySetIsPublished(): void
    {
        $this->client->request('GET', 'https://localhost/lti/jwks');
        $response = $this->client->getResponse();

        self::assertSame('application/jwk-set+json', $response->headers->get('Content-Type'));
        self::assertSame('max-age=300, public', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('"kid":"test-1"', (string) $response->getContent());
        self::assertSame(['keys'], array_keys((array) json_decode((string) $response->getContent(), true)));
        self::assertCount(1, KeySet::fromJson((string) $response->getContent())->keys);
    }

    public function testTheServicesAreCalledThroughTheGuardedClient(): void
    {
        self::assertInstanceOf(SafeHttpServiceClient::class, self::getContainer()->get(ServiceClient::class));
        self::assertInstanceOf(SafeHttpClient::class, self::getContainer()->get(LtiServices::SERVICE_HTTP_CLIENT));
    }

    public function testTheToolUsesTheServicesOfThePlatform(): void
    {
        $token = $this->token();
        $auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/vnd.ims.lis.v2.lineitem+json'];
        $base = 'https://localhost/lti/platform/contexts/course-1';

        $this->client->request('POST', $base.'/lineitems', server: $auth, content: '{"scoreMaximum":10,"label":"Quiz","tag":"quiz","id":"https://evil.example.com/"}');
        $created = $this->client->getResponse();
        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        self::assertSame('application/vnd.ims.lis.v2.lineitem+json', $created->headers->get('Content-Type'));
        $item = $this->json((string) $created->getContent());
        $url = $item['id'] ?? null;
        self::assertIsString($url);
        self::assertMatchesRegularExpression('#^https://localhost/lti/platform/contexts/course-1/lineitems/[0-9a-f]{32}$#', $url);
        self::assertSame(10.0, $item['scoreMaximum']);
        $this->client->request('POST', $base.'/lineitems', server: $auth, content: '{"scoreMaximum":5,"label":"Essay"}');

        $this->client->request('GET', $base.'/lineitems?limit=1', server: $auth);

        $page = $this->client->getResponse();
        self::assertCount(1, $this->json((string) $page->getContent()));
        self::assertMatchesRegularExpression('#^<https://localhost/lti/platform/contexts/course-1/lineitems\?limit=1&offset=1>; rel="next"$#', (string) $page->headers->get('Link'));
        $this->client->request('GET', $base.'/lineitems?tag=quiz', server: $auth);
        self::assertCount(1, $this->json((string) $this->client->getResponse()->getContent()));
        self::assertNull($this->client->getResponse()->headers->get('Link'));

        $this->client->request('GET', $url, server: $auth);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->client->request('PUT', $url, server: $auth, content: '{"scoreMaximum":20,"label":"Quiz 2"}');
        self::assertSame('Quiz 2', $this->json((string) $this->client->getResponse()->getContent())['label'] ?? null);
        $this->client->request('PUT', $url, server: $auth, content: '{"label":"x"}');
        self::assertSame(400, $this->client->getResponse()->getStatusCode());

        $score = '{"userId":"sub-ada","scoreGiven":15,"scoreMaximum":20,"activityProgress":"Completed","gradingProgress":"FullyGraded","timestamp":"2026-11-14T12:00:00.000Z"}';
        $this->client->request('POST', $url.'/scores', server: $auth, content: $score);
        self::assertSame(204, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
        $this->client->request('POST', $url.'/scores', server: $auth, content: str_replace('12:00:00', '11:00:00', $score));
        self::assertSame(409, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', $url.'/scores', server: $auth, content: '{"userId":"x"}');
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', $url.'/scores', server: $auth, content: '[1]');
        self::assertStringContainsString('The body is not a JSON object.', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', $url.'/results', server: $auth);
        $results = $this->json((string) $this->client->getResponse()->getContent());
        self::assertSame([[
            'id' => $url.'/results?user_id=sub-ada',
            'scoreOf' => $url,
            'userId' => 'sub-ada',
            'resultScore' => 15.0,
            'resultMaximum' => 20.0,
        ]], $results);

        $this->client->request('GET', $base.'/memberships?limit=2&role=Learner', server: $auth);
        $members = $this->client->getResponse();
        self::assertSame('application/vnd.ims.lti-nrps.v2.membershipcontainer+json', $members->headers->get('Content-Type'));
        $body = $this->json((string) $members->getContent());
        self::assertIsArray($body['members'] ?? null);
        self::assertSame($base.'/memberships', $body['id']);
        self::assertSame(['id' => 'course-1', 'label' => 'CS101'], $body['context']);
        self::assertSame(['m1', 'm3'], array_column($body['members'], 'user_id'));
        self::assertNull($members->headers->get('Link'));
        $this->client->request('GET', $base.'/memberships?limit=1', server: $auth);
        self::assertSame('<'.$base.'/memberships?limit=1&offset=1>; rel="next"', $this->client->getResponse()->headers->get('Link'));

        $this->client->request('DELETE', $url, server: $auth);
        self::assertSame(204, $this->client->getResponse()->getStatusCode());
        $this->client->request('DELETE', $url, server: $auth);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        $this->client->request('GET', $url, server: $auth);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());

        $events = array_map(static fn (object $event): string => $event::class, $this->service(RecordingListener::class)->events);
        self::assertSame([LineItemSaved::class, LineItemSaved::class, LineItemSaved::class, ScoreReceived::class, LineItemDeleted::class], $events);
    }

    public function testAScoreIsKeptAndToldWithATokenOfTheScoreScopeAlone(): void
    {
        $base = 'https://localhost/lti/platform/contexts/course-1';
        $this->client->request('POST', $base.'/lineitems', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token(Scope::LINE_ITEM)], content: '{"scoreMaximum":10,"label":"Quiz"}');
        $url = $this->json((string) $this->client->getResponse()->getContent())['id'] ?? null;
        self::assertIsString($url);

        $score = '{"userId":"sub-ada","scoreGiven":7,"scoreMaximum":10,"activityProgress":"Completed","gradingProgress":"FullyGraded","timestamp":"2026-11-14T12:00:00.000Z"}';
        $this->client->request('POST', $url.'/scores', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token(Scope::SCORE)], content: $score);

        self::assertSame(204, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
        $received = array_values(array_filter($this->service(RecordingListener::class)->events, static fn (object $event): bool => $event instanceof ScoreReceived));
        self::assertCount(1, $received);
        self::assertSame('Quiz', $received[0]->lineItem->lineItem->label);
        self::assertSame(7.0, $received[0]->score->scoreGiven);

        $this->client->request('GET', $url.'/results', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token(Scope::RESULT_READ_ONLY)]);
        $results = $this->json((string) $this->client->getResponse()->getContent());
        self::assertIsArray($results[0] ?? null);
        self::assertSame(7.0, $results[0]['resultScore'] ?? null);
    }

    public function testTheServicesRefuseWhatTheTokenDoesNotAllow(): void
    {
        $base = 'https://localhost/lti/platform/contexts/course-1';

        foreach ([[], ['HTTP_AUTHORIZATION' => 'Bearer unknown-token-of-sixteen']] as $server) {
            $this->client->request('GET', $base.'/lineitems', server: $server);
            self::assertSame(401, $this->client->getResponse()->getStatusCode());
            self::assertSame('Bearer error="invalid_token"', $this->client->getResponse()->headers->get('WWW-Authenticate'));
        }

        $token = $this->token(Scope::MEMBERSHIP_READ_ONLY);
        $auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
        $this->client->request('GET', $base.'/lineitems', server: $auth);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame('Bearer error="insufficient_scope"', $this->client->getResponse()->headers->get('WWW-Authenticate'));

        foreach (['POST /lineitems', 'GET /lineitems/'.str_repeat('a', 32), 'PUT /lineitems/'.str_repeat('a', 32), 'DELETE /lineitems/'.str_repeat('a', 32), 'POST /lineitems/'.str_repeat('a', 32).'/scores', 'GET /lineitems/'.str_repeat('a', 32).'/results'] as $call) {
            [$method, $path] = explode(' ', $call);
            $this->client->request($method, $base.$path, server: $auth, content: '{}');
            self::assertSame(403, $this->client->getResponse()->getStatusCode(), $call);
        }

        $this->client->request('GET', 'https://localhost/lti/platform/contexts/course-9/memberships', server: $auth);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());

        $all = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token()];
        $this->client->request('GET', 'https://localhost/lti/platform/contexts/course-9/lineitems', server: $all);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', 'https://localhost/lti/platform/contexts/course-9/lineitems', server: $all, content: '{"scoreMaximum":1,"label":"x"}');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', $base.'/lineitems', server: $all, content: 'nope');
        self::assertSame('The body is not a line item: it is not a JSON object.', $this->client->getResponse()->getContent());
        $this->client->request('POST', $base.'/lineitems', server: $all, content: '{"label":"x"}');
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', $base.'/lineitems/'.str_repeat('a', 32).'/scores', server: $all, content: '{"userId":"u","activityProgress":"Completed","gradingProgress":"FullyGraded","timestamp":"2026-11-14T12:00:00Z"}');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        $this->client->request('GET', $base.'/lineitems/'.str_repeat('a', 32).'/results', server: $all);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        $this->client->request('PUT', $base.'/lineitems/'.str_repeat('a', 32), server: $all, content: '{"scoreMaximum":1,"label":"x"}');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', $base.'/lineitems', server: $all, content: str_repeat(' ', ServiceResponses::MAX_BODY + 1));
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testTheTokenEndpointAnswersAsOauth(): void
    {
        $this->client->request('POST', 'https://localhost/lti/platform/token', ['grant_type' => 'password']);
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame(['error' => 'unsupported_grant_type', 'error_description' => 'Only the grant "client_credentials" is supported.'], json_decode((string) $this->client->getResponse()->getContent(), true, 4, \JSON_THROW_ON_ERROR));
        self::assertSame('no-cache', $this->client->getResponse()->headers->get('Pragma'));

        $this->client->request('POST', 'https://localhost/lti/platform/token', ['grant_type' => 'client_credentials', 'client_assertion_type' => AccessTokenProvider::ASSERTION_TYPE, 'client_assertion' => 'a.b.c']);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testThePageThatPostsALaunchSubmitsItselfByAScriptOfItsOwnNonce(): void
    {
        $post = new FormPost(Url::fromString('https://tool.example.com:8443/launch'), ['id_token' => 'a.b.c', 'state' => 's"<']);
        $responder = $this->service(FormPostResponder::class);
        $page = $responder->respond($post);
        $other = $responder->respond($post);

        $policy = (string) $page->headers->get('Content-Security-Policy');
        self::assertSame(1, preg_match("#^script-src 'nonce-([A-Za-z0-9+/]{24})'; form-action https://tool\\.example\\.com:8443; frame-ancestors 'self' https://tool\\.example\\.com:8443$#", $policy, $match), $policy);
        $content = (string) $page->getContent();
        self::assertSame(1, substr_count($content, '<script nonce="'.$match[1].'">'));
        self::assertStringContainsString('form.submit();', $content);
        self::assertStringContainsString('})(document.currentScript.previousElementSibling);', $content);
        self::assertMatchesRegularExpression('#</form>\s*<script nonce=#', $content);
        self::assertStringContainsString('<input type="hidden" name="state" value="s&quot;&lt;">', $content);
        self::assertStringContainsString('type="submit"', $content);
        self::assertStringNotContainsString($match[1], (string) $other->getContent());
        self::assertStringNotContainsString($match[1], (string) $other->headers->get('Content-Security-Policy'));
        self::assertSame(200, $page->getStatusCode());
        self::assertSame(422, $responder->respond($post, 422)->getStatusCode());
    }

    public function testTheToolAnswersADeepLinkingRequest(): void
    {
        $settings = new DeepLinkingSettings(Url::fromString('https://localhost/lti/platform/deep-linking', true), ['link'], data: 'placement-7');
        $launch = new Launch(TestKernel::ISSUER, TestKernel::CLIENT, new LaunchMessage(MessageType::DeepLinking, '1', Url::fromString('https://localhost/course/1', true), new Person('sub-ada'), Roles::of([]), deepLinking: $settings), []);
        $post = $this->service(DeepLinkingResponder::class)->respond($launch, [new LinkItem(Url::fromString('https://tool.example.com/page'), 'Page')], 'Added');
        $page = $this->service(FormPostResponder::class)->respond($post);

        self::assertStringContainsString('name="JWT"', (string) $page->getContent());
        $this->client->request('POST', $post->action->toString(), $post->fields);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('One item was picked.', (string) $response->getContent());
        self::assertStringContainsString('Added', (string) $response->getContent());

        $returned = array_values(array_filter($this->service(RecordingListener::class)->events, static fn (object $event): bool => $event instanceof DeepLinkingReturned));
        self::assertCount(1, $returned);
        self::assertSame('placement-7', $returned[0]->result->data);
    }

    public function testADeepLinkingResponseWhoseHandlingFailedIsResumedWithoutItsToken(): void
    {
        $settings = new DeepLinkingSettings(Url::fromString('https://localhost/lti/platform/deep-linking', true), ['link'], data: 'placement-8');
        $launch = new Launch(TestKernel::ISSUER, TestKernel::CLIENT, new LaunchMessage(MessageType::DeepLinking, '1', Url::fromString('https://localhost/course/1', true), new Person('sub-ada'), Roles::of([]), deepLinking: $settings), []);
        $post = $this->service(DeepLinkingResponder::class)->respond($launch, [new LinkItem(Url::fromString('https://tool.example.com/page'), 'Page')], 'Added');
        $nonce = Jwt::parse($post->fields['JWT'])->claims['nonce'] ?? null;
        self::assertIsString($nonce);
        $key = DeepLinkingResult::key(TestKernel::CLIENT, $nonce);
        $listener = $this->service(RecordingListener::class);
        $listener->failure = new RuntimeException('The gradebook is down.');

        $this->client->catchExceptions(true);
        $this->client->request('POST', $post->action->toString(), $post->fields);
        self::assertSame(500, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', $post->action->toString(), $post->fields);
        self::assertSame(400, $this->client->getResponse()->getStatusCode(), 'The token is taken once.');
        self::assertStringContainsString('already used', (string) $this->client->getResponse()->getContent());

        $kept = $this->service(DeepLinkingReturns::class)->find($key, new DateTimeImmutable());
        self::assertNotNull($kept);
        self::assertNull($kept->handledAt);

        $tester = new CommandTester(new Application($this->client->getKernel())->find('phil:lti:resume-deep-linking'));
        self::assertSame(0, $tester->execute(['key' => $key]));
        self::assertStringContainsString('Handled 1 responses again; 0 failed.', $this->flat($tester->getDisplay()));

        $returned = array_values(array_filter($listener->events, static fn (object $event): bool => $event instanceof DeepLinkingReturned));
        self::assertCount(2, $returned);
        self::assertSame([$key, false, 'placement-8'], [$returned[0]->key, $returned[0]->resumed, $returned[0]->result->data]);
        self::assertSame([$key, true, 'placement-8'], [$returned[1]->key, $returned[1]->resumed, $returned[1]->result->data]);
        self::assertEquals($returned[0]->result, $returned[1]->result);
        self::assertNotNull($this->service(DeepLinkingReturns::class)->find($key, new DateTimeImmutable())?->handledAt);
    }

    public function testTheLineItemsArePagedByDefaultAndAtTheMaximum(): void
    {
        $store = $this->service(LineItemStore::class);
        $connection = $this->service('doctrine.dbal.default_connection');
        $this->assertInstanceOf(Connection::class, $connection);
        $connection->transactional(static function () use ($store): void {
            for ($i = 0; $i <= 1000; ++$i) {
                $store->save(new StoredLineItem(\sprintf('%032x', $i), TestKernel::CLIENT, 'course-1', new LineItem(1.0, 'L', tag: 0 === $i % 2 ? 'even' : 'odd')));
            }
        });

        $base = 'https://localhost/lti/platform/contexts/course-1';
        $auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token(Scope::LINE_ITEM_READ_ONLY)];
        $this->client->request('GET', $base.'/lineitems', server: $auth);
        $page = $this->client->getResponse();
        self::assertSame(200, $page->getStatusCode());
        self::assertCount(100, $this->json((string) $page->getContent()));
        self::assertSame('<'.$base.'/lineitems?limit=100&offset=100>; rel="next"', $page->headers->get('Link'));

        $this->client->request('GET', $base.'/lineitems?limit=5000&tag=even', server: $auth);
        self::assertCount(501, $this->json((string) $this->client->getResponse()->getContent()));
        self::assertNull($this->client->getResponse()->headers->get('Link'));

        $this->client->request('GET', $base.'/lineitems?limit=5000', server: $auth);
        self::assertCount(1000, $this->json((string) $this->client->getResponse()->getContent()));
        self::assertSame('<'.$base.'/lineitems?limit=1000&offset=1000>; rel="next"', $this->client->getResponse()->headers->get('Link'));

        $this->client->request('GET', $base.'/lineitems?limit=1000&offset=1000', server: $auth);
        self::assertCount(1, $this->json((string) $this->client->getResponse()->getContent()));
        self::assertNull($this->client->getResponse()->headers->get('Link'));

        $this->client->request('GET', $base.'/lineitems?limit=2&tag=odd&resource_id=&offset=4', server: $auth);
        self::assertSame('<'.$base.'/lineitems?tag=odd&limit=2&offset=6>; rel="next"', $this->client->getResponse()->headers->get('Link'));
    }

    /**
     * The output of a command on one line, as wide as the terminal that runs the test may be.
     */
    private function flat(string $display): string
    {
        return (string) preg_replace('/\s+/', ' ', $display);
    }

    private function token(string ...$scopes): string
    {
        $now = time();
        $assertion = JwtSigner::sign(['iss' => TestKernel::CLIENT, 'sub' => TestKernel::CLIENT, 'aud' => 'https://localhost/lti/platform/token', 'iat' => $now, 'exp' => $now + 60, 'jti' => sodium_bin2hex(random_bytes(8))], $this->service(KeyStore::class)->signingKey());
        $this->client->request('POST', 'https://localhost/lti/platform/token', [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => AccessTokenProvider::ASSERTION_TYPE,
            'client_assertion' => $assertion,
            'scope' => implode(' ', [] === $scopes ? Scope::ALL : $scopes),
        ]);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $body = $this->json((string) $response->getContent());
        self::assertSame('Bearer', $body['token_type'] ?? null);
        self::assertIsString($body['access_token'] ?? null);

        return $body['access_token'];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(string $content): array
    {
        $data = json_decode($content, true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $id
     *
     * @return ($id is class-string<T> ? T : object)
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }

    /**
     * @param array<string, string> $changes
     *
     * @return array<string, string>
     */
    private function authentication(array $changes = []): array
    {
        return array_filter($changes + ['scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post', 'prompt' => 'none', 'client_id' => TestKernel::CLIENT, 'redirect_uri' => 'https://localhost/lti/launch', 'login_hint' => 'hint-ada', 'nonce' => 'n', 'state' => 's', 'lti_message_hint' => 'link-1'], static fn (string $value): bool => '' !== $value);
    }
}
