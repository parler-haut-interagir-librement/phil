<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use Override;
use Phil\IdentityProviderBundle\Application\AccountLinks;
use Phil\IdentityProviderBundle\Infrastructure\Http\AccountController;
use Phil\IdentityProviderBundle\Infrastructure\Http\LoginLinkController;
use Phil\IdentityProviderBundle\Infrastructure\Routing\RouterLinkOrigin;
use Phil\IdentityProviderBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mime\Email;

/**
 * A request that names another host — `attacker.example` — asks for a link sent to an account: with `public_origin`,
 * every link of the email points to the canonical address, whatever the `Host` header said.
 */
#[CoversClass(RouterLinkOrigin::class)]
#[CoversClass(AccountLinks::class)]
#[CoversClass(AccountController::class)]
#[CoversClass(LoginLinkController::class)]
final class ForgedHostTest extends FunctionalTestCase
{
    private const array FORGED = ['HTTP_HOST' => 'attacker.example'];

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(identity: ['public_origin' => 'https://accounts.example.com']);
    }

    public function testTheResetLinkPointsToTheCanonicalOrigin(): void
    {
        $this->account();
        $crawler = $this->client->request('GET', '/forgot-password', server: self::FORGED);
        $this->client->submit($crawler->selectButton('Send the link')->form(['email' => 'ada@example.com']), serverParameters: self::FORGED);

        $link = $this->link();
        self::assertStringStartsWith('https://accounts.example.com/reset-password?account=', $link);
        self::assertStringNotContainsString('attacker.example', $link);

        $this->client->request('GET', substr($link, \strlen('https://accounts.example.com')));
        self::assertResponseIsSuccessful();
    }

    public function testTheSignInLinkPointsToTheCanonicalOrigin(): void
    {
        $this->account();
        $crawler = $this->client->request('GET', '/login/link', server: self::FORGED);
        $this->client->submit($crawler->selectButton('Send me a link')->form(['email' => 'ada@example.com']), serverParameters: self::FORGED);

        $link = $this->link();
        self::assertStringStartsWith('https://accounts.example.com/login/link/check?user=', $link);
        self::assertStringNotContainsString('attacker.example', $link);
    }

    public function testTheVerificationLinkPointsToTheCanonicalOrigin(): void
    {
        $crawler = $this->client->request('GET', '/register', server: self::FORGED);
        $this->client->submit($crawler->selectButton('Create the account')->form(['email' => 'ada@example.com', 'password' => self::PASSWORD]), serverParameters: self::FORGED);

        $link = $this->link();
        self::assertStringStartsWith('https://accounts.example.com/verify-email?account=', $link);
        self::assertStringNotContainsString('attacker.example', $link);
    }

    public function testThePagesStillLinkToTheHostTheyAreServedFrom(): void
    {
        $this->client->request('GET', '/forgot-password');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/forgot-password"]');
    }

    private function link(): string
    {
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $link);

        return html_entity_decode($link[1] ?? '');
    }
}
