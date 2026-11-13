<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

use DateTimeImmutable;
use Phil\AccessLinks\Contract\InvitationStore;
use Phil\AccessLinks\Contract\ReminderSender;
use Phil\AccessLinks\Exception\AlreadyInvited;
use Phil\AccessLinks\Exception\InvalidConfiguration;
use Phil\AccessLinks\Exception\InvalidToken;
use Phil\AccessLinks\Exception\InvitationClosed;
use Phil\AccessLinks\Exception\LinkNotUsable;
use Phil\AccessLinks\Exception\UnknownInvitation;
use Psr\Clock\ClockInterface;
use Random\Engine\Secure;
use Random\Randomizer;
use SensitiveParameter;

/**
 * The library in one object: recipients invited by personal link, links opened and answered until the closure,
 * reminders sent to those who have not answered, invitations revoked and counted.
 *
 * Every moment comes from the clock and is compared by order, never by equality. The object keeps no state between
 * calls: it is safe in a worker that serves many requests.
 */
final readonly class AccessLinks
{
    /** The most links one invitation may have: the first, one per reminder, and those issued again by hand. */
    public const int MAX_LINKS = 16;

    private Randomizer $randomizer;

    public function __construct(
        private InvitationStore $store,
        private TokenHasher $hasher,
        private LinkFormat $format,
        private ClockInterface $clock,
        private ReminderSchedule $reminders = new ReminderSchedule(),
        ?Randomizer $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer(new Secure());
    }

    /**
     * Invites a recipient, named by a stable identifier of the application, to a campaign, and issues the first link.
     *
     * @param DateTimeImmutable      $closesAt  when the answer stops changing
     * @param DateTimeImmutable|null $expiresAt when the link stops opening, the closure by default
     * @param DateTimeImmutable|null $validFrom when the link starts opening, now by default
     *
     * @throws AlreadyInvited       when the recipient is already invited to the campaign
     * @throws InvalidConfiguration when a value is out of bounds or the moments are out of order
     */
    public function invite(string $campaign, string $recipient, DateTimeImmutable $closesAt, ?DateTimeImmutable $expiresAt = null, ?DateTimeImmutable $validFrom = null): IssuedLink
    {
        $now = $this->clock->now();

        if ($closesAt <= $now) {
            throw InvalidConfiguration::because('An invitation closes after it is sent.');
        }

        $invitation = new Invitation(
            $this->randomizer->getBytesFromString('0123456789abcdef', 32),
            $campaign,
            $recipient,
            $now,
            $closesAt,
            $expiresAt,
            $validFrom,
            nextReminderAt: $this->reminders->next($now, 0, $closesAt),
        );
        $token = LinkToken::generate($this->randomizer);

        if (!$this->store->add($invitation, $this->hasher->hash($token))) {
            throw AlreadyInvited::to($campaign, $recipient);
        }

        return new IssuedLink($invitation, $token, $this->format->for($token));
    }

    /**
     * Issues another link to an invitation, for a recipient who lost theirs; the earlier links keep opening.
     *
     * @throws UnknownInvitation
     * @throws InvalidConfiguration when the invitation has its sixteen links already
     */
    public function reissue(string $invitationId): IssuedLink
    {
        $invitation = $this->invitation($invitationId);
        $token = LinkToken::generate($this->randomizer);

        if (!$this->store->addLink($invitationId, $this->hasher->hash($token), self::MAX_LINKS)) {
            throw InvalidConfiguration::because(\sprintf('An invitation has %d links at the most.', self::MAX_LINKS));
        }

        return new IssuedLink($invitation, $token, $this->format->for($token));
    }

    /**
     * What a link opens now, or `null` when it opens nothing: malformed, unknown, revoked, not yet valid or expired.
     * Opening changes nothing, so that the preview of a mail client spends nothing.
     */
    public function open(#[SensitiveParameter] string $token): ?InvitationAccess
    {
        $invitation = $this->find($token);
        $now = $this->clock->now();

        if (!$invitation instanceof Invitation || !$invitation->opensAt($now)) {
            return null;
        }

        return new InvitationAccess($invitation, $invitation->isEditableAt($now));
    }

    /**
     * Records the recipient's answer, or changes it, until the invitation closes.
     *
     * @param string $answer what the recipient answered, as the application encodes it: 1 to 4096 bytes of UTF-8
     *
     * @throws LinkNotUsable        when the link opens nothing
     * @throws InvitationClosed     when the invitation is closed
     * @throws InvalidConfiguration when the answer is out of bounds
     */
    public function respond(#[SensitiveParameter] string $token, string $answer): Invitation
    {
        Invitation::checkAnswer($answer);
        $invitation = $this->find($token);
        $now = $this->clock->now();

        if (!$invitation instanceof Invitation || !$invitation->opensAt($now)) {
            throw LinkNotUsable::create();
        }

        if (!$this->store->respond($invitation->id, $answer, $now)) {
            throw InvitationClosed::create();
        }

        return $this->invitation($invitation->id);
    }

    /**
     * Revokes an invitation: its links open nothing more. Returns `false` for one revoked before.
     *
     * @throws UnknownInvitation
     */
    public function revoke(string $invitationId): bool
    {
        $this->invitation($invitationId);

        return $this->store->revoke($invitationId, $this->clock->now());
    }

    /**
     * The invitations whose reminder is due now.
     *
     * @param positive-int $limit
     *
     * @return list<string> their identifiers
     */
    public function dueReminders(int $limit = 100): array
    {
        return $this->store->due($this->clock->now(), $limit);
    }

    /**
     * Sends the due reminder of an invitation with a fresh link, if it still has no answer: the reminder is claimed
     * first, so that two workers never send it twice, then the link is issued and handed to the sender. Returns whether
     * a reminder was sent.
     *
     * A sender that fails after the claim loses that reminder rather than sending it twice; the next one falls due on
     * schedule.
     *
     * @throws UnknownInvitation
     */
    public function remind(string $invitationId, ReminderSender $sender): bool
    {
        $invitation = $this->invitation($invitationId);
        $now = $this->clock->now();
        $next = $this->reminders->next($invitation->createdAt, $invitation->remindersSent + 1, $invitation->closesAt);

        if (!$this->store->claimReminder($invitationId, $invitation->remindersSent, $next, $now)) {
            return false;
        }

        $token = LinkToken::generate($this->randomizer);

        if (!$this->store->addLink($invitationId, $this->hasher->hash($token), self::MAX_LINKS)) {
            return false;
        }

        $sender->sendReminder(new IssuedLink($this->invitation($invitationId), $token, $this->format->for($token)));

        return true;
    }

    /**
     * @throws UnknownInvitation
     */
    public function invitation(string $invitationId): Invitation
    {
        return $this->store->get($invitationId) ?? throw UnknownInvitation::withId($invitationId);
    }

    public function counts(string $campaign): CampaignCounts
    {
        return $this->store->counts($campaign);
    }

    private function find(#[SensitiveParameter] string $token): ?Invitation
    {
        try {
            return $this->store->findByToken($this->hasher->candidates(LinkToken::parse($token)));
        } catch (InvalidToken) {
            return null;
        }
    }
}
