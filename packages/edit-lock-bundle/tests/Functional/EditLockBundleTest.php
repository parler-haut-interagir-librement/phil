<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Infrastructure\Doctrine\DoctrineLeaseRepository;
use Phil\EditLockBundle\Infrastructure\Http\ClientConfig;
use Phil\EditLockBundle\Infrastructure\Mercure\TurboStreams;
use Phil\EditLockBundle\PhilEditLockBundle;
use Phil\EditLockBundle\Tests\Functional\App\RecordingHub;
use Phil\EditLockBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mercure\Update;

/**
 * Two writers on one page, through the routes, the Doctrine store on SQLite and the broadcaster.
 */
#[CoversClass(PhilEditLockBundle::class)]
final class EditLockBundleTest extends WebTestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private KernelBrowser $client;

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

        $this->client = self::createClient();
        $this->client->disableReboot();

        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testTwoWritersOnOnePage(): void
    {
        $this->service(LeaseRepository::class, DoctrineLeaseRepository::class);

        self::assertSame([200, 'bob'], $this->post('bob', 'acquire', ['resource' => 'page:1', 'part' => 'b1'], 'lease.holder.id'));
        self::assertSame([409, 'bob'], $this->post('cy', 'acquire', ['resource' => 'page:1', 'part' => 'b1'], 'lease.holder.id'));
        self::assertSame([200, 'cy'], $this->post('cy', 'acquire', ['resource' => 'page:1', 'part' => 'b2'], 'lease.holder.id'));
        self::assertSame([204, null], $this->post('bob', 'save', ['resource' => 'page:1', 'block' => ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => '<strong>Hi</strong>']], 'after' => 'b0']));
        self::assertSame([409, 'lost'], $this->post('cy', 'save', ['resource' => 'page:1', 'block' => ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => 'x']]], 'error'));

        $this->service('clock', MockClock::class)->sleep(10);
        self::assertSame([200, 30], $this->post('bob', 'heartbeat', ['resource' => 'page:1', 'part' => 'b1'], 'lease.expiresIn'));

        $this->token();
        $this->client->request('GET', '/_edit-lock/presence?resource=page:1', server: ['HTTP_X_PHIL_EDIT_LOCK' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN, 'HTTP_X_USER' => 'cy']);
        self::assertSame(['leases' => [
            ['resource' => 'page:1', 'part' => 'b1', 'holder' => ['id' => 'bob', 'name' => 'Bob'], 'expiresIn' => 30, 'mine' => false],
            ['resource' => 'page:1', 'part' => 'b2', 'holder' => ['id' => 'cy', 'name' => 'Cy'], 'expiresIn' => 20, 'mine' => true],
        ]], $this->json());

        self::assertSame([403, 'refused'], $this->post('cy', 'take-over', ['resource' => 'page:1', 'part' => 'b1'], 'error'));
        self::assertSame([200, 'ada'], $this->post('ada', 'take-over', ['resource' => 'page:1', 'part' => 'b1'], 'lease.holder.id'));
        self::assertSame([204, null], $this->post('ada', 'remove', ['resource' => 'page:1', 'id' => 'b1']));
        self::assertSame([204, null], $this->post('cy', 'leave', ['resource' => 'page:1']));
        self::assertSame([204, null], $this->post('ada', 'release', ['resource' => 'page:1', 'part' => 'b1']));
        self::assertSame([403, 'refused'], $this->post('eve', 'acquire', ['resource' => 'page:1'], 'error'));
        self::assertSame([401, 'anonymous'], $this->post(null, 'acquire', ['resource' => 'page:1'], 'error'));

        $hub = $this->service(RecordingHub::class, RecordingHub::class);
        $streams = array_map(static fn (Update $update): string => $update->getData(), $hub->updates);
        self::assertSame(array_fill(0, \count($hub->updates), ['urn:phil:edit-lock:page:1']), array_map(static fn (Update $update): array => $update->getTopics(), $hub->updates));
        self::assertTrue($hub->updates[0]->isPrivate());
        self::assertSame([
            'update', 'update', 'replace', 'update', 'update', 'remove', 'update', 'update',
        ], array_map(static fn (string $stream): string => (string) preg_replace('/^<turbo-stream action="([a-z]+)".*$/s', '$1', $stream), $streams));
        self::assertStringContainsString('<li data-holder="bob" data-part="b1" data-expires-in="30">Bob</li>', $streams[0]);
        self::assertStringContainsString('data-after="b0" data-ujo="{&quot;blocks&quot;:[{&quot;id&quot;:&quot;b1&quot;,&quot;type&quot;:&quot;paragraph&quot;,&quot;data&quot;:{&quot;text&quot;:&quot;&lt;b&gt;Hi&lt;/b&gt;&quot;}}]}"', $streams[2]);
        self::assertStringContainsString('<li data-holder="ada" data-part="b1" data-expires-in="30">Ada</li>', $streams[4]);
        self::assertSame('<turbo-stream action="update" target="'.TurboStreams::presenceTarget('page:1').'"><template><ul class="phil-edit-lock-presence"></ul></template></turbo-stream>', $streams[7]);
    }

    public function testTheControllerConfiguration(): void
    {
        $config = $this->service(ClientConfig::class, ClientConfig::class);
        $locks = $this->service(EditLocks::class, EditLocks::class);

        self::assertSame([
            'resource' => 'page:1',
            'holder' => ['id' => 'bob', 'name' => 'Bob'],
            'seconds' => 30,
            'takeOver' => false,
            'urls' => [
                'acquire' => '/_edit-lock/acquire',
                'heartbeat' => '/_edit-lock/heartbeat',
                'release' => '/_edit-lock/release',
                'takeOver' => '/_edit-lock/take-over',
                'leave' => '/_edit-lock/leave',
                'presence' => '/_edit-lock/presence',
                'save' => '/_edit-lock/save',
                'remove' => '/_edit-lock/remove',
            ],
            'topic' => 'urn:phil:edit-lock:page:1',
            'hub' => 'https://hub.test/.well-known/mercure?match=urn%3Aphil%3Aedit-lock%3Apage%3A1',
            'csrfCookie' => 'csrf-token',
        ], $config->for('page:1', Holder::of('bob', 'Bob')));
        self::assertSame(30, $locks->seconds());
    }

    public function testARequestWithoutItsCsrfTokenIsRefused(): void
    {
        $this->client->request('POST', '/_edit-lock/acquire', server: ['HTTP_X_PHIL_EDIT_LOCK' => '1', 'HTTP_X_USER' => 'bob', 'CONTENT_TYPE' => 'application/json'], content: '{"resource":"page:1"}');
        self::assertResponseStatusCodeSame(403, 'no token');

        $this->client->getHistory()->clear();
        $this->client->request('POST', '/_edit-lock/acquire', server: ['HTTP_X_PHIL_EDIT_LOCK' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN, 'HTTP_X_USER' => 'bob', 'CONTENT_TYPE' => 'application/json'], content: '{"resource":"page:1"}');
        self::assertResponseStatusCodeSame(403, 'a token without its cookie');

        self::assertSame(200, $this->post('bob', 'acquire', ['resource' => 'page:1'])[0]);
        self::assertSame([], $this->client->getCookieJar()->allValues('http://localhost/'), 'the token cookie is cleared once checked');
    }

    /**
     * A new token in the cookie of Symfony's stateless CSRF check, as the controller sets it, and no page before.
     */
    private function token(): void
    {
        $this->client->getHistory()->clear();
        $this->client->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{int, mixed}
     */
    private function post(?string $user, string $endpoint, array $body, ?string $path = null): array
    {
        $server = ['HTTP_X_PHIL_EDIT_LOCK' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN, 'CONTENT_TYPE' => 'application/json'];

        if (null !== $user) {
            $server['HTTP_X_USER'] = $user;
        }

        $this->token();
        $this->client->request('POST', '/_edit-lock/'.$endpoint, server: $server, content: json_encode($body, \JSON_THROW_ON_ERROR));
        $status = $this->client->getResponse()->getStatusCode();

        if (null === $path) {
            return [$status, '' === (string) $this->client->getResponse()->getContent() ? null : $this->json()];
        }

        $value = $this->json();

        foreach (explode('.', $path) as $key) {
            $value = \is_array($value) ? $value[$key] ?? null : null;
        }

        return [$status, $value];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(): array
    {
        $json = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);

        return $json;
    }
}
