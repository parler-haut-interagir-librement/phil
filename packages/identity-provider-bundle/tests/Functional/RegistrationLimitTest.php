<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use Override;
use Phil\IdentityProviderBundle\Infrastructure\Http\AccountController;
use Phil\IdentityProviderBundle\Infrastructure\Security\RegistrationRateLimiter;
use Phil\IdentityProviderBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Registration counted per client and per address typed, and a verification email sent once in each delay.
 */
#[CoversClass(AccountController::class)]
#[CoversClass(RegistrationRateLimiter::class)]
final class RegistrationLimitTest extends FunctionalTestCase
{
    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(identity: ['registration' => ['rate_limit' => ['per_ip' => ['limit' => 3], 'per_address' => ['limit' => 2], 'resend_delay' => '1 hour']]]);
    }

    public function testOneAddressIsRegisteredAFewTimesAtMost(): void
    {
        $this->register('ada@example.com');
        self::assertResponseIsSuccessful();
        $this->register(' ADA@example.com ');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Check your email');

        $this->register('Ada@Example.com');
        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('main', 'Too many registrations');
        self::assertInputValueSame('email', 'Ada@Example.com');
        self::assertEmailCount(1, message: 'Only the first registration sends a link.');
    }

    public function testOneClientRegistersAFewAccountsAtMost(): void
    {
        foreach (['ada', 'grace', 'hedy'] as $name) {
            $this->register($name.'@example.com');
            self::assertResponseIsSuccessful();
        }

        $this->register('joan@example.com');
        self::assertResponseStatusCodeSame(429);
        self::assertNull($this->accounts()->findByEmail('joan@example.com'));

        $this->register('joan@example.com', '192.0.2.10');
        self::assertResponseIsSuccessful();
        self::assertNotNull($this->accounts()->findByEmail('joan@example.com'));
    }

    public function testAVerificationEmailIsNotSentAgainTooSoon(): void
    {
        $this->register('ada@example.com');

        $this->signIn();
        $crawler = $this->client->request('GET', '/account');
        $this->client->submit($crawler->selectButton('Send the link again')->form());
        self::assertResponseRedirects('/account?sent=1');
        self::assertCount(1, array_filter(self::getMailerMessages(), static fn (RawMessage $message): bool => $message instanceof Email && str_starts_with((string) $message->getSubject(), 'Verify your email address')));
    }

    public function testAVerifiedAccountSpendsNothingOfTheDelay(): void
    {
        $this->account();
        $this->signIn();
        $crawler = $this->client->request('GET', '/account');
        self::assertCount(0, $crawler->selectButton('Send the link again'));
    }

    private function register(string $email, string $client = '127.0.0.1'): void
    {
        $crawler = $this->client->request('GET', '/register', server: ['REMOTE_ADDR' => $client]);
        $this->client->submit($crawler->selectButton('Create the account')->form(['email' => $email, 'password' => self::PASSWORD]), serverParameters: ['REMOTE_ADDR' => $client]);
    }
}
