<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use LogicException;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Infrastructure\Console\CreateClientCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\DisableClientCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\KeySetFile;
use Phil\IdentityProviderBundle\Infrastructure\Console\ListClientsCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\PurgeCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\ReplaceClientKeysCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\IdentityProviderBundle\Infrastructure\Federation\ExternalProviders;
use Phil\IdentityProviderBundle\Infrastructure\Http\AuthenticatorRouteController;
use Phil\IdentityProviderBundle\Infrastructure\Http\ExternalController;
use Phil\IdentityProviderBundle\Infrastructure\Http\LoginLinkController;
use Phil\IdentityProviderBundle\Infrastructure\Http\PhoneController;
use Phil\IdentityProviderBundle\Infrastructure\Security\ExternalAuthenticator;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingSms;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;

/**
 * Sign-in links, the phone number, federated identities and the commands.
 */
#[CoversClass(LoginLinkController::class)]
#[CoversClass(PhoneController::class)]
#[CoversClass(ExternalController::class)]
#[CoversClass(ExternalAuthenticator::class)]
#[CoversClass(ExternalProviders::class)]
#[CoversClass(AuthenticatorRouteController::class)]
#[CoversClass(CreateClientCommand::class)]
#[CoversClass(ListClientsCommand::class)]
#[CoversClass(DisableClientCommand::class)]
#[CoversClass(RotateKeysCommand::class)]
#[CoversClass(PurgeCommand::class)]
#[CoversClass(ReplaceClientKeysCommand::class)]
#[CoversClass(KeySetFile::class)]
final class OtherMethodsTest extends FunctionalTestCase
{
    public function testASignInLink(): void
    {
        $this->account();
        $crawler = $this->client->request('GET', '/login/link');
        $this->client->submit($crawler->selectButton('Send me a link')->form(['email' => 'nobody@example.com']));
        self::assertSelectorTextContains('h1', 'Check your email');
        self::assertEmailCount(0);

        $crawler = $this->client->request('GET', '/login/link');
        $this->client->submit($crawler->selectButton('Send me a link')->form(['email' => 'ADA@example.com']));
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Your sign-in link for Example', $email->getSubject());
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $link);
        $url = html_entity_decode($link[1] ?? '');
        self::assertStringStartsWith('http://localhost/login/link/check?user=', $url);

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->request('GET', '/account');
        self::assertResponseRedirects(message: 'Following the link signs no one in.');

        $crawler = $this->client->request('GET', $url.'&'.http_build_query(['x[]' => 'array', 'bad!name' => 'v', 'long' => str_repeat('a', 1025)]));
        self::assertResponseIsSuccessful('A scanner that followed the link first used none of it.');
        $form = $crawler->selectButton('Sign in')->form();
        self::assertSame('POST', $form->getMethod());
        self::assertSame('http://localhost/login/link/check', $form->getUri());
        $keys = array_keys($form->getValues());
        sort($keys);
        self::assertSame(['expires', 'hash', 'user'], $keys, 'The parameters of the link only, and nothing malformed.');
        $this->client->submit($form);
        self::assertResponseRedirects('/account', message: 'The POST signs in, back to the page asked before.');
        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();

        $this->client->getCookieJar()->clear();
        $this->client->submit($form);
        self::assertResponseRedirects('/login', message: 'The link works once.');
        $this->client->request('GET', '/account');
        self::assertResponseRedirects();
    }

    public function testThePhoneNumber(): void
    {
        $account = $this->account();
        $this->signIn();
        $crawler = $this->client->request('GET', '/account/phone');
        $this->client->submit($crawler->selectButton('Send a code')->form(['phone' => '514 555 0123']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'country code');

        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->selectButton('Send a code')->form(['phone' => '+1 514 555 0123']));
        self::assertResponseRedirects('/account/phone', 303);
        $crawler = $this->client->followRedirect();
        $sms = $this->service(RecordingSms::class, 'test.sms');
        self::assertSame('+15145550123', $sms->sent[0][0] ?? null);

        $this->client->submit($crawler->selectButton('Confirm')->form(['code' => $sms->lastCode()]));
        self::assertResponseRedirects('/account/phone', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main', '+15145550123 is verified');
        self::assertSame('+15145550123', $this->accounts()->get($account->id())?->phone());

        $this->client->submit($crawler->selectButton('Remove')->form());
        self::assertNull($this->accounts()->get($account->id())?->phone());
    }

    public function testAFederatedIdentitySignsInAndLinks(): void
    {
        $this->client->request('GET', '/login/with/fake?sub=s-1&email=fake@example.com&verified=1');
        self::assertResponseRedirects();
        self::assertStringStartsWith('http://localhost/login/with/fake/callback?', $this->location());
        $this->client->followRedirect();
        self::assertResponseRedirects('/home');
        $crawler = $this->client->request('GET', '/account/identities');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main ul', 'fake');
        self::assertCount(1, $crawler->filter('a[href="/login/with/fake"]'));
        $account = $this->accounts()->findByEmail('fake@example.com');
        self::assertNotNull($account);
        self::assertTrue($account->isEmailVerified());

        $this->client->submit($crawler->selectButton('Unlink')->form());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'only way to sign in');

        $this->client->request('GET', '/logout');
        $this->account('ada@example.com');
        $this->client->request('GET', '/login/with/fake?sub=s-2&email=ada@example.com&verified=1');
        $this->client->followRedirect();
        self::assertResponseRedirects('http://localhost/login');
        $this->client->request('GET', '/login/with/fake/callback');
        self::assertResponseRedirects('http://localhost/login');
        $this->client->request('GET', '/login/with/unknown');
        self::assertResponseStatusCodeSame(404);

        $this->signIn();
        $this->client->request('GET', '/login/with/fake?sub=s-2');
        $this->client->followRedirect();
        self::assertResponseRedirects('/home');
        self::assertNotNull($this->service(LinkedIdentityRepository::class)->find('fake', 's-2'));
    }

    public function testTheCommands(): void
    {
        $application = new Application(self::$kernel ?? self::bootKernel());
        $create = new CommandTester($application->find('phil:identity-provider:client:create'));
        $create->execute(['id' => 'app', 'name' => 'The App', '--redirect-uri' => ['https://app.example/cb'], '--scope' => ['email']]);
        self::assertSame(0, $create->getStatusCode());
        self::assertStringContainsString('Client "app" registered.', $create->getDisplay());
        self::assertMatchesRegularExpression('/Its secret, shown once: [A-Za-z0-9_-]{43}/', $create->getDisplay());

        $create->execute(['id' => 'spa', 'name' => 'SPA', '--redirect-uri' => ['https://spa.example/cb'], '--public' => true, '--first-party' => true]);
        self::assertStringNotContainsString('secret', $create->getDisplay());
        $create->execute(['id' => 'app', 'name' => 'Again', '--redirect-uri' => ['https://app.example/cb']]);
        self::assertSame(1, $create->getStatusCode());
        self::assertStringContainsString('client.taken', $create->getDisplay());

        $list = new CommandTester($application->find('phil:identity-provider:client:list'));
        $list->execute([]);
        self::assertStringContainsString('confidential', $list->getDisplay());
        self::assertStringContainsString('public, first party', $list->getDisplay());

        $disable = new CommandTester($application->find('phil:identity-provider:client:disable'));
        $disable->execute(['id' => 'app', '--rotate-secret' => true]);
        self::assertStringContainsString('Its new secret, shown once:', $disable->getDisplay());
        $disable->execute(['id' => 'spa', '--rotate-secret' => true]);
        self::assertSame(1, $disable->getStatusCode());
        $disable->execute(['id' => 'nobody']);
        self::assertSame(1, $disable->getStatusCode());
        $disable->execute(['id' => 'app']);
        self::assertStringContainsString('Client "app" disabled.', $disable->getDisplay());
        $list->execute([]);
        self::assertStringContainsString('disabled', $list->getDisplay());

        $rotate = new CommandTester($application->find('phil:identity-provider:rotate-keys'));
        $rotate->execute([]);
        self::assertMatchesRegularExpression('/Keys removed: 0\. Activated: \S+\. Created: \S+\./', $rotate->getDisplay());
        $rotate->execute(['--now' => true]);
        self::assertSame(0, $rotate->getStatusCode());

        $key = PrivateKey::generate(SigningAlgorithm::ES256, 'k1');
        $next = PrivateKey::generate(SigningAlgorithm::ES256, 'k2');
        $directory = sys_get_temp_dir().'/phil-idp-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/k1.json', json_encode(['keys' => [$key->publicJwk()]], \JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/k2.json', json_encode(['keys' => [$next->publicJwk()]], \JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/bad.json', '{"keys": [1]}');
        file_put_contents($directory.'/large.json', str_repeat(' ', KeySetFile::MAX_BYTES + 1));

        $create->execute(['id' => 'machine', 'name' => 'Machine', '--jwks' => $directory.'/k1.json', '--resource' => ['https://api.example/']]);
        self::assertSame(0, $create->getStatusCode());
        self::assertStringNotContainsString('secret', $create->getDisplay());
        $create->execute(['id' => 'broken', 'name' => 'Broken', '--jwks' => $directory.'/bad.json', '--resource' => ['https://api.example/']]);
        self::assertStringContainsString('client.jwks_invalid', $create->getDisplay());
        $list->execute([]);
        self::assertStringContainsString('confidential, keys', $list->getDisplay());
        self::assertStringContainsString('https://api.example/', $list->getDisplay());
        $disable->execute(['id' => 'machine', '--rotate-secret' => true]);
        self::assertSame(1, $disable->getStatusCode());

        $keys = new CommandTester($application->find('phil:identity-provider:client:keys'));
        $keys->execute(['id' => 'machine', 'jwks' => $directory.'/k2.json']);
        self::assertSame(0, $keys->getStatusCode());
        self::assertStringContainsString('The keys of client "machine" are replaced.', $keys->getDisplay());
        self::assertNotNull($this->service(ClientRegistry::class)->get('machine')?->publicKey('k2'));

        foreach ([['id' => 'nobody', 'jwks' => $directory.'/k2.json'], ['id' => 'machine', 'jwks' => $directory.'/large.json'], ['id' => 'machine', 'jwks' => $directory.'/missing.json'], ['id' => 'spa', 'jwks' => $directory.'/k2.json']] as $input) {
            $keys->execute($input);
            self::assertSame(1, $keys->getStatusCode(), (string) json_encode($input));
        }

        foreach (['k1', 'k2', 'bad', 'large'] as $file) {
            unlink($directory.'/'.$file.'.json');
        }

        rmdir($directory);

        $purge = new CommandTester($application->find('phil:identity-provider:purge'));
        $purge->execute(['--session-days' => '7']);
        self::assertStringContainsString('Codes and tokens removed: 0. Link tokens removed: 0. Sessions removed: 0.', $purge->getDisplay());
    }

    public function testAnAuthenticatorRouteWithoutItsAuthenticatorSaysSo(): void
    {
        $this->expectException(LogicException::class);

        new AuthenticatorRouteController()();
    }

    public function testAPostToTheLinkCheckWithoutItsAuthenticatorSaysSo(): void
    {
        $controller = $this->service(LoginLinkController::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('login_link authenticator');

        $controller->confirm(Request::create('/login/link/check?user=a&expires=1&hash=h', 'POST'));
    }
}
