<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Infrastructure\Doctrine\DoctrineThreadRepository;
use Phil\CommentsBundle\Infrastructure\Http\ClientConfig;
use Phil\CommentsBundle\PhilCommentsBundle;
use Phil\CommentsBundle\Tests\Functional\App\TestKernel;
use Phil\CommentsBundle\Tests\Unit\Fixture\Json;
use Phil\Resource\ResourceReference;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Two writers and an editor on one page, through the routes and the Doctrine stores on SQLite.
 */
#[CoversClass(PhilCommentsBundle::class)]
final class CommentsBundleTest extends WebTestCase
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

    public function testAReviewOfAPage(): void
    {
        $this->service(ThreadRepository::class, DoctrineThreadRepository::class);
        $config = $this->service(ClientConfig::class, ClientConfig::class)->for(ResourceReference::of('page', '1'), ResourceReference::of('user', 'ada'));
        self::assertSame(['/_comments/threads', '/_comments/threads/__id__/reply', '/_comments/comments/__id__/delete'], [Json::at($config, 'urls.threads'), Json::at($config, 'urls.reply'), Json::at($config, 'urls.delete')]);
        self::assertSame('csrf-token', Json::at($config, 'csrfCookie'));

        [$status, $opened] = $this->send('ada', 'POST', '/_comments/threads', ['subject' => 'page:1', 'anchor' => ['block' => 'b1', 'field' => 'text', 'start' => 4, 'quote' => 'cat', 'prefix' => 'the ', 'suffix' => ' sat'], 'body' => 'A dog?', 'suggestion' => 'dog']);
        self::assertSame(201, $status);
        $id = Json::at($opened, 'thread.id');
        self::assertIsString($id);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/D', $id);

        self::assertSame(201, $this->send('bob', 'POST', '/_comments/threads/'.$id.'/reply', ['body' => 'Cats are fine.'])[0]);
        self::assertSame(403, $this->send('bob', 'POST', '/_comments/threads/'.$id.'/accept')[0]);
        self::assertSame(404, $this->send('bob', 'POST', '/_comments/threads/nope/resolve')[0]);
        self::assertSame(401, $this->send(null, 'GET', '/_comments/threads?subject=page:1')[0]);

        [, $list] = $this->send('ed', 'GET', '/_comments/threads?subject=page:1');
        self::assertSame([['A dog?', 'Cats are fine.']], [Json::column($list, 'threads.0.comments', 'body')]);
        self::assertSame(['resolve' => true, 'decide' => true, 'reply' => true], Json::at($list, 'threads.0.may'));

        $comments = $this->service(Comments::class, Comments::class);
        self::assertSame(['updated' => 1, 'detached' => 0], $comments->reanchorDocument(ResourceReference::of('page', '1'), new Document([new Block('b1', 'paragraph', ['text' => 'Today the <b>cat</b> sat'])])));

        $document = $comments->applySuggestion($id, ResourceReference::of('user', 'ed'), new Document([new Block('b1', 'paragraph', ['text' => 'Today the <b>cat</b> sat'])]));
        self::assertSame('Today the <b>dog</b> sat', $document->blocks[0]->data['text']);

        $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class)->clear();
        [, $after] = $this->send('ed', 'GET', '/_comments/threads?subject=page:1');
        self::assertSame(['resolved', 'accepted', 10, ['ref' => 'user:ed', 'name' => 'user:ed']], [Json::at($after, 'threads.0.status'), Json::at($after, 'threads.0.suggestion.state'), Json::at($after, 'threads.0.anchor.start'), Json::at($after, 'threads.0.resolvedBy')]);

        $reply = Json::at($after, 'threads.0.comments.1.id');
        self::assertIsString($reply);
        self::assertSame(403, $this->send('ada', 'POST', '/_comments/comments/'.$reply.'/edit', ['body' => 'No'])[0]);
        self::assertSame('Moderated', Json::at($this->send('ed', 'POST', '/_comments/comments/'.$reply.'/edit', ['body' => 'Moderated'])[1], 'thread.comments.1.body'));
        self::assertSame('open', Json::at($this->send('ada', 'POST', '/_comments/threads/'.$id.'/reopen')[1], 'thread.status'));
        self::assertSame($id, Json::at($this->send('bob', 'POST', '/_comments/comments/'.$reply.'/delete')[1], 'thread.id'));
    }

    public function testAChangeWithoutItsCsrfTokenIsRefusedAndAReadNeedsNone(): void
    {
        $this->client->request('GET', '/_comments/threads?subject=page:1', server: ['HTTP_X_PHIL_COMMENTS' => '1', 'HTTP_X_TEST_AUTHOR' => 'ada']);
        self::assertResponseIsSuccessful('a read carries no token');

        $this->client->getHistory()->clear();
        $this->client->request('POST', '/_comments/threads', server: ['HTTP_X_PHIL_COMMENTS' => '1', 'HTTP_X_TEST_AUTHOR' => 'ada', 'CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(403, 'no token');

        $this->client->getHistory()->clear();
        $this->client->request('POST', '/_comments/threads', server: ['HTTP_X_PHIL_COMMENTS' => '1', 'HTTP_X_TEST_AUTHOR' => 'ada', 'HTTP_CSRF_TOKEN' => self::TOKEN, 'CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(403, 'a token without its cookie');
        self::assertSame(400, $this->send('ada', 'POST', '/_comments/threads')[0], 'a change with its token reaches the endpoint');
        self::assertSame([], $this->client->getCookieJar()->allValues('http://localhost/'), 'the token cookie is cleared once checked');
    }

    /**
     * A request as the controller sends it: its header, and a new token in the header and the cookie of Symfony's
     * stateless CSRF check.
     *
     * @param array<string, mixed> $body
     *
     * @return array{int, mixed}
     */
    private function send(?string $author, string $method, string $uri, array $body = []): array
    {
        $server = ['HTTP_X_PHIL_COMMENTS' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN, 'CONTENT_TYPE' => 'application/json'];

        if (null !== $author) {
            $server['HTTP_X_TEST_AUTHOR'] = $author;
        }

        $this->client->getHistory()->clear();
        $this->client->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));
        $this->client->request($method, $uri, server: $server, content: 'GET' === $method ? null : (string) json_encode($body));
        $response = $this->client->getResponse();
        $decoded = json_decode((string) $response->getContent(), true);

        return [$response->getStatusCode(), $decoded];
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
}
