<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Exception\DeliveryFailed;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Infrastructure\Audit\NullAuditRecorder;
use Phil\IdentityProviderBundle\Infrastructure\Mailer\MailerAccountMailer;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Notifier\NotifierSmsSender;
use Phil\IdentityProviderBundle\Infrastructure\Secret\KeyManagementSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Security\AccountUserChecker;
use Phil\IdentityProviderBundle\Infrastructure\Security\AccountUserProvider;
use Phil\IdentityProviderBundle\Infrastructure\Security\DeviceName;
use Phil\IdentityProviderBundle\Infrastructure\Security\Proofs;
use Phil\IdentityProviderBundle\Infrastructure\Twig\KitComponents;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\FailingResponse;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Translation\IdentityTranslator;
use Throwable;

#[CoversClass(DeviceName::class)]
#[CoversClass(Proofs::class)]
#[CoversClass(PlainSecretCipher::class)]
#[CoversClass(KeyManagementSecretCipher::class)]
#[CoversClass(NotifierSmsSender::class)]
#[CoversClass(MailerAccountMailer::class)]
#[CoversClass(NullAuditRecorder::class)]
#[CoversClass(AccountUserProvider::class)]
#[CoversClass(AccountUserChecker::class)]
#[CoversClass(KitComponents::class)]
final class AdaptersTest extends TestCase
{
    private const string ID = '01JCZ6Q7Y3W0000000000000AA';

    #[DataProvider('userAgents')]
    public function testDeviceNames(?string $userAgent, string $name): void
    {
        self::assertSame($name, DeviceName::of($userAgent));
    }

    /**
     * @return iterable<array{?string, string}>
     */
    public static function userAgents(): iterable
    {
        yield [null, 'Browser'];
        yield ['curl/8.0', 'Browser'];
        yield ['Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', 'Firefox on Linux'];
        yield ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/141.0 Safari/537.36 Edg/141.0', 'Edge on Windows'];
        yield ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605 Version/18.0 Safari/605', 'Safari on macOS'];
        yield ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1', 'Safari on iOS'];
        yield ['Mozilla/5.0 (iPad; CPU OS 18_0) Safari/604.1', 'Safari on iPadOS'];
        yield ['Mozilla/5.0 (Linux; Android 15) Chrome/141.0 Mobile Safari/537.36', 'Chrome on Android'];
        yield ['Mozilla/5.0 (X11; CrOS x86_64) Chrome/141.0 Safari/537.36', 'Chrome on ChromeOS'];
        yield ['Mozilla/5.0 (Windows NT 10.0) Chrome/141.0 Safari/537.36 OPR/120.0', 'Opera on Windows'];
        yield [str_repeat('x', 600).'Firefox/1', 'Browser'];
    }

    public function testProofs(): void
    {
        $token = new UsernamePasswordToken(new InMemoryUser('ada', null), 'main');

        self::assertSame([], Proofs::of(null));
        self::assertSame([], Proofs::of($token));
        $token->setAuthenticationProofs(['pwd' => 100, 'otp' => 200]);
        self::assertSame(['pwd' => 100, 'otp' => 200], Proofs::of($token));
        self::assertTrue(Proofs::provenSince($token, ['otp'], 200));
        self::assertFalse(Proofs::provenSince($token, ['otp'], 201));
        self::assertFalse(Proofs::provenSince($token, ['hwk'], 0));
        self::assertTrue(Proofs::provenSince($token, ['pwd', 'hwk'], 100));
        self::assertFalse(Proofs::provenSince(null, ['pwd'], 0));
    }

    public function testThePlainCipherAndTheKeyManagementCipher(): void
    {
        $plain = new PlainSecretCipher();
        self::assertSame('plain.secret', $plain->seal('secret', 'ctx'));
        self::assertSame('secret', $plain->open('plain.secret', 'other'));

        $kms = new SodiumKms(new InMemoryKeyLoader(['key' => sodium_crypto_aead_xchacha20poly1305_ietf_keygen()]));
        $cipher = new KeyManagementSecretCipher($kms, $kms, 'key');
        $sealed = $cipher->seal('secret', 'totp:a');

        self::assertStringStartsWith('kms.', $sealed);
        self::assertStringNotContainsString('secret', $sealed);
        self::assertSame('secret', $cipher->open($sealed, 'totp:a'));
        self::assertSame('kept', $cipher->open('plain.kept', 'totp:a'));

        foreach ([[$plain, $sealed], [$cipher, 'kms.one-part'], [$cipher, 'other.a.b']] as [$opener, $value]) {
            try {
                $opener->open($value, 'totp:a');
                self::fail('Opened.');
            } catch (MalformedInput $malformedInput) {
                self::assertNotSame('', $malformedInput->getMessage());
            }
        }

        $this->expectException(Throwable::class);
        $cipher->open($sealed, 'totp:b');
    }

    public function testTheTexter(): void
    {
        $texter = new class implements TexterInterface {
            /** @var list<MessageInterface> */
            public array $sent = [];

            public bool $fail = false;

            public function send(MessageInterface $message): ?SentMessage
            {
                if ($this->fail) {
                    throw new TransportException('down', new FailingResponse());
                }

                $this->sent[] = $message;

                return null;
            }

            public function supports(MessageInterface $message): bool
            {
                return true;
            }

            public function __toString(): string
            {
                return 'test';
            }
        };
        new NotifierSmsSender($texter, 'PHIL')->send('+15145550123', 'Code: 123456');

        $message = $texter->sent[0] ?? null;
        self::assertInstanceOf(SmsMessage::class, $message);
        self::assertSame(['+15145550123', 'Code: 123456', 'PHIL'], [$message->getPhone(), $message->getSubject(), $message->getFrom()]);

        $texter->fail = true;
        $this->expectException(DeliveryFailed::class);
        new NotifierSmsSender($texter)->send('+15145550123', 'Code');
    }

    public function testTheMailer(): void
    {
        $mailer = new RecordingTransport();
        $account = Account::register(self::ID, 'ada@example.com', new DateTimeImmutable('2026-11-14 10:00:00'), 'Ada');
        $accountMailer = new MailerAccountMailer($mailer, $mailer, new IdentityTranslator(), 'accounts@example.com', 'Example');
        $accountMailer->sendVerification($account, 'https://x/verify');
        $accountMailer->sendPasswordReset($account, 'https://x/reset');
        $accountMailer->sendLoginLink($account, 'https://x/login');
        $accountMailer->sendRiskySignIn($account, AccountSession::open('s', self::ID, 'Safari on iOS', [], true, new DateTimeImmutable('2026-11-14 10:00:00')));

        $expected = [
            ['email.verify_email.subject', '@PhilIdentityProvider/email/verify_email.html.twig', ['url' => 'https://x/verify']],
            ['email.reset_password.subject', '@PhilIdentityProvider/email/reset_password.html.twig', ['url' => 'https://x/reset']],
            ['email.login_link.subject', '@PhilIdentityProvider/email/login_link.html.twig', ['url' => 'https://x/login']],
            ['email.risky_sign_in.subject', '@PhilIdentityProvider/email/risky_sign_in.html.twig', ['device' => 'Safari on iOS']],
        ];

        self::assertCount(4, $mailer->sent);

        foreach ($expected as $position => [$subject, $template, $context]) {
            $email = $mailer->sent[$position];
            self::assertInstanceOf(TemplatedEmail::class, $email);
            self::assertSame($subject, $email->getSubject());
            self::assertSame($template, $email->getHtmlTemplate());
            self::assertSame(str_replace('.html.twig', '.txt.twig', $template), $email->getTextTemplate(), 'The text part writes the link in full.');
            $tracking = TrackingHeader::fromHeaders($email->getHeaders());
            self::assertInstanceOf(TrackingHeader::class, $tracking);
            self::assertSame([false, false], [$tracking->getOpens(), $tracking->getClicks()], 'The provider follows neither opens nor clicks.');
            self::assertSame($email, $mailer->rendered[$position] ?? null);
            self::assertSame('accounts@example.com', $email->getFrom()[0]->getAddress());
            self::assertSame('ada@example.com', $email->getTo()[0]->getAddress());
            self::assertSame(['site' => 'Example', 'name' => 'Ada'], array_intersect_key($email->getContext(), ['site' => 1, 'name' => 1]));

            foreach ($context as $key => $value) {
                self::assertSame($value, $email->getContext()[$key] ?? null);
            }
        }
    }

    public function testTheUserProviderAndChecker(): void
    {
        $accounts = new InMemoryAccountRepository();
        $account = Account::register(self::ID, 'ada@example.com', new DateTimeImmutable());
        $accounts->save($account);
        $provider = new AccountUserProvider($accounts);

        self::assertEquals($account, $provider->loadUserByIdentifier(self::ID)->account);
        self::assertEquals($account, $provider->loadUserByIdentifier('ADA@example.com')->account);
        self::assertEquals($account, $provider->refreshUser(new AccountUser($account))->account);
        $uuid = Account::register('0192f5b4-7c1e-7a3b-9f00-123456789abc', 'uuid@example.com', new DateTimeImmutable());
        $accounts->save($uuid);
        self::assertEquals($uuid, $provider->loadUserByIdentifier('0192f5b4-7c1e-7a3b-9f00-123456789abc')->account);
        self::assertEquals($uuid, $provider->refreshUser(new AccountUser($uuid))->account);

        try {
            $provider->loadUserByIdentifier('uuid');
            self::fail('Loaded an identifier as an address.');
        } catch (UserNotFoundException $userNotFoundException) {
            self::assertSame('uuid', $userNotFoundException->getUserIdentifier());
        }

        self::assertTrue($provider->supportsClass(AccountUser::class));
        self::assertFalse($provider->supportsClass(InMemoryUser::class));

        $provider->upgradePassword(new AccountUser($account), 'stronger');
        $provider->upgradePassword(new InMemoryUser('x', null), 'ignored');
        self::assertSame('stronger', $accounts->get(self::ID)?->passwordHash());

        try {
            $provider->loadUserByIdentifier('01JCZ6Q7Y3W0000000000000ZZ');
            self::fail('Loaded.');
        } catch (UserNotFoundException $userNotFoundException) {
            self::assertSame('01JCZ6Q7Y3W0000000000000ZZ', $userNotFoundException->getUserIdentifier());
        }

        try {
            $provider->refreshUser(new InMemoryUser('x', null));
            self::fail('Refreshed.');
        } catch (UnsupportedUserException $unsupportedUserException) {
            self::assertStringContainsString(InMemoryUser::class, $unsupportedUserException->getMessage());
        }

        $checker = new AccountUserChecker(true);
        $checker->checkPreAuth(new AccountUser($account));
        $checker->checkPreAuth(new InMemoryUser('x', null));
        $checker->checkPostAuth(new InMemoryUser('x', null));
        new AccountUserChecker()->checkPostAuth(new AccountUser($account));

        foreach ([
            'account.unverified' => static fn () => $checker->checkPostAuth(new AccountUser($account)),
            'account.disabled' => static function () use ($checker, $account): void {
                $account->disable(new DateTimeImmutable());
                $checker->checkPreAuth(new AccountUser($account));
            },
        ] as $key => $check) {
            try {
                $check();
                self::fail($key);
            } catch (CustomUserMessageAccountStatusException $exception) {
                self::assertSame($key, $exception->getMessageKey());
            }
        }

        $account->verifyEmail(new DateTimeImmutable());
        $checker->checkPostAuth(new AccountUser($account));
    }

    public function testKitNamesAndTheEmptyTrail(): void
    {
        self::assertSame('Button', new KitComponents()->name('Button'));
        self::assertSame('ui:Field:Label', new KitComponents('/ui/')->name('Field:Label'));
        self::assertSame('a:b:Card', new KitComponents('a/b')->name('Card'));
        new NullAuditRecorder()->record('identity.test', null, ['a' => 1]);
        self::assertTrue(class_exists(AuditTrail::class));
    }
}
