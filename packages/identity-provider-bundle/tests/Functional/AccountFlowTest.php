<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Infrastructure\Http\AccountController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Pages;
use Phil\IdentityProviderBundle\Infrastructure\Mailer\MailerAccountMailer;
use Phil\IdentityProviderBundle\Infrastructure\Routing\RouteLoader;
use Phil\IdentityProviderBundle\Infrastructure\Security\AccountUserChecker;
use Phil\IdentityProviderBundle\Infrastructure\Security\AccountUserProvider;
use Phil\IdentityProviderBundle\Infrastructure\Security\PasswordSignInListener;
use Phil\IdentityProviderBundle\Infrastructure\Security\SessionListener;
use Phil\IdentityProviderBundle\Infrastructure\Twig\IdentityExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Mime\Email;

/**
 * Registration, the link that verifies the address, signing in with the password and its lock, the account page, and
 * the forgotten password.
 */
#[CoversClass(AccountController::class)]
#[CoversClass(Pages::class)]
#[CoversClass(MailerAccountMailer::class)]
#[CoversClass(RouteLoader::class)]
#[CoversClass(AccountUserProvider::class)]
#[CoversClass(AccountUserChecker::class)]
#[CoversClass(PasswordSignInListener::class)]
#[CoversClass(SessionListener::class)]
#[CoversClass(IdentityExtension::class)]
final class AccountFlowTest extends FunctionalTestCase
{
    public function testRegistrationAndVerification(): void
    {
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Create an account');
        self::assertCount(1, $crawler->filter('input[type="email"][autocomplete="email"][required]'));
        self::assertCount(1, $crawler->filter('label[for="phil-identity-password"]'));

        $this->client->submit($crawler->selectButton('Create the account')->form(['email' => 'ada@example.com', 'password' => self::PASSWORD, 'name' => 'Ada']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Check your email');
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Verify your email address for Example', $email->getSubject());
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $link);
        self::assertStringStartsWith('http://localhost/verify-email?account=', html_entity_decode($link[1] ?? ''));

        $crawler = $this->client->request('GET', html_entity_decode($link[1] ?? ''));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Verify your address');
        self::assertFalse($this->accounts()->findByEmail('ada@example.com')?->isEmailVerified(), 'Opening the link, as a mail scanner does, verifies nothing.');
        $form = $crawler->selectButton('Verify')->form();

        $this->client->request('POST', '/verify-email', ['_token' => 'forged'] + array_diff_key($form->getValues(), ['_token' => true]));
        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->accounts()->findByEmail('ada@example.com')?->isEmailVerified());

        $this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Address verified');
        self::assertTrue($this->accounts()->findByEmail('ada@example.com')?->isEmailVerified());
        $this->client->request('GET', html_entity_decode($link[1] ?? ''));
        self::assertResponseStatusCodeSame(410);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(410);

        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Create the account')->form(['email' => 'ADA@example.com', 'password' => self::PASSWORD]));
        self::assertSelectorTextContains('h1', 'Check your email');
    }

    public function testARefusedRegistrationSaysWhy(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Create the account')->form(['email' => 'ada@example.com', 'password' => 'password']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'too easy to guess');
    }

    public function testSignInTheAccountPageAndTheLock(): void
    {
        $this->account();
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('http://localhost/login');

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->signIn(password: 'wrong');
        }

        $this->signIn();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.error', 'account.locked');

        $accounts = $this->accounts();
        $account = $accounts->findByEmail('ada@example.com');
        self::assertNotNull($account);
        $account->recordSignIn();
        $accounts->save($account);

        $this->signIn();
        self::assertResponseRedirects('http://localhost/account');
        $crawler = $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('ada@example.com', $crawler->filter('main')->text());
        self::assertCount(1, $crawler->filter('a[href="/account/passkeys"]'));
        self::assertCount(1, $crawler->filter('a[href="/account/sessions"]'));

        $this->client->submit($crawler->selectButton('Save')->form(['name' => 'Ada Lovelace']));
        self::assertResponseRedirects('/account?saved=1', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'saved');
        self::assertSame('Ada Lovelace', $accounts->findByEmail('ada@example.com')?->displayName());
    }

    public function testADisabledOrUnverifiedAccountDoesNotSignIn(): void
    {
        $account = $this->account(verified: false);
        $account->disable(new DateTimeImmutable());
        $this->accounts()->save($account);

        $this->signIn();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.error', 'account.disabled');
    }

    public function testTheForgottenPassword(): void
    {
        $this->account();
        $crawler = $this->client->request('GET', '/forgot-password');
        $this->client->submit($crawler->selectButton('Send the link')->form(['email' => 'nobody@example.com']));
        self::assertSelectorTextContains('h1', 'Check your email');
        self::assertEmailCount(0);

        $crawler = $this->client->request('GET', '/forgot-password');
        $this->client->submit($crawler->selectButton('Send the link')->form(['email' => 'ada@example.com']));
        self::assertSelectorTextContains('h1', 'Check your email');
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $link);
        $url = html_entity_decode($link[1] ?? '');
        self::assertStringStartsWith('http://localhost/reset-password?account=', $url);

        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Save the password')->form(['password' => 'short']));
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->selectButton('Save the password')->form(['password' => 'violet staple horse battery correct']));
        self::assertSelectorTextContains('main', 'Your password is changed');

        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(410);
        $this->signIn(password: 'violet staple horse battery correct');
        self::assertResponseRedirects('/home');
    }

    public function testAFormWithoutItsTokenIsRefused(): void
    {
        $this->client->request('POST', '/register', ['email' => 'ada@example.com', 'password' => self::PASSWORD, '_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }
}
