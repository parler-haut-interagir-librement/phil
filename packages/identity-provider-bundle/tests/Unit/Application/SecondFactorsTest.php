<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use Phil\IdentityProviderBundle\Domain\Totp\Totp;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySecondFactorRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecondFactors::class)]
final class SecondFactorsTest extends TestCase
{
    public function testEnrolmentConfirmationAndCodes(): void
    {
        $services = new Services();
        $store = new InMemorySecondFactorRepository();
        $factors = new SecondFactors($store, new PlainSecretCipher(), new RecoveryCodes(str_repeat('k', 32)), $services->journal, $services->clock, 'Example');
        $account = $services->account();

        $first = $factors->enroll($account);
        $enrolment = $factors->enroll($account);
        self::assertNotSame($first['secret'], $enrolment['secret']);
        self::assertSame(32, \strlen($enrolment['secret']));
        self::assertStringStartsWith('otpauth://totp/Example:ada%40example.com?secret='.$enrolment['secret'].'&issuer=Example', $enrolment['uri']);
        self::assertSame('plain.'.$enrolment['secret'], $store->totp($account->id())?->storedSecret());
        self::assertFalse($factors->hasTotp($account));

        $totp = Totp::fromBase32($enrolment['secret']);
        $now = $services->clock->now()->getTimestamp();
        self::assertFalse($factors->verifyTotp($account, $totp->at($now)));

        try {
            $factors->confirm($account, '000000');
            self::fail('Confirmed with a wrong code.');
        } catch (Refused $refused) {
            self::assertSame(['totp.invalid'], $refused->reasons);
        }

        $codes = $factors->confirm($account, $totp->at($now));
        self::assertCount(10, $codes);
        self::assertTrue($factors->hasTotp($account));
        self::assertSame(10, $factors->remainingRecoveryCodes($account));
        self::assertSame([IdentityEventType::TotpEnabled->value, IdentityEventType::RecoveryCodesGenerated->value], \array_slice($services->audit->actions(), -2));

        self::assertFalse($factors->verifyTotp($account, $totp->at($now)));
        $services->clock->modify('+30 seconds');
        self::assertTrue($factors->verifyTotp($account, $totp->at($now + 30)));
        self::assertFalse($factors->verifyTotp($account, $totp->at($now + 30)));

        foreach (['totp.already_enabled' => static fn (): array => $factors->enroll($account), 'totp.not_pending' => static fn (): array => $factors->confirm($account, $totp->at($now + 60))] as $reason => $operation) {
            try {
                $operation();
                self::fail($reason);
            } catch (Refused $refused) {
                self::assertSame([$reason], $refused->reasons);
            }
        }

        self::assertTrue($factors->useRecoveryCode($account, $codes[4]));
        self::assertFalse($factors->useRecoveryCode($account, $codes[4]));
        self::assertFalse($factors->useRecoveryCode($account, 'AAAAA-AAAAA'));
        self::assertSame(9, $factors->remainingRecoveryCodes($account));
        self::assertSame([IdentityEventType::RecoveryCodeUsed->value, $account->id(), ['remaining' => 9]], array_last($services->audit->records));

        $fresh = $factors->regenerateRecoveryCodes($account);
        self::assertSame(10, $factors->remainingRecoveryCodes($account));
        self::assertFalse($factors->useRecoveryCode($account, $codes[0]));
        self::assertTrue($factors->useRecoveryCode($account, $fresh[0]));

        $factors->disableTotp($account);
        self::assertFalse($factors->hasTotp($account));
        self::assertSame(0, $factors->remainingRecoveryCodes($account));
        self::assertNull($store->recoveryCodes($account->id()));
        self::assertSame(IdentityEventType::TotpDisabled->value, array_last($services->audit->actions()));
        $count = \count($services->audit->records);
        $factors->disableTotp($account);
        self::assertCount($count, $services->audit->records);
    }

    public function testAConfirmationWithoutRecoveryCodesAndAWindow(): void
    {
        $services = new Services();
        $factors = new SecondFactors(new InMemorySecondFactorRepository(), new PlainSecretCipher(), new RecoveryCodes(str_repeat('k', 32)), $services->journal, $services->clock, 'Example', 0);
        $account = $services->account();
        $totp = Totp::fromBase32($factors->enroll($account)['secret']);
        $now = $services->clock->now()->getTimestamp();

        try {
            $factors->confirm($account, $totp->at($now - 30), false);
            self::fail('Accepted a code outside a window of 0.');
        } catch (Refused $refused) {
            self::assertNotSame('', $refused->getMessage());
        }

        self::assertSame([], $factors->confirm($account, $totp->at($now), false));
        self::assertSame(0, $factors->remainingRecoveryCodes($account));
        self::assertFalse($factors->useRecoveryCode($account, 'AAAAA-AAAAA'));
        self::assertFalse($factors->verifyTotp($services->account('bob@example.com'), $totp->at($now)));
    }
}
