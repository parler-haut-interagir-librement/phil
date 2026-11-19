<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommercePosBundle\Domain\Exception\CashierPinRefused;
use Phil\CommercePosBundle\Domain\Model\CashierPin;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The cashier switch on a shared register: a cashier picks their name and types their PIN, and rings up as
 * themselves on a device someone already unlocked. It is off unless the application enables it.
 *
 * A PIN belongs to a user of the application, is kept only as Symfony PasswordHasher's hash, and is tried under a
 * rate limiter keyed by register and user. The switch names no account of its own and signs nobody in: it tells the
 * application which user typed the right PIN, and the application decides what that user may do.
 */
final readonly class CashierSwitch
{
    private const string PIN = '/^\d{4,8}$/D';

    public function __construct(
        private RegisterRepository $registers,
        private ?PasswordHasherInterface $hasher = null,
        private ?RateLimiterFactoryInterface $limiter = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->hasher instanceof PasswordHasherInterface && $this->limiter instanceof RateLimiterFactoryInterface;
    }

    /**
     * Sets or changes a user's PIN: 4 to 8 digits, neither one digit repeated nor a run such as 1234 or 9876.
     */
    public function setPin(string $user, #[SensitiveParameter] string $pin, DateTimeImmutable $at): void
    {
        $hasher = $this->hasher ?? throw CashierPinRefused::disabled();
        $this->checkPin($pin);
        $hash = $hasher->hash($pin);
        $existing = $this->registers->findCashierPin($user);

        if ($existing instanceof CashierPin) {
            $existing->change($hash, $at);

            return;
        }

        $this->registers->addCashierPin(CashierPin::of($user, $hash, $at));
    }

    public function removePin(string $user): void
    {
        $pin = $this->registers->findCashierPin($user);

        if ($pin instanceof CashierPin) {
            $this->registers->removeCashierPin($pin);
        }
    }

    public function hasPin(string $user): bool
    {
        return $this->registers->findCashierPin($user) instanceof CashierPin;
    }

    /**
     * Checks a cashier's PIN at a register.
     *
     * @return string the user who takes over the register
     *
     * @throws CashierPinRefused when the switch is off, the attempts are spent, or the PIN does not match
     */
    public function switchTo(string $register, string $user, #[SensitiveParameter] string $pin, DateTimeImmutable $at): string
    {
        if (!$this->hasher instanceof PasswordHasherInterface || !$this->limiter instanceof RateLimiterFactoryInterface) {
            throw CashierPinRefused::disabled();
        }

        $limiter = $this->limiter->create(hash('sha256', $register."\0".$user));
        $attempt = $limiter->consume();

        if (!$attempt->isAccepted()) {
            throw CashierPinRefused::tooManyAttempts($attempt->getRetryAfter());
        }

        $stored = $this->registers->findCashierPin($user);

        if (!$stored instanceof CashierPin) {
            // As long as a wrong PIN takes: whether a user has a PIN is not told by the time it takes to refuse.
            $this->hasher->hash($pin);

            throw CashierPinRefused::wrongPin();
        }

        if (!$this->hasher->verify($stored->hash(), $pin)) {
            throw CashierPinRefused::wrongPin();
        }

        $limiter->reset();

        if ($this->hasher->needsRehash($stored->hash())) {
            $stored->change($this->hasher->hash($pin), $at);
        }

        return $stored->user;
    }

    private function checkPin(#[SensitiveParameter] string $pin): void
    {
        if (1 !== preg_match(self::PIN, $pin)) {
            throw InvalidValue::because('A PIN is 4 to 8 digits.');
        }

        $steps = [];

        for ($i = 1, $length = \strlen($pin); $i < $length; ++$i) {
            $step = (\ord($pin[$i]) - \ord($pin[$i - 1]) + 10) % 10;
            $steps[$step] = $step;
        }

        // One step between every digit: 1111 (0), 1234 or 7890 (1), 9876 or 3210 (9).
        if (1 === \count($steps) && (isset($steps[0]) || isset($steps[1]) || isset($steps[9]))) {
            throw InvalidValue::because('A PIN is neither one digit repeated nor a run of digits.');
        }
    }
}
