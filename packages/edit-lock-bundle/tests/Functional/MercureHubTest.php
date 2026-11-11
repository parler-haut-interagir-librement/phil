<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\EditLockBundle\PhilEditLockBundle;
use Phil\EditLockBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;

/**
 * Presence pushed through a real Mercure hub and read back by a subscriber. Skipped unless a hub answers at
 * `MERCURE_URL`, by default `http://127.0.0.1:3999/.well-known/mercure`, with the test secret for both keys:
 *
 *     docker run -d -p 3999:80 -e SERVER_NAME=':80' \
 *         -e MERCURE_PUBLISHER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
 *         -e MERCURE_SUBSCRIBER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' dunglas/mercure
 */
#[CoversClass(PhilEditLockBundle::class)]
final class MercureHubTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testPresenceReachesASubscriber(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        $url = getenv('MERCURE_URL');
        $hub = false === $url ? TestKernel::HUB : $url;
        $topic = 'urn:phil:edit-lock:page:'.bin2hex(random_bytes(4));
        $token = new LcobucciFactory(TestKernel::SECRET)->create(
            [new Grant([Grant::ACTION_SUBSCRIBE], [$topic])],
            ['iss' => 'https://localhost', 'sub' => 'test', 'client_id' => 'test', 'aud' => [$hub]],
        );
        $context = stream_context_create(['http' => ['header' => 'Authorization: Bearer '.$token, 'timeout' => 3, 'ignore_errors' => true]]);
        $stream = @fopen($hub.'?match='.rawurlencode($topic), 'r', false, $context);

        if (false === $stream) {
            self::markTestSkipped('No Mercure hub answers at '.$hub.'.');
        }

        stream_set_timeout($stream, 3);
        $client = self::createClient(['environment' => 'mercure']);
        $client->disableReboot();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $client->getCookieJar()->set(new Cookie('csrf-token_a-token-of-24-characters', 'csrf-token', null, '/', 'localhost'));
        $client->request('POST', '/_edit-lock/acquire', server: ['HTTP_X_PHIL_EDIT_LOCK' => '1', 'HTTP_CSRF_TOKEN' => 'a-token-of-24-characters', 'HTTP_X_USER' => 'bob', 'CONTENT_TYPE' => 'application/json'], content: json_encode(['resource' => substr($topic, \strlen('urn:phil:edit-lock:')), 'part' => 'b1'], \JSON_THROW_ON_ERROR));
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $received = '';

        while (!str_contains($received, "\n\n") && false !== ($line = fgets($stream))) {
            $received .= str_starts_with($line, ':') ? '' : $line;
        }

        fclose($stream);
        self::assertStringContainsString('data: <turbo-stream action="update" target="phil-edit-lock-presence-', $received);
        self::assertStringContainsString('<li data-holder="bob" data-part="b1" data-expires-in="30">Bob</li>', $received);
    }
}
