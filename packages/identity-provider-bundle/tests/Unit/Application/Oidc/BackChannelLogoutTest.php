<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application\Oidc;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\LogoutNotifier;
use Phil\IdentityProviderBundle\Application\Exception\DeliveryFailed;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\LogoutTokens;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\MetadataController;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Messenger\MessengerLogoutNotifier;
use Phil\IdentityProviderBundle\Infrastructure\Messenger\SendLogoutToken;
use Phil\IdentityProviderBundle\Infrastructure\Messenger\SendLogoutTokenHandler;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The back-channel logout the provider emits: a session that ends tells the clients it issued tokens to, one message
 * each with identifiers only, and each message posts a signed logout token to the client's address.
 */
#[CoversClass(LogoutTokens::class)]
#[CoversClass(MessengerLogoutNotifier::class)]
#[CoversClass(SendLogoutToken::class)]
#[CoversClass(SendLogoutTokenHandler::class)]
#[CoversClass(Sessions::class)]
#[CoversClass(ClientRegistry::class)]
#[CoversClass(MetadataController::class)]
#[CoversClass(DeliveryFailed::class)]
final class BackChannelLogoutTest extends TestCase
{
    private const string ISSUER = 'https://id.example';

    private Services $services;

    private InMemoryClientRepository $clients;

    private InMemorySigningKeyRepository $keyStore;

    private ProviderSettings $settings;

    protected function setUp(): void
    {
        $this->services = new Services();
        $this->clients = new InMemoryClientRepository();
        $this->keyStore = new InMemorySigningKeyRepository();
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256);
    }

    public function testAClosedSessionTellsEachClientOfItsTokensOnce(): void
    {
        $grants = new InMemoryGrantRepository();
        $notifier = new class implements LogoutNotifier {
            /** @var list<array{string, string, list<string>}> */
            public array $told = [];

            #[Override]
            public function sessionEnded(string $sessionId, string $accountId, array $clientIds): void
            {
                $this->told[] = [$sessionId, $accountId, $clientIds];
            }
        };
        $account = $this->services->account();
        $sessions = new Sessions(new InMemoryAccountSessionRepository(), $this->services->journal, $this->services->clock, null, $grants, 300, null, $notifier);
        $session = $sessions->open($account, 'Firefox', ['pwd']);
        $now = $this->services->clock->now();
        $grants->saveToken(
            IssuedToken::issue('a1', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', $account->id(), [], $session->id(), $now, [], $now, $now->modify('+1 hour')),
            IssuedToken::issue('a2', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', $account->id(), [], $session->id(), $now, [], $now, $now->modify('+1 hour')),
            IssuedToken::issue('b1', TokenKind::Access, 'FAMILY0000000000000000000B', 'other', $account->id(), [], $session->id(), $now, [], $now, $now->modify('+1 hour')),
        );
        $quiet = $sessions->open($account, 'Safari', ['pwd']);

        self::assertTrue($sessions->close($account, $session->id()));
        self::assertTrue($sessions->close($account, $quiet->id()));

        self::assertSame([[$session->id(), $account->id(), ['app', 'other']]], $notifier->told, 'A session without tokens tells nobody.');
    }

    public function testTheNotifierDispatchesOneMessageOfIdentifiersPerClient(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            #[Override]
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };

        new MessengerLogoutNotifier($bus)->sessionEnded('sid', 'account', ['app', 'other']);

        self::assertEquals([new SendLogoutToken('app', 'account', 'sid'), new SendLogoutToken('other', 'account', 'sid')], $bus->messages);
        self::assertSame(['clientId', 'accountId', 'sessionId'], array_keys(get_object_vars($bus->messages[0])));
    }

    public function testTheHandlerPostsASignedLogoutToken(): void
    {
        $this->registry()->register('app', 'App', ['https://app.example/cb'], backchannelLogoutUri: 'https://app.example/logout?x=1');
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options['body'] ?? null, $options['max_redirects'] ?? null, $options['timeout'] ?? null, $options['max_duration'] ?? null];

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->handler($http, 2.5)(new SendLogoutToken('app', 'ACCOUNT', 'SESSION'));

        self::assertCount(1, $requests);
        [$method, $url, $body, $redirects, $timeout, $duration] = $requests[0];
        self::assertSame(['POST', 'https://app.example/logout?x=1', 0, 2.5, 2.5], [$method, $url, $redirects, $timeout, $duration]);
        self::assertIsString($body);
        parse_str($body, $form);
        self::assertSame(['logout_token'], array_keys($form));
        self::assertIsString($form['logout_token']);

        $header = json_decode(Base64Url::decode(explode('.', $form['logout_token'])[0]), true);
        self::assertIsArray($header);
        self::assertSame('logout+jwt', $header['typ']);
        $claims = Jws::decode($form['logout_token'], $this->keys()->publicKey(...));
        $now = $this->services->clock->now()->getTimestamp();
        self::assertSame(['iss', 'sub', 'aud', 'iat', 'exp', 'jti', 'sid', 'events'], array_keys($claims));
        self::assertSame([self::ISSUER, 'ACCOUNT', 'app', $now, $now + LogoutTokens::LIFETIME, 'SESSION', [LogoutTokens::EVENT => []]], [$claims['iss'], $claims['sub'], $claims['aud'], $claims['iat'], $claims['exp'], $claims['sid'], $claims['events']]);
        self::assertStringContainsString('"events":{"http://schemas.openid.net/event/backchannel-logout":{}}', Base64Url::decode(explode('.', $form['logout_token'])[1]));
        self::assertIsString($claims['jti']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}$/D', $claims['jti']);
    }

    public function testTheHandlerAccepts204AndFailsOtherwiseSoThatMessengerTriesAgain(): void
    {
        $this->registry()->register('app', 'App', ['https://app.example/cb'], backchannelLogoutUri: 'https://app.example/logout');

        $this->handler(new MockHttpClient(new MockResponse('', ['http_code' => 204])))(new SendLogoutToken('app', 'ACCOUNT', 'SESSION'));

        foreach ([new MockResponse('', ['http_code' => 400]), new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location: https://elsewhere.example']]), new MockResponse('', ['http_code' => 500]), new MockResponse('', ['error' => 'timeout'])] as $response) {
            try {
                $this->handler(new MockHttpClient($response))(new SendLogoutToken('app', 'ACCOUNT', 'SESSION'));
                self::fail('Delivered.');
            } catch (DeliveryFailed $deliveryFailed) {
                self::assertStringStartsWith('The back-channel logout of client "app" answered ', $deliveryFailed->getMessage());
            }
        }

        try {
            $this->handler(new MockHttpClient(static fn (): never => throw new TransportException('down')))(new SendLogoutToken('app', 'ACCOUNT', 'SESSION'));
            self::fail('Delivered.');
        } catch (DeliveryFailed $deliveryFailed) {
            self::assertSame('The back-channel logout of client "app" answered 0.', $deliveryFailed->getMessage());
        }
    }

    public function testTheHandlerSkipsAClientWithoutAddressDisabledOrGone(): void
    {
        $this->registry()->register('quiet', 'Quiet', ['https://app.example/cb']);
        [$disabled] = $this->registry()->register('disabled', 'Disabled', ['https://app.example/cb'], backchannelLogoutUri: 'https://app.example/logout');
        $this->registry()->disable($disabled);
        $calls = 0;
        $http = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('', ['http_code' => 200]);
        });

        foreach (['quiet', 'disabled', 'gone'] as $clientId) {
            $this->handler($http)(new SendLogoutToken($clientId, 'ACCOUNT', 'SESSION'));
        }

        self::assertSame(0, $calls);
    }

    public function testTheLogoutAddressIsReadLikeARedirectAddress(): void
    {
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb'], backchannelLogoutUri: 'HTTPS://App.example/logout');
        self::assertSame('https://app.example/logout', $client->backchannelLogoutUri());
        self::assertNull($this->registry()->register('quiet', 'Quiet', ['https://app.example/cb'])[0]->backchannelLogoutUri());

        foreach (['http://app.example/logout', 'https://app.example/logout#f', 'not an address'] as $address) {
            try {
                $this->registry()->register('bad', 'Bad', ['https://app.example/cb'], backchannelLogoutUri: $address);
                self::fail('Accepted '.$address);
            } catch (Refused $refused) {
                self::assertSame(['client.logout_uri_invalid', $address], $refused->reasons);
            }
        }
    }

    public function testTheDiscoveryDocumentSaysWhenTheProviderEmitsLogoutTokens(): void
    {
        $routes = new RouteCollection();

        foreach (['authorize', 'token', 'userinfo', 'jwks', 'end_session', 'revoke', 'introspect'] as $name) {
            $routes->add('phil_identity_provider_oidc_'.$name, new Route('/oidc/'.$name));
        }

        $urls = new UrlGenerator($routes, new RequestContext());
        $without = json_decode((string) new MetadataController($this->settings, $this->keys(), $urls)->discovery()->getContent(), true);
        $with = json_decode((string) new MetadataController($this->settings, $this->keys(), $urls, 300, true)->discovery()->getContent(), true);
        self::assertIsArray($without);
        self::assertIsArray($with);

        self::assertArrayNotHasKey('backchannel_logout_supported', $without);
        self::assertArrayNotHasKey('backchannel_logout_session_supported', $without);
        self::assertTrue($with['backchannel_logout_supported']);
        self::assertTrue($with['backchannel_logout_session_supported']);
    }

    private function handler(MockHttpClient $http, float $timeout = 5.0): SendLogoutTokenHandler
    {
        return new SendLogoutTokenHandler($this->clients, new LogoutTokens($this->keys(), $this->settings, $this->services->clock), $http, $timeout);
    }

    private function keys(): SigningKeys
    {
        return new SigningKeys($this->keyStore, new PlainSecretCipher(), $this->settings, $this->services->clock, new LockFactory(new InMemoryStore()), $this->services->journal);
    }

    private function registry(): ClientRegistry
    {
        return new ClientRegistry($this->clients, $this->services->clock, $this->settings, $this->services->journal);
    }
}
