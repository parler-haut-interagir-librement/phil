<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;
use Symfony\Component\Uid\Uuid;

/**
 * Someone who answers a scheduling poll by their personal link: a name, an address if they gave one to receive the date
 * chosen, an account if they have one, the time zone they read the dates in, and their answer for each date.
 *
 * Their link is an invitation of `phil/access-links`, whose token only its hash names; the respondent keeps the
 * identifier of that invitation, never the token. Once the poll is closed and the delay of the configuration has run,
 * the name and the address are erased; the answers stay, without anyone behind them.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_scheduling_poll_respondent_invitation', columns: ['invitation'])]
#[ORM\Index(name: 'phil_scheduling_poll_respondent_poll', columns: ['poll'])]
#[ORM\Index(name: 'phil_scheduling_poll_respondent_account', columns: ['account'])]
#[ORM\Index(name: 'phil_scheduling_poll_respondent_email', columns: ['email'])]
final class Respondent
{
    public const string TABLE = 'phil_scheduling_poll_respondent';

    public const int MAX_NAME = 100;

    public const int MAX_EMAIL = 254;

    /** @var array<array-key, string> the answer to each slot, by its identifier, which PHP may turn into a number */
    #[ORM\Column(name: 'answers', type: 'json')]
    private array $answers = [];

    #[ORM\Column(name: 'name', length: self::MAX_NAME, nullable: true)]
    private ?string $name;

    #[ORM\Column(name: 'email', length: self::MAX_EMAIL, nullable: true)]
    private ?string $email;

    #[ORM\Column(name: 'account', length: 320, nullable: true)]
    private readonly ?string $account;

    #[ORM\Column(name: 'timezone', length: 64, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(name: 'answered_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $answeredAt = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(name: 'notified_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $notifiedAt = null;

    #[ORM\Column(name: 'erased_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $erasedAt = null;

    #[ORM\Column(name: 'joined_at', type: 'datetime_utc_immutable')]
    private DateTimeImmutable $joinedAt;

    /**
     * @throws InvalidSchedulingPoll when the name is empty or too long, or the address is not one
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'poll', length: 36, options: ['fixed' => true])]
        public readonly string $poll,
        #[ORM\Column(name: 'invitation', length: 32, options: ['fixed' => true])]
        public readonly string $invitation,
        string $name,
        DateTimeImmutable $joinedAt,
        ?string $email = null,
        ?ResourceReference $account = null,
        #[ORM\Column(name: 'locale', length: 16, nullable: true)]
        public readonly ?string $locale = null,
    ) {
        if (!Uuid::isValid($id) || !Uuid::isValid($poll) || 1 !== preg_match('/^[0-9a-f]{32}$/D', $invitation)) {
            throw InvalidSchedulingPoll::because('A respondent and its poll are identified by UUIDs, its invitation by 32 hexadecimal digits.');
        }

        $this->name = self::checkName($name);
        $this->email = self::checkEmail($email);
        $this->account = $account?->toString();
        $this->joinedAt = SchedulingPoll::utc($joinedAt);

        if (null !== $locale && 1 !== preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8}){0,2}$/D', $locale)) {
            throw InvalidSchedulingPoll::because('The locale of a respondent is a language tag, such as "fr" or "fr_CA".');
        }
    }

    /**
     * The name as kept: trimmed, without control characters.
     *
     * @throws InvalidSchedulingPoll when the name is empty or longer than 100 characters
     */
    public static function checkName(string $name): string
    {
        $name = trim(preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $name) ?? '');

        if ('' === $name || mb_strlen($name) > self::MAX_NAME) {
            throw InvalidSchedulingPoll::because(\sprintf('The name of a respondent is 1 to %d characters.', self::MAX_NAME));
        }

        return $name;
    }

    /**
     * The address as kept, read by `phil/email-address`: the part before the @ as written, the domain in ASCII, or
     * `null` when none is given.
     *
     * @throws InvalidSchedulingPoll when the address is not one a mail server can deliver to
     */
    public static function checkEmail(?string $email): ?string
    {
        if ('' === trim((string) $email)) {
            return null;
        }

        try {
            return EmailAddress::parse((string) $email)->toAscii();
        } catch (InvalidEmailAddress) {
            throw InvalidSchedulingPoll::because('The address of a respondent is an email address, such as "ada@example.org".');
        }
    }

    /**
     * The name, `null` once erased.
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * The address, `null` when none was given or once erased.
     */
    public function email(): ?string
    {
        return $this->email;
    }

    public function account(): ?ResourceReference
    {
        return null === $this->account ? null : ResourceReference::fromString($this->account);
    }

    /**
     * The time zone the respondent last chose to read the dates in, `null` before.
     */
    public function timezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * The answer to a slot, `null` when the respondent did not answer.
     */
    public function answerTo(string $slot): ?Availability
    {
        return isset($this->answers[$slot]) ? Availability::tryFrom($this->answers[$slot]) : null;
    }

    /**
     * @return array<string, Availability> the answers by slot
     */
    public function answers(): array
    {
        $answers = [];

        foreach ($this->answers as $slot => $answer) {
            $availability = Availability::tryFrom($answer);

            if ($availability instanceof Availability) {
                $answers[(string) $slot] = $availability;
            }
        }

        return $answers;
    }

    public function joinedAt(): DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function answeredAt(): ?DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function hasAnswered(): bool
    {
        return $this->answeredAt instanceof DateTimeImmutable;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof DateTimeImmutable;
    }

    public function notifiedAt(): ?DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function erasedAt(): ?DateTimeImmutable
    {
        return $this->erasedAt;
    }

    /**
     * Records the answers, as the store wrote them in its conditional write.
     *
     * @param array<string, Availability> $answers
     */
    public function answer(array $answers, ?string $timezone, DateTimeImmutable $at): void
    {
        $this->answers = array_map(static fn (Availability $answer): string => $answer->value, $answers);
        $this->timezone = $timezone;
        $this->answeredAt = SchedulingPoll::utc($at);
    }

    public function revoke(DateTimeImmutable $at): void
    {
        $this->revokedAt ??= SchedulingPoll::utc($at);
    }

    public function notified(DateTimeImmutable $at): void
    {
        $this->notifiedAt = SchedulingPoll::utc($at);
    }

    /**
     * Gives back a notice that could not be sent.
     */
    public function releaseNotice(): void
    {
        $this->notifiedAt = null;
    }

    /**
     * Erases the name, the address and the time zone; the answers stay, without anyone behind them.
     */
    public function erase(DateTimeImmutable $at): void
    {
        $this->name = null;
        $this->email = null;
        $this->timezone = null;
        $this->erasedAt ??= SchedulingPoll::utc($at);
    }
}
