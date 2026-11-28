<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo;

use Phil\ParticipantSafetyInfo\Contract\AccessJournal;
use Phil\ParticipantSafetyInfo\Contract\AccessWindows;
use Phil\ParticipantSafetyInfo\Contract\EventRoles;
use Phil\ParticipantSafetyInfo\Contract\SafetyInfoSealer;
use Phil\ParticipantSafetyInfo\Contract\SafetyInfoStore;
use Phil\ParticipantSafetyInfo\Event\SafetyInfoErased;
use Phil\ParticipantSafetyInfo\Event\SafetyInfoRead;
use Phil\ParticipantSafetyInfo\Event\SafetyInfoSubmitted;
use Phil\ParticipantSafetyInfo\Exception\AccessDenied;
use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use Phil\ParticipantSafetyInfo\Exception\UnreadableSafetyInfo;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Every path to a participant's safety information. The participant submits, reads, reuses and erases their own; the
 * responsible people of the event read it during its access window; a member of its team reads it only by breaking
 * the glass, with a reason. Each reading by someone else is written to the journal before the information is opened.
 * The information is purged once the window has closed.
 *
 * The `$person`, `$reader` and `$participant` arguments name a person the application has authenticated: the vault
 * decides what that person may do, not who they are. The service keeps no state between two calls, and is safe to
 * share across the requests of a long-running worker.
 */
final readonly class SafetyInfoVault
{
    public const int MAX_PURGE = 10_000;

    public const int MAX_JOURNAL_PAGE = 500;

    private const string CONTEXT = "phil/participant-safety-info\x1Fv1";

    public function __construct(
        private SafetyInfoStore $store,
        private SafetyInfoSealer $sealer,
        private EventRoles $roles,
        private AccessWindows $windows,
        private AccessJournal $journal,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Seals and saves the participant's own safety information for an event, replacing what they sent before; it is
     * kept until the access window closes.
     *
     * @throws AccessDenied      when the event has no window, or its window has closed
     * @throws InvalidSafetyInfo when an identifier is out of bounds
     */
    public function submit(string $event, string $participant, SafetyInfo $info): void
    {
        Identifier::check($event, 'An event');
        Identifier::check($participant, 'A participant');

        $now = $this->clock->now();
        $window = $this->windows->windowOf($event);

        if (!$window instanceof AccessWindow || $window->hasClosedAt($now)) {
            throw AccessDenied::create();
        }

        $sealed = $this->sealer->seal(SafetyInfoCodec::encode($info), $this->context($event, $participant));
        $this->store->save(new SealedSafetyInfo($event, $participant, $sealed, $now, $window->closesAt));
        $this->events?->dispatch(new SafetyInfoSubmitted($event, $participant));
    }

    /**
     * The participant's own safety information for an event, `null` when they sent none.
     *
     * @throws UnreadableSafetyInfo when the sealed text does not open
     */
    public function readOwn(string $event, string $participant): ?SafetyInfo
    {
        $record = $this->store->get(Identifier::check($event, 'An event'), Identifier::check($participant, 'A participant'));

        return $record instanceof SealedSafetyInfo ? $this->open($record) : null;
    }

    /**
     * A participant's safety information, read by a responsible person of the event during its access window;
     * `null` when the participant sent none. The reading is journaled first.
     *
     * @throws AccessDenied         when the reader is not responsible for the event, or the window is not open
     * @throws UnreadableSafetyInfo when the sealed text does not open
     */
    public function read(string $event, string $participant, string $reader): ?SafetyInfo
    {
        return $this->readAs($event, $participant, $reader, AccessReason::Responsible, null);
    }

    /**
     * A participant's safety information, read in an emergency by a member of the team or a responsible person of
     * the event during its access window, with a written reason; `null` when the participant sent none. The reading
     * and its reason are journaled first, and the `SafetyInfoRead` event lets the application tell the responsible
     * people.
     *
     * @throws AccessDenied         when the reader is not on the team of the event, or the window is not open
     * @throws InvalidSafetyInfo    when the reason does not hold 10 to 500 characters
     * @throws UnreadableSafetyInfo when the sealed text does not open
     */
    public function breakGlass(string $event, string $participant, string $reader, string $justification): ?SafetyInfo
    {
        return $this->readAs($event, $participant, $reader, AccessReason::BreakGlass, trim($justification));
    }

    /**
     * Copies the participant's own safety information from one event to another, at their request only; whether
     * there was something to copy.
     *
     * @throws AccessDenied         when the event to copy to has no window, or its window has closed
     * @throws UnreadableSafetyInfo when the sealed text does not open
     */
    public function copy(string $fromEvent, string $toEvent, string $participant): bool
    {
        $info = $this->readOwn($fromEvent, $participant);

        if (!$info instanceof SafetyInfo) {
            return false;
        }

        $this->submit($toEvent, $participant, $info);

        return true;
    }

    /**
     * Erases the participant's own safety information for an event; whether there was some.
     */
    public function erase(string $event, string $participant): bool
    {
        $erased = $this->store->delete(Identifier::check($event, 'An event'), Identifier::check($participant, 'A participant'));

        if ($erased) {
            $this->events?->dispatch(new SafetyInfoErased($event, $participant));
        }

        return $erased;
    }

    /**
     * Who read the participant's safety information for an event, most recent first: what the participant sees of
     * the journal.
     *
     * @return list<AccessRecord>
     */
    public function readingsOf(string $event, string $participant, int $limit = 100): array
    {
        if ($limit < 1 || $limit > self::MAX_JOURNAL_PAGE) {
            throw InvalidSafetyInfo::because(\sprintf('A page of the journal reads 1 to %d records.', self::MAX_JOURNAL_PAGE));
        }

        return $this->journal->recordsOf(Identifier::check($event, 'An event'), Identifier::check($participant, 'A participant'), $limit);
    }

    /**
     * Deletes up to `$limit` records whose access window has closed: the scheduled purge.
     *
     * @return int how many were deleted
     */
    public function purge(int $limit = 1_000): int
    {
        if ($limit < 1 || $limit > self::MAX_PURGE) {
            throw InvalidSafetyInfo::because(\sprintf('A purge deletes 1 to %d records at a time.', self::MAX_PURGE));
        }

        return $this->store->deleteRetainedUntilBefore($this->clock->now(), $limit);
    }

    private function readAs(string $event, string $participant, string $reader, AccessReason $reason, ?string $justification): ?SafetyInfo
    {
        $now = $this->clock->now();
        // The record checks the three identifiers and the justification before anything is read.
        $record = new AccessRecord($event, $participant, $reader, $reason, $now, $justification);
        $role = $this->roles->roleOf($event, $reader);
        $allowed = AccessReason::Responsible === $reason ? Role::Responsible === $role : Role::None !== $role;
        $window = $this->windows->windowOf($event);

        if (!$allowed || !$window instanceof AccessWindow || !$window->isOpenAt($now)) {
            throw AccessDenied::create();
        }

        $sealed = $this->store->get($event, $participant);

        if (!$sealed instanceof SealedSafetyInfo) {
            return null;
        }

        $this->journal->record($record);
        $this->events?->dispatch(new SafetyInfoRead($event, $participant, $reader, $reason));

        return $this->open($sealed);
    }

    private function open(SealedSafetyInfo $record): SafetyInfo
    {
        return SafetyInfoCodec::decode($this->sealer->open($record->sealed, $this->context($record->event, $record->participant)));
    }

    private function context(string $event, string $participant): string
    {
        return self::CONTEXT."\x1F".$event."\x1F".$participant;
    }
}
