<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommercePosBundle\Application\CashierSwitch;
use Phil\CommercePosBundle\Domain\Exception\CashierPinRefused;
use Phil\CommercePosBundle\Domain\Model\CashierPin;
use Phil\CommercePosBundle\Tests\Unit\Support\InMemoryPos;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(CashierSwitch::class)]
#[CoversClass(CashierPinRefused::class)]
#[CoversClass(CashierPin::class)]
final class CashierSwitchTest extends TestCase
{
    private InMemoryPos $pos;

    private CashierSwitch $switch;

    private DateTimeImmutable $at;

    #[Override]
    protected function setUp(): void
    {
        $this->pos = new InMemoryPos();
        $this->switch = new CashierSwitch($this->pos, $this->hasher(), new RateLimiterFactory(['id' => 'pin', 'policy' => 'fixed_window', 'limit' => 3, 'interval' => '15 minutes'], new InMemoryStorage()));
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testACashierTakesOverBySettingAndTypingTheirPin(): void
    {
        self::assertTrue($this->switch->isEnabled());
        self::assertFalse($this->switch->hasPin('ada'));

        $this->switch->setPin('ada', '2580', $this->at);

        self::assertTrue($this->switch->hasPin('ada'));
        $stored = $this->pos->pins['ada'];
        self::assertNotSame('2580', $stored->hash());
        self::assertTrue($this->hasher()->verify($stored->hash(), '2580'));
        self::assertSame($this->at, $stored->setAt());
        self::assertSame('ada', $this->switch->switchTo('front', 'ada', '2580', $this->at));

        $later = $this->at->modify('+1 day');
        $this->switch->setPin('ada', '73915', $later);

        self::assertSame([$stored, $later], [$this->pos->pins['ada'], $stored->setAt()]);
        self::assertSame('ada', $this->switch->switchTo('front', 'ada', '73915', $this->at));

        $this->switch->removePin('ada');
        $this->switch->removePin('ada');
        self::assertFalse($this->switch->hasPin('ada'));
    }

    public function testAWrongPinAndAMissingOneReadTheSame(): void
    {
        $this->switch->setPin('ada', '2580', $this->at);

        foreach ([['ada', '2581'], ['bob', '2580']] as [$user, $pin]) {
            try {
                $this->switch->switchTo('front', $user, $pin, $this->at);
                self::fail('A wrong PIN switched the cashier.');
            } catch (CashierPinRefused $refused) {
                self::assertSame(['The PIN does not match.', null], [$refused->getMessage(), $refused->retryAfter]);
            }
        }
    }

    public function testAttemptsAreBoundedByRegisterAndUserAndResetByTheRightPin(): void
    {
        $this->switch->setPin('ada', '2580', $this->at);
        $this->switch->setPin('bob', '1357', $this->at);

        $this->switch->switchTo('front', 'ada', '2580', $this->at);

        $this->refused('front', 'ada', '0000');
        $this->refused('front', 'ada', '0000');

        self::assertSame('ada', $this->switch->switchTo('front', 'ada', '2580', $this->at));

        $this->refused('front', 'ada', '0000');
        $this->refused('front', 'ada', '0000');
        $this->refused('front', 'ada', '0000');

        try {
            $this->switch->switchTo('front', 'ada', '2580', $this->at);
            self::fail('A fourth attempt was tried.');
        } catch (CashierPinRefused $cashierPinRefused) {
            self::assertSame('Too many PIN attempts: try again later.', $cashierPinRefused->getMessage());
            self::assertInstanceOf(DateTimeImmutable::class, $cashierPinRefused->retryAfter);
        }

        self::assertSame('bob', $this->switch->switchTo('front', 'bob', '1357', $this->at));
        self::assertSame('ada', $this->switch->switchTo('back', 'ada', '2580', $this->at));
    }

    public function testTheAttemptsOfARegisterAndAUserAreNotThoseOfAnotherPairSpelledAlike(): void
    {
        $this->refused('ab', 'c', '0000');
        $this->refused('ab', 'c', '0000');
        $this->refused('ab', 'c', '0000');

        $this->refused('a', 'bc', '0000');
        $this->refused('abc', '', '0000');
    }

    public function testAMissingPinTakesAsLongToRefuseAsAWrongOne(): void
    {
        $hasher = new class implements PasswordHasherInterface {
            /** @var list<string> */
            public array $hashed = [];

            public function hash(#[SensitiveParameter] string $plainPassword): string
            {
                $this->hashed[] = $plainPassword;

                return 'hash';
            }

            public function verify(string $hashedPassword, #[SensitiveParameter] string $plainPassword): bool
            {
                return false;
            }

            public function needsRehash(string $hashedPassword): bool
            {
                return false;
            }
        };
        $switch = new CashierSwitch($this->pos, $hasher, new RateLimiterFactory(['id' => 'pin', 'policy' => 'no_limit'], new InMemoryStorage()));

        try {
            $switch->switchTo('front', 'nobody', '2580', $this->at);
            self::fail('A user without a PIN switched the cashier.');
        } catch (CashierPinRefused $cashierPinRefused) {
            self::assertSame('The PIN does not match.', $cashierPinRefused->getMessage());
        }

        self::assertSame(['2580'], $hasher->hashed);
    }

    public function testAnOldHashIsRehashedOnTheRightPin(): void
    {
        $this->pos->pins['ada'] = CashierPin::of('ada', new NativePasswordHasher(null, null, 4)->hash('2580'), $this->at);
        $switch = new CashierSwitch($this->pos, new NativePasswordHasher(null, null, 5), new RateLimiterFactory(['id' => 'pin', 'policy' => 'no_limit'], new InMemoryStorage()));
        $old = $this->pos->pins['ada']->hash();
        $later = $this->at->modify('+1 hour');

        self::assertSame('ada', $switch->switchTo('front', 'ada', '2580', $later));
        self::assertNotSame($old, $this->pos->pins['ada']->hash());
        self::assertSame($later, $this->pos->pins['ada']->setAt());
        self::assertFalse(new NativePasswordHasher(null, null, 5)->needsRehash($this->pos->pins['ada']->hash()));

        $now = $this->pos->pins['ada']->hash();
        $switch->switchTo('front', 'ada', '2580', $later->modify('+1 hour'));
        self::assertSame([$now, $later], [$this->pos->pins['ada']->hash(), $this->pos->pins['ada']->setAt()]);
    }

    public function testTheSwitchIsOffUnlessEnabled(): void
    {
        $off = new CashierSwitch($this->pos);
        $half = new CashierSwitch($this->pos, $this->hasher());

        self::assertFalse($off->isEnabled());
        self::assertFalse($half->isEnabled());

        foreach ([static fn (): string => $off->switchTo('front', 'ada', '2580', new DateTimeImmutable()), static fn (): string => $half->switchTo('front', 'ada', '2580', new DateTimeImmutable()), static fn () => $off->setPin('ada', '2580', new DateTimeImmutable())] as $attempt) {
            try {
                $attempt();
                self::fail('The switch worked while off.');
            } catch (CashierPinRefused $refused) {
                self::assertSame('The cashier switch by PIN is not enabled.', $refused->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function weakPins(): iterable
    {
        yield 'three digits' => ['258', 'A PIN is 4 to 8 digits.'];
        yield 'nine digits' => ['258025802', 'A PIN is 4 to 8 digits.'];
        yield 'letters' => ['25a0', 'A PIN is 4 to 8 digits.'];
        yield 'a new line' => ["2580\n", 'A PIN is 4 to 8 digits.'];
        yield 'one digit' => ['1111', 'A PIN is neither one digit repeated nor a run of digits.'];
        yield 'up' => ['1234', 'A PIN is neither one digit repeated nor a run of digits.'];
        yield 'up through zero' => ['7890', 'A PIN is neither one digit repeated nor a run of digits.'];
        yield 'down' => ['9876', 'A PIN is neither one digit repeated nor a run of digits.'];
        yield 'down through nine' => ['2109', 'A PIN is neither one digit repeated nor a run of digits.'];
    }

    #[DataProvider('weakPins')]
    public function testAWeakPinIsRefused(string $pin, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->switch->setPin('ada', $pin, $this->at);
    }

    public function testAPinOfEvenStepsOtherThanOneIsTaken(): void
    {
        foreach (['1345', '1357', '2468', '0000000', '12345678'] as $pin) {
            try {
                $this->switch->setPin('ada', $pin, $this->at);
                self::assertNotContains($pin, ['0000000', '12345678'], $pin);
            } catch (InvalidValue) {
                self::assertContains($pin, ['0000000', '12345678'], $pin);
            }
        }

        self::assertSame('ada', $this->switch->switchTo('front', 'ada', '2468', $this->at));
    }

    public function testAPinIsKeptForANamedUserAsAHash(): void
    {
        foreach ([[' ', 'h', 'A cashier is named in 1 to 255 characters.'], [str_repeat('é', 256), 'h', 'A cashier is named in 1 to 255 characters.'], ['ada', '', 'A PIN hash is 1 to 255 bytes.'], ['ada', str_repeat('h', 256), 'A PIN hash is 1 to 255 bytes.']] as [$user, $hash, $message]) {
            try {
                CashierPin::of($user, $hash, $this->at);
                self::fail($message);
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertSame(str_repeat('é', 255), CashierPin::of(str_repeat('é', 255), 'h', $this->at)->user);
    }

    private function refused(string $register, string $user, string $pin): void
    {
        try {
            $this->switch->switchTo($register, $user, $pin, $this->at);
            self::fail('A wrong PIN switched the cashier.');
        } catch (CashierPinRefused $cashierPinRefused) {
            self::assertSame('The PIN does not match.', $cashierPinRefused->getMessage());
        }
    }

    private function hasher(): NativePasswordHasher
    {
        return new NativePasswordHasher(null, null, 4);
    }
}
