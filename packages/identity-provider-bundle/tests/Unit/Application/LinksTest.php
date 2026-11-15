<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\AccountLinks;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\SignedLinks;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Query;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingMailer;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(SignedLinks::class)]
#[CoversClass(AccountLinks::class)]
final class LinksTest extends TestCase
{
    private const string SECRET = 'a secret of the application, long enough';

    public function testALinkVerifiesItsAddressOnce(): void
    {
        $services = new Services(date('Y-m-d H:i:s'));
        $links = new SignedLinks(self::SECRET, $services->clock);
        $account = $services->account();
        $link = $links->sign(SignedLinks::VERIFY, $account);

        self::assertSame($services->clock->now()->getTimestamp() + 86_400, $link['expires']);
        self::assertTrue($links->verify(SignedLinks::VERIFY, $account, $link['expires'], $link['signature']));
        self::assertFalse($links->verify(SignedLinks::RESET, $account, $link['expires'], $link['signature']));
        self::assertFalse($links->verify(SignedLinks::VERIFY, $account, $link['expires'] + 1, $link['signature']));
        self::assertFalse($links->verify(SignedLinks::VERIFY, $account, $link['expires'], 'forged'));
        self::assertFalse(new SignedLinks('another secret of the application, as long', $services->clock)->verify(SignedLinks::VERIFY, $account, $link['expires'], $link['signature']));

        $account->verifyEmail($services->clock->now());
        self::assertFalse($links->verify(SignedLinks::VERIFY, $account, $link['expires'], $link['signature']));
    }

    public function testAResetLinkStopsWorkingOnceThePasswordChangedOrItExpired(): void
    {
        $services = new Services(date('Y-m-d H:i:s'));
        $links = new SignedLinks(self::SECRET, $services->clock, 100, 60);
        $account = $services->account();
        $link = $links->sign(SignedLinks::RESET, $account);

        self::assertSame($services->clock->now()->getTimestamp() + 60, $link['expires']);
        self::assertSame($services->clock->now()->getTimestamp() + 100, $links->sign(SignedLinks::VERIFY, $account)['expires']);
        self::assertTrue($links->verify(SignedLinks::RESET, $account, $link['expires'], $link['signature']));
        $account->verifyEmail($services->clock->now());
        self::assertTrue($links->verify(SignedLinks::RESET, $account, $link['expires'], $link['signature']));

        $services->clock->modify('+61 seconds');
        self::assertFalse($links->verify(SignedLinks::RESET, $account, $link['expires'], $link['signature']));
        $services->clock->modify('-61 seconds');

        $account->changePassword('new hash', $services->clock->now()->modify('+1 second'));
        self::assertFalse($links->verify(SignedLinks::RESET, $account, $link['expires'], $link['signature']));
    }

    public function testTheLinksSentByEmail(): void
    {
        $services = new Services(date('Y-m-d H:i:s'));
        $mailer = new RecordingMailer();
        $links = new AccountLinks(new SignedLinks(self::SECRET, $services->clock), $this->urls(), $services->accounts, $services->journal, $mailer);
        $account = $services->account();
        $links->sendVerification($account);

        self::assertCount(1, $mailer->sent);
        [$message, $id, $url] = $mailer->sent[0];
        self::assertSame(['verify', $account->id()], [$message, $id]);
        self::assertStringStartsWith('https://id.example/verify-email?account='.$account->id().'&expires=', $url);
        self::assertStringNotContainsString('ada', $url);
        self::assertSame($url, preg_replace('/&signature=.*/', '', $links->verificationUrl($account)).substr($url, (int) strpos($url, '&signature=')));

        $query = Query::of($url);
        self::assertEquals($account, $links->account(SignedLinks::VERIFY, $query['account'], (int) $query['expires'], $query['signature']));
        self::assertNull($links->account(SignedLinks::VERIFY, 'unknown', (int) $query['expires'], $query['signature']));

        $account->verifyEmail($services->clock->now());
        $services->accounts->save($account);
        $links->sendVerification($account);
        self::assertCount(1, $mailer->sent);

        $links->requestPasswordReset('ADA@example.com');
        self::assertCount(2, $mailer->sent);
        self::assertSame('reset', $mailer->message(1)[0]);
        self::assertStringStartsWith('https://id.example/reset-password?account=', $mailer->message(1)[2]);
        self::assertSame(IdentityEventType::PasswordResetRequested->value, array_last($services->audit->actions()));

        $links->requestPasswordReset('nobody@example.com');
        $links->requestPasswordReset('not an address');
        self::assertCount(2, $mailer->sent);

        $account->disable($services->clock->now());
        $services->accounts->save($account);
        $links->requestPasswordReset('ada@example.com');
        self::assertCount(2, $mailer->sent);
        $query = Query::of($mailer->message(1)[2]);
        self::assertNull($links->account(SignedLinks::RESET, $query['account'], (int) $query['expires'], $query['signature']));
    }

    public function testWithoutMailerNothingIsSent(): void
    {
        $services = new Services(date('Y-m-d H:i:s'));
        $links = new AccountLinks(new SignedLinks(self::SECRET, $services->clock), $this->urls(), $services->accounts, $services->journal);
        $links->sendVerification($services->account());
        $links->requestPasswordReset('ada@example.com');

        self::assertContains(IdentityEventType::PasswordResetRequested->value, $services->audit->actions());
    }

    private function urls(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(AccountLinks::VERIFY_ROUTE, new Route('/verify-email'));
        $routes->add(AccountLinks::RESET_ROUTE, new Route('/reset-password'));

        return new UrlGenerator($routes, new RequestContext(host: 'id.example', scheme: 'https'));
    }
}
