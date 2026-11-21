<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use Override;
use Phil\IdentityProviderBundle\Application\AccountLinks;
use Phil\IdentityProviderBundle\Application\SignedLinks;
use Phil\IdentityProviderBundle\Domain\Contract\LinkTokenRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineLinkTokenRepository;
use Phil\IdentityProviderBundle\Infrastructure\Http\AccountController;
use Phil\IdentityProviderBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mime\Email;

/**
 * The application with the links in the stored mode, UUIDv7 account identifiers, canonical addresses without their
 * subaddress, and no progressive lock: a link works once and is revoked, an account signs in by its UUID, and failed
 * sign-ins lock nothing.
 */
#[CoversClass(AccountController::class)]
#[CoversClass(AccountLinks::class)]
#[CoversClass(SignedLinks::class)]
#[CoversClass(DoctrineLinkTokenRepository::class)]
final class StoredLinksTest extends FunctionalTestCase
{
    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(identity: [
            'links' => ['mode' => 'stored'],
            'lockout' => false,
            'accounts' => ['id_generator' => 'uuid_v7', 'strip_subaddress' => true],
        ]);
    }

    public function testAVerificationLinkWorksOnce(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Create the account')->form(['email' => 'ada+id@example.com', 'password' => self::PASSWORD]));
        $account = $this->accounts()->findByEmail('ada+id@example.com');
        self::assertNotNull($account);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $account->id());
        self::assertSame('ada@example.com', $account->emailCanonical());

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $link);
        $url = html_entity_decode($link[1] ?? '');
        parse_str(substr($url, (int) strpos($url, '?') + 1), $query);
        self::assertIsString($query['signature'] ?? null);
        self::assertSame(43, \strlen($query['signature']));

        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Verify')->form();
        $this->client->submit($form);
        self::assertSelectorTextContains('h1', 'Address verified');
        self::assertTrue($this->accounts()->findByEmail('ada+id@example.com')?->isEmailVerified());
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(410);

        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Create the account')->form(['email' => 'ADA+other@example.com', 'password' => self::PASSWORD]));
        self::assertSelectorTextContains('h1', 'Check your email');
        self::assertNull($this->accounts()->findByEmail('ada+other@example.com'));
    }

    public function testAResetLinkWorksOnceAndTheOthersAreRevoked(): void
    {
        $this->account();
        $first = $this->resetLink();
        $second = $this->resetLink();
        self::assertNotSame($first, $second);

        $crawler = $this->client->request('GET', $second);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Save the password')->form(['password' => 'short']));
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->selectButton('Save the password')->form(['password' => 'violet staple horse battery correct']));
        self::assertSelectorTextContains('main', 'Your password is changed');

        $this->client->request('GET', $second);
        self::assertResponseStatusCodeSame(410);
        $this->client->request('GET', $first);
        self::assertResponseStatusCodeSame(410);
        $account = $this->accounts()->findByEmail('ada@example.com');
        self::assertNotNull($account);
        self::assertSame(0, $this->service(LinkTokenRepository::class)->revoke($account->id()));
    }

    public function testFailedSignInsLockNothingWithoutTheLockout(): void
    {
        $this->account();

        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $this->signIn(password: 'wrong');
        }

        self::assertSame(8, $this->accounts()->findByEmail('ada@example.com')?->failedSignIns());
        self::assertNull($this->accounts()->findByEmail('ada@example.com')?->lockedUntil());
        $this->signIn();
        self::assertResponseRedirects('/home');
    }

    private function resetLink(): string
    {
        $crawler = $this->client->request('GET', '/forgot-password');
        $this->client->submit($crawler->selectButton('Send the link')->form(['email' => 'ada@example.com']));
        $email = array_last(self::getMailerMessages());
        self::assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $link);

        return html_entity_decode($link[1] ?? '');
    }
}
