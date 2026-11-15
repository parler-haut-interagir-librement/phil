<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\DeliveryFailed;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\PhoneVerification;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\ArrayChallengeStore;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingSms;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhoneVerification::class)]
#[CoversClass(DeliveryFailed::class)]
final class PhoneVerificationTest extends TestCase
{
    public function testANumberIsVerifiedByItsCode(): void
    {
        $services = new Services();
        $sms = new RecordingSms();
        $challenges = new ArrayChallengeStore();
        $verification = new PhoneVerification($sms, $challenges, $services->accounts, $services->journal, $services->clock, 'Code: {code}');
        $account = $services->account();

        self::assertNull($verification->pending($account));
        $verification->start($account, '+1 (514) 555-0123');
        self::assertSame('+15145550123', $verification->pending($account));
        self::assertSame('+15145550123', $sms->sent[0][0]);
        self::assertMatchesRegularExpression('/^Code: \d{6}$/D', $sms->sent[0][1]);
        self::assertNull($verification->pending($services->account('bob@example.com')));

        self::assertSame('+15145550123', $verification->confirm($account, ' '.$sms->lastCode().' '));
        self::assertSame('+15145550123', $account->phone());
        self::assertNotNull($account->phoneVerifiedAt());
        self::assertNull($verification->pending($account));
        self::assertSame(IdentityEventType::PhoneVerified->value, array_last($services->audit->actions()));

        $verification->remove($account);
        self::assertNull($account->phone());
        self::assertSame(IdentityEventType::PhoneRemoved->value, array_last($services->audit->actions()));
    }

    public function testAttemptsAndExpiry(): void
    {
        $services = new Services();
        $sms = new RecordingSms();
        $verification = new PhoneVerification($sms, new ArrayChallengeStore(), $services->accounts, $services->journal, $services->clock, lifetime: 60, attempts: 2);
        $account = $services->account();
        $verification->start($account, '+15145550123');
        $code = $sms->lastCode();
        $wrong = '000000' === $code ? '111111' : '000000';

        $this->assertRefused('phone.invalid_code', static fn (): string => $verification->confirm($account, $wrong));
        self::assertSame('+15145550123', $verification->pending($account));
        $this->assertRefused('phone.invalid_code', static fn (): string => $verification->confirm($account, $wrong));
        self::assertNull($verification->pending($account));
        $this->assertRefused('phone.expired', static fn (): string => $verification->confirm($account, $code));

        $verification->start($account, '+15145550123');
        $services->clock->modify('+61 seconds');
        $this->assertRefused('phone.expired', static fn (): string => $verification->confirm($account, $sms->lastCode()));

        $verification->start($account, '+15145550123');
        $services->clock->modify('+60 seconds');
        self::assertSame('+15145550123', $verification->confirm($account, $sms->lastCode()));
        $this->assertRefused('phone.expired', static fn (): string => $verification->confirm($services->account('bob@example.com'), $sms->lastCode()));
    }

    public function testRefusals(): void
    {
        $services = new Services();
        $sms = new RecordingSms();
        $verification = new PhoneVerification($sms, new ArrayChallengeStore(), $services->accounts, $services->journal, $services->clock);
        $account = $services->account();

        $this->assertRefused('phone.invalid', static fn () => $verification->start($account, '514 555 0123'));
        $sms->fail = true;
        $this->assertRefused('phone.not_sent', static fn () => $verification->start($account, '+15145550123'));
        self::assertNull($verification->pending($account));
        self::assertSame([], $sms->sent);
    }

    private function assertRefused(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail('Accepted, expected '.$reason);
        } catch (Refused $refused) {
            self::assertSame([$reason], $refused->reasons);
        }
    }
}
