<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Application\Contract\ChallengeStore;
use Phil\IdentityProviderBundle\Application\Contract\SmsSender;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\DeliveryFailed;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * The verification of a phone number by a code sent in a text message: six digits, kept hashed in the
 * `ChallengeStore` with the number, the expiry and the attempts left, valid `lifetime` seconds and for `attempts`
 * tries. A verified number lets the application reach the person; a code sent to it is a proof of the method `sms`.
 * The rate of sending belongs to the application's rate limiter, in front of the route.
 */
final readonly class PhoneVerification
{
    private const string PURPOSE = 'phone.verify';

    private const int CODE_LENGTH = 6;

    public function __construct(
        private SmsSender $sender,
        private ChallengeStore $challenges,
        private AccountRepository $accounts,
        private Journal $journal,
        private ClockInterface $clock,
        private string $messageTemplate = 'Your code: {code}',
        private int $lifetime = 600,
        private int $attempts = 5,
    ) {
    }

    /**
     * @throws Refused when the number is not in E.164, or the message could not be sent
     */
    public function start(Account $account, string $phone): void
    {
        $phone = str_replace([' ', '-', '.', '(', ')'], '', $phone);

        if (1 !== preg_match(Account::PHONE_PATTERN, $phone)) {
            throw Refused::because('phone.invalid');
        }

        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; ++$i) {
            $code .= random_int(0, 9);
        }

        try {
            $this->sender->send($phone, str_replace('{code}', $code, $this->messageTemplate));
        } catch (DeliveryFailed) {
            throw Refused::because('phone.not_sent');
        }

        $this->store($account, $phone, $this->hash($account, $phone, $code), $this->clock->now()->getTimestamp() + $this->lifetime, $this->attempts);
    }

    /**
     * The number waiting for its code, null when none is.
     */
    public function pending(Account $account): ?string
    {
        $pending = $this->read($this->challenges->peek(self::PURPOSE), $account);

        return $pending[0] ?? null;
    }

    /**
     * Verifies the number with the code it received.
     *
     * @throws Refused when no code is pending, it expired, it does not match, or too many tries were made
     */
    public function confirm(Account $account, #[SensitiveParameter] string $code): string
    {
        $pending = $this->read($this->challenges->take(self::PURPOSE), $account);

        if (null === $pending || $pending[2] < $this->clock->now()->getTimestamp()) {
            throw Refused::because('phone.expired');
        }

        [$phone, $hash, $expires, $left] = $pending;

        if (!hash_equals($hash, $this->hash($account, $phone, trim($code)))) {
            if ($left > 1) {
                $this->store($account, $phone, $hash, $expires, $left - 1);
            }

            throw Refused::because('phone.invalid_code');
        }

        $account->verifyPhone($phone, $this->clock->now());
        $this->accounts->save($account);
        $this->journal->record(IdentityEventType::PhoneVerified, $account->id());

        return $phone;
    }

    public function remove(Account $account): void
    {
        $account->removePhone();
        $this->accounts->save($account);
        $this->journal->record(IdentityEventType::PhoneRemoved, $account->id());
    }

    private function store(Account $account, string $phone, string $hash, int $expires, int $left): void
    {
        $this->challenges->put(self::PURPOSE, implode(' ', [$account->id(), $phone, $hash, $expires, $left]));
    }

    /**
     * @return array{string, string, int, int}|null the number, the hash, the expiry and the attempts left
     */
    private function read(?string $stored, Account $account): ?array
    {
        $parts = explode(' ', $stored ?? '');

        if (5 !== \count($parts) || $parts[0] !== $account->id()) {
            return null;
        }

        return [$parts[1], $parts[2], (int) $parts[3], (int) $parts[4]];
    }

    private function hash(Account $account, string $phone, string $code): string
    {
        return hash('sha256', $account->id()."\0".$phone."\0".$code);
    }
}
