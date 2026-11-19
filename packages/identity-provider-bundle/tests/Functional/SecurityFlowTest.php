<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Totp\Totp;
use Phil\IdentityProviderBundle\Infrastructure\Http\PasskeyController;
use Phil\IdentityProviderBundle\Infrastructure\Http\SessionController;
use Phil\IdentityProviderBundle\Infrastructure\Http\StepUpController;
use Phil\IdentityProviderBundle\Infrastructure\Http\TotpController;
use Phil\IdentityProviderBundle\Infrastructure\Security\OneTimeCodeAuthenticator;
use Phil\IdentityProviderBundle\Infrastructure\Security\PasskeyAuthenticator;
use Phil\IdentityProviderBundle\Infrastructure\Security\ReAuthenticationEntryPoint;
use Phil\IdentityProviderBundle\Infrastructure\Security\SecondFactorListener;
use Phil\IdentityProviderBundle\Infrastructure\Security\SessionChallengeStore;
use Phil\IdentityProviderBundle\Infrastructure\Security\SessionListener;
use Phil\IdentityProviderBundle\Infrastructure\Security\StepUpVoter;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Authenticator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The authenticator application as a second step, step-up before sensitive actions, passkeys, and sessions closed
 * from another device.
 */
#[CoversClass(TotpController::class)]
#[CoversClass(StepUpController::class)]
#[CoversClass(PasskeyController::class)]
#[CoversClass(SessionController::class)]
#[CoversClass(OneTimeCodeAuthenticator::class)]
#[CoversClass(PasskeyAuthenticator::class)]
#[CoversClass(ReAuthenticationEntryPoint::class)]
#[CoversClass(SecondFactorListener::class)]
#[CoversClass(SessionListener::class)]
#[CoversClass(SessionChallengeStore::class)]
#[CoversClass(StepUpVoter::class)]
final class SecurityFlowTest extends FunctionalTestCase
{
    public function testTheAuthenticatorApplicationThenTheSecondStep(): void
    {
        $this->account();
        $this->signIn();
        $crawler = $this->client->request('GET', '/account/two-factor');
        self::assertResponseIsSuccessful();
        $secret = $crawler->filter('code')->first()->text();
        self::assertSame(32, \strlen($secret));
        self::assertStringStartsWith('otpauth://totp/Example:ada%40example.com?secret='.$secret, (string) $crawler->filter('a[href^="otpauth"]')->attr('href'));
        self::assertCount(1, $crawler->filter('input[autocomplete="one-time-code"][inputmode="numeric"]'));

        $this->client->submit($crawler->selectButton('Confirm')->form(['code' => '000000']));
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->getCrawler();
        $secret = $crawler->filter('code')->first()->text();
        $this->client->submit($crawler->selectButton('Confirm')->form(['code' => Totp::fromBase32($secret)->at(time())]));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Recovery codes');
        $codes = $this->client->getCrawler()->filter('.phil-identity-recovery-codes code')->each(static fn (Crawler $node): string => $node->text());
        self::assertCount(10, $codes);

        $this->client->request('GET', '/logout');
        $this->signIn();
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('/two-factor');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Confirm it is you');
        $this->client->request('GET', '/logout');
        self::assertResponseRedirects();

        $this->signIn();
        $crawler = $this->client->request('GET', '/two-factor');
        $this->client->submit($crawler->filter('form')->first()->form(['code' => '000000']));
        self::assertResponseRedirects('/two-factor');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'not right');

        $this->client->submit($crawler->filter('form')->eq(1)->form(['code' => $codes[0]]));
        self::assertResponseRedirects('/home');
        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Your account');
    }

    public function testFiveWrongCodesSignTheSessionOut(): void
    {
        $account = $this->account();
        $factors = $this->service(SecondFactors::class);
        $factors->confirm($account, Totp::fromBase32($factors->enroll($account)['secret'])->at(time()));
        $this->signIn();

        for ($try = 1; $try < OneTimeCodeAuthenticator::MAX_FAILURES; ++$try) {
            $crawler = $this->client->request('GET', '/two-factor');
            self::assertResponseIsSuccessful();
            $this->client->submit($crawler->filter('form')->first()->form(['code' => '000000']));
            self::assertResponseRedirects('/two-factor');
        }

        $crawler = $this->client->request('GET', '/two-factor');
        $this->client->submit($crawler->filter('form')->first()->form(['code' => '000000']));
        self::assertResponseRedirects('/two-factor');
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('http://localhost/login', message: 'The session was signed out: the first step comes again.');
    }

    public function testASensitiveActionAsksToProveItAgain(): void
    {
        $this->account();
        $this->signIn();
        $clock = time();
        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Change the password')->form(['password' => 'violet staple horse battery correct']);
        $this->client->submit($form);
        self::assertResponseRedirects('/account?saved=1', 303);
        self::assertGreaterThanOrEqual($clock, time());
    }

    public function testPasskeysRegisterSignInAndAskForReAuthentication(): void
    {
        $account = $this->account();
        $this->signIn();
        $crawler = $this->client->request('GET', '/account/passkeys');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'You have no passkey yet.');
        $token = (string) $crawler->filter('[data-phil--identity-provider-bundle--passkey-token-value]')->attr('data-phil--identity-provider-bundle--passkey-token-value');

        $this->client->request('POST', '/account/passkeys/options', server: ['HTTP_X_CSRF_TOKEN' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/account/passkeys/options', server: ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        $options = json_decode($this->content(), true);
        self::assertIsArray($options);
        self::assertIsArray($options['rp']);
        self::assertSame('localhost', $options['rp']['id'] ?? null);
        self::assertIsString($options['challenge']);

        $authenticator = new Authenticator('localhost', 'http://localhost');
        $this->client->request('POST', '/account/passkeys/register?name=Laptop', server: ['HTTP_X_CSRF_TOKEN' => $token, 'CONTENT_TYPE' => 'application/json'], content: $authenticator->register($options['challenge']));
        self::assertResponseStatusCodeSame(201);
        $created = json_decode($this->content(), true);
        self::assertIsArray($created);
        self::assertSame('Laptop', $created['name'] ?? null);

        $this->client->request('POST', '/account/passkeys/register', server: ['HTTP_X_CSRF_TOKEN' => $token, 'CONTENT_TYPE' => 'application/json'], content: $authenticator->register($options['challenge']));
        self::assertResponseRedirects('/reauthenticate');
        $this->client->followRedirect();
        self::assertCount(1, $this->client->getCrawler()->filter('[data-phil--identity-provider-bundle--passkey-login-url-value="/passkey/login"]'));

        $this->client->request('GET', '/logout');
        $this->client->request('POST', '/passkey/options');

        $options = json_decode($this->content(), true);
        self::assertIsArray($options);
        self::assertSame([], $options['allowCredentials']);
        self::assertIsString($options['challenge']);

        $this->client->request('POST', '/passkey/login', server: ['CONTENT_TYPE' => 'application/json'], content: $authenticator->sign('wrong'));
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'passkey.refused'], json_decode($this->content(), true));

        $this->client->request('POST', '/passkey/options');
        $options = json_decode($this->content(), true);
        self::assertIsArray($options);
        self::assertIsString($options['challenge']);
        $this->client->request('POST', '/passkey/login', server: ['CONTENT_TYPE' => 'application/json'], content: $authenticator->sign($options['challenge'], $account->id()));
        self::assertResponseIsSuccessful();
        self::assertSame(['redirect' => '/home'], json_decode($this->content(), true));

        $crawler = $this->client->request('GET', '/account/sessions');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('li[aria-current="true"]'));

        $crawler = $this->client->request('GET', '/account/passkeys');
        $passkeyId = (string) preg_replace('#.*/account/passkeys/([0-9a-f]{64})/rename#', '$1', (string) $crawler->filter('form[action$="/rename"]')->attr('action'));
        $this->client->submit($crawler->filter('form[action$="/rename"]')->form(['name' => 'Phone']));
        self::assertResponseRedirects('/account/passkeys', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main ul', 'Phone');

        $this->client->submit($crawler->filter('form[action$="/remove"]')->form());
        self::assertResponseRedirects('/account/passkeys', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'You have no passkey yet.');
        self::assertSame(64, \strlen($passkeyId));
    }

    public function testAnOldSignInIsAskedAgainBeforeASensitiveAction(): void
    {
        $this->account();
        $this->signIn();
        $this->service(MockClock::class, 'clock')->modify('+301 seconds');

        $crawler = $this->client->request('GET', '/account');
        $this->client->submit($crawler->selectButton('Change the password')->form(['password' => 'violet staple horse battery correct']));
        self::assertResponseRedirects('/reauthenticate');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Confirm it is you');
        self::assertCount(1, $this->client->getCrawler()->filter('a[href="/login"]'));
    }

    public function testSessionsAreClosedFromAnotherDevice(): void
    {
        $account = $this->account();
        $this->client->request('POST', '/login', ['_username' => 'ada@example.com', '_password' => self::PASSWORD], server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/140.0']);
        $sessions = $this->service(Sessions::class);
        $other = $sessions->open($account, 'Safari on iOS', ['pwd']);

        $crawler = $this->client->request('GET', '/account/sessions');
        self::assertCount(2, $crawler->filter('main > ul > li'));
        self::assertSelectorTextContains('li[aria-current="true"]', 'Firefox on Linux');
        self::assertSelectorTextContains('main', 'Safari on iOS');
        self::assertSelectorTextContains('main', 'new device');

        $this->client->submit($crawler->selectButton('Sign out of this session')->form());
        self::assertResponseRedirects('/account/sessions', 303);
        $crawler = $this->client->followRedirect();
        self::assertCount(1, $crawler->filter('main > ul > li'));
        self::assertTrue($this->service(AccountSessionRepository::class)->get($other->id())?->isClosed());

        $current = array_find($sessions->openFor($account), static fn (AccountSession $session): bool => 'Firefox on Linux' === $session->device());
        self::assertNotNull($current);
        $sessions->close($account, $current->id());
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAllOtherSessionsAreClosedAtOnce(): void
    {
        $account = $this->account();
        $this->signIn();
        $sessions = $this->service(Sessions::class);
        $sessions->open($account, 'Safari on iOS', ['pwd']);
        $sessions->open($account, 'Edge on Windows', ['pwd']);

        $crawler = $this->client->request('GET', '/account/sessions');
        $this->client->submit($crawler->selectButton('Sign out of every other session')->form());
        self::assertResponseRedirects('/account/sessions', 303);
        self::assertCount(1, $sessions->openFor($account));
        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
    }
}
