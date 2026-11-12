<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\KeyManagement\Envelope;

/**
 * The bundle in an application: a form page whose tag carries the controller, the status template in French, drafts
 * saved, read, refused and discarded through the endpoints, sealed in their table, and a valid submission that tells
 * the browser to forget the draft and deletes the server's copy.
 */
#[CoversNothing]
final class FormsAutosaveTest extends WebTestCase
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
        if (!class_exists(EventDispatcher::class)) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        $this->client = self::createClient();
        $this->client->disableReboot();

        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema([$manager->getClassMetadata(Draft::class)]);
    }

    public function testAFormKeptAsADraftFromThePageToItsSubmission(): void
    {
        $crawler = $this->client->request('GET', '/posts/7', server: ['HTTP_X_USER' => 'ada']);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form');
        $prefix = 'data-phil--forms-autosave--autosave-';

        self::assertSame('phil--forms-autosave--autosave', $form->attr('data-controller'));
        $key = (string) $form->attr($prefix.'key-value');
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}$/D', $key);
        self::assertSame('/_autosave/drafts/'.$key, $form->attr($prefix.'endpoint-value'));
        self::assertSame('csrf-token', $form->attr($prefix.'csrf-value'));
        $labels = json_decode((string) $form->attr($prefix.'labels-value'), true, 2, \JSON_THROW_ON_ERROR);
        self::assertIsArray($labels);
        self::assertSame('Brouillon gardé.', $labels['savedServer']);
        self::assertSame('Abandonner le brouillon', trim($crawler->filter('[data-phil--forms-autosave--autosave-target="discard"]')->text()));
        self::assertSame('polite', $crawler->filter('[data-phil--forms-autosave--autosave-target="status"]')->attr('aria-live'));
        self::assertSame('off', $crawler->filter('#post_note')->attr('data-phil-autosave'));
        self::assertNotSame($key, $this->client->request('GET', '/posts/8', server: ['HTTP_X_USER' => 'ada'])->filter('form')->attr($prefix.'key-value'));
        self::assertNull($this->client->request('GET', '/posts/7')->filter('form')->attr($prefix.'endpoint-value'), 'a visitor keeps drafts in the browser alone');

        self::assertSame([200, ['revision' => 1]], $this->api('PUT', $key, ['revision' => null, 'version' => 'v1', 'fields' => ['post[title]' => ['Draft']]]));
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $row = $connection->fetchAssociative('SELECT owner, payload FROM '.Draft::TABLE);
        self::assertIsArray($row);
        self::assertIsString($row['payload']);
        self::assertIsString($row['owner']);
        self::assertSame('d1', Envelope::fromBytes((string) base64_decode($row['payload'], true))->keyId);
        self::assertStringNotContainsString('Draft', $row['payload']);
        self::assertStringNotContainsString('ada', $row['owner']);
        [$status, $read] = $this->api('GET', $key);
        self::assertSame(200, $status);
        self::assertIsArray($read);
        self::assertSame(['post[title]' => ['Draft']], $read['fields']);
        self::assertSame([204, null], $this->api('GET', $key, user: 'bob'));
        self::assertSame(403, $this->api('PUT', $key, ['version' => 'v1', 'fields' => []], token: false)[0]);
        self::assertSame(409, $this->api('PUT', $key, ['revision' => null, 'version' => 'v1', 'fields' => []])[0]);

        $this->client->request('POST', '/posts/7', ['post' => ['title' => '', 'body' => 'x']], server: ['HTTP_X_USER' => 'ada']);
        self::assertResponseIsSuccessful();
        self::assertSame('true', $this->client->getCrawler()->filter('form')->attr($prefix.'invalid-value'));
        self::assertSame(200, $this->api('GET', $key)[0], 'an invalid submission keeps the draft');

        $this->client->request('POST', '/posts/7', ['post' => ['title' => 'Sent', 'body' => 'x']], server: ['HTTP_X_USER' => 'ada']);
        self::assertResponseRedirects('/done');
        self::assertSame($key, $this->client->getResponse()->headers->getCookies()[0]->getValue());
        self::assertSame([204, null], $this->api('GET', $key));
    }

    public function testDiscardingAndThePurge(): void
    {
        $key = 'abcdefghijklmnopqrstuvwxyz012345';
        $this->api('PUT', $key, ['version' => 'v', 'fields' => []]);
        self::assertSame([204, null], $this->api('DELETE', $key));
        self::assertSame([204, null], $this->api('GET', $key));
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array{int, mixed}
     */
    private function api(string $method, string $key, ?array $body = null, string $user = 'ada', bool $token = true): array
    {
        $server = ['HTTP_X_USER' => $user, 'HTTP_X_PHIL_FORMS_AUTOSAVE' => '1', 'CONTENT_TYPE' => 'application/json'];

        if ($token) {
            $server['HTTP_CSRF_TOKEN'] = self::TOKEN;
            $this->client->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token'));
        } else {
            $this->client->getCookieJar()->clear();
        }

        $this->client->request($method, '/_autosave/drafts/'.$key, server: $server, content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));
        $response = $this->client->getResponse();
        $content = (string) $response->getContent();

        return [$response->getStatusCode(), '' === $content ? null : json_decode($content, true, 512, \JSON_THROW_ON_ERROR)];
    }
}
