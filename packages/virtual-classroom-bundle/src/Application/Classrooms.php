<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\VirtualClassroom\Attendance\AttendanceSheet;
use Phil\VirtualClassroom\Attendance\Interval;
use Phil\VirtualClassroom\Contract\RoomEvent;
use Phil\VirtualClassroom\Contract\VirtualClassroom;
use Phil\VirtualClassroom\Event\ParticipantJoined;
use Phil\VirtualClassroom\Event\ParticipantLeft;
use Phil\VirtualClassroom\Event\RecordingReady;
use Phil\VirtualClassroom\Event\RoomEnded;
use Phil\VirtualClassroom\Exception\InvalidResponse;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Exception\ProviderRefused;
use Phil\VirtualClassroom\Exception\ProviderUnavailable;
use Phil\VirtualClassroom\Exception\Unsupported;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;
use Phil\VirtualClassroom\RoomState;
use Phil\VirtualClassroom\Webhook;
use Phil\VirtualClassroomBundle\Application\Contract\CallbackUrls;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Domain\Event\AttendanceUpdated;
use Phil\VirtualClassroomBundle\Domain\Event\ClassroomEnded;
use Phil\VirtualClassroomBundle\Domain\Event\ClassroomOpened;
use Phil\VirtualClassroomBundle\Domain\Event\ParticipantArrived;
use Phil\VirtualClassroomBundle\Domain\Event\ParticipantDeparted;
use Phil\VirtualClassroomBundle\Domain\Event\RecordingAvailable;
use Phil\VirtualClassroomBundle\Domain\Exception\InvalidRoom;
use Phil\VirtualClassroomBundle\Domain\Exception\RoomClosed;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownProvider;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownRoom;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The rooms of the application: scheduled, joined — the room opened on its server at the first join, the link
 * written for the participant's role —, ended, synced with what the server says, and told what its notifications
 * say. The stays of the participants make the attendance; a notification delivered twice changes nothing.
 *
 * Every event is dispatched with identifiers only, after the store has kept what it says.
 */
final readonly class Classrooms
{
    private const string UTC = 'UTC';

    public function __construct(
        private ClassroomStore $store,
        private Providers $providers,
        private CallbackUrls $callbacks,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Schedules a room; it opens on its server when its first participant joins it.
     *
     * @param ?string $provider the name of the server, the default one when null
     * @param ?string $owner    what the room belongs to, as the owner names it: `course:42`, `meeting/7`
     *
     * @throws UnknownProvider when no server has this name
     * @throws InvalidRoom     when a value is out of its bounds
     */
    public function schedule(string $title, ?string $provider = null, bool $record = false, ?int $maxMinutes = null, ?string $owner = null, ?RoomId $id = null): Room
    {
        $provider ??= $this->providers->defaultName();

        if (!$this->providers->has($provider)) {
            throw UnknownProvider::of($provider);
        }

        $room = new Room($id ?? RoomId::generate(), $provider, $title, $record, $maxMinutes, $owner, $this->now());
        $this->store->saveRoom($room);

        return $room;
    }

    /**
     * @throws UnknownRoom when no room has this identifier
     */
    public function room(RoomId $id): Room
    {
        return $this->store->room($id) ?? throw UnknownRoom::of($id->value);
    }

    public function ofOwner(string $owner): ?Room
    {
        return $this->store->roomOfOwner($owner);
    }

    /**
     * Opens the room on its server if needed, and writes the participant's link to it.
     *
     * @throws UnknownRoom         when no room has this identifier
     * @throws RoomClosed          when the room has ended
     * @throws UnknownProvider     when its server is no longer configured
     * @throws ProviderUnavailable when its server cannot be reached in time
     * @throws ProviderRefused     when its server refuses the room
     * @throws InvalidResponse     when its server answers what cannot be read
     */
    public function joinUrl(RoomId $id, Participant $participant, Role $role): string
    {
        $room = $this->room($id);

        if ($room->hasEnded()) {
            throw RoomClosed::of($id->value);
        }

        $provider = $this->providers->get($room->provider);
        $opened = $provider->open(new RoomSpec(
            $room->id,
            $room->title,
            $room->record,
            $room->maxMinutes,
            $this->callbacks->ended($room->provider, $room->id),
            $this->callbacks->events($room->provider, $room->id),
        ));
        $first = $room->open($opened->sessionKey, $this->now());
        $this->store->saveRoom($room);

        if ($first) {
            $this->events?->dispatch(new ClassroomOpened($id->value));
        }

        return $provider->joinUrl($opened, $participant, $role);
    }

    /**
     * Ends the room for everyone, on its server when it can, and closes the stays still open.
     *
     * @throws UnknownRoom         when no room has this identifier
     * @throws UnknownProvider     when its server is no longer configured
     * @throws ProviderUnavailable when its server cannot be reached in time
     * @throws ProviderRefused     when its server refuses the call
     * @throws InvalidResponse     when its server answers what cannot be read
     */
    public function end(RoomId $id): void
    {
        $room = $this->room($id);

        if ($room->hasEnded()) {
            return;
        }

        $provider = $this->providers->get($room->provider);

        if ($room->openedAt instanceof DateTimeImmutable && $provider->capabilities()->end) {
            $provider->end($id);
        }

        $this->ended($room, $this->now());
    }

    /**
     * Asks the server who is in an open room and what it recorded: an arrival or a departure is noted at the moment
     * of the question, and a room the server no longer knows ends.
     *
     * @throws UnknownRoom         when no room has this identifier
     * @throws UnknownProvider     when its server is no longer configured
     * @throws ProviderUnavailable when its server cannot be reached in time
     * @throws ProviderRefused     when its server refuses the call
     * @throws InvalidResponse     when its server answers what cannot be read
     */
    public function sync(RoomId $id): void
    {
        $room = $this->room($id);
        $provider = $this->providers->get($room->provider);

        if (!$room->hasEnded() && $room->openedAt instanceof DateTimeImmutable && $provider->capabilities()->presences) {
            $this->reconcile($room, $provider);
        }

        if ($room->record && $provider->capabilities()->recordings) {
            $this->fetchRecordings($room, $provider);
        }
    }

    /**
     * Applies what a notification says, once the provider checked it.
     *
     * @param ?RoomId $room the room the address of the notification names, which every event must be of; null for
     *                      the one address of an account
     *
     * @return int how many events changed something
     *
     * @throws UnknownProvider when no server has this name
     * @throws Unsupported     when the server sends no notifications
     * @throws InvalidWebhook  when the notification is refused
     */
    public function receive(string $provider, ?RoomId $room, Webhook $webhook): int
    {
        $server = $this->providers->get($provider);
        $changed = 0;
        /** @var array<string, Room> $touched */
        $touched = [];
        /** @var array<string, true> $stayed the rooms whose stays changed */
        $stayed = [];

        foreach ($server->receive($webhook) as $event) {
            if ($room instanceof RoomId && !$room->equals($event->room)) {
                throw InvalidWebhook::because('The notification names another room than its address.');
            }

            $target = $touched[$event->room->value] ?? $this->store->room($event->room);

            if (null === $target || $target->provider !== $provider) {
                continue;
            }

            $touched[$target->id->value] = $target;

            if (!$this->apply($target, $server, $event)) {
                continue;
            }

            ++$changed;

            if ($event instanceof ParticipantJoined || $event instanceof ParticipantLeft) {
                $stayed[$target->id->value] = true;
            }
        }

        foreach ($touched as $key => $target) {
            if ($target->hasEnded() && null !== $target->endedAt && ($stayed[$key] ?? false)) {
                // A stay reported after the end, with no departure, ends with the room.
                $this->store->leaveAll($target->id, $target->endedAt);
                $this->events?->dispatch(new AttendanceUpdated($target->id->value));
            }
        }

        return $changed;
    }

    /**
     * What each participant attended of the session: from its opening — or its first arrival, when the room never
     * opened through the application — to its end, or now while it runs.
     *
     * @return list<AttendeeReport>
     *
     * @throws UnknownRoom when no room has this identifier
     */
    public function attendance(RoomId $id): array
    {
        $room = $this->room($id);
        $stays = $this->store->stays($id);
        $start = $room->openedAt ?? $this->firstArrival($stays) ?? $room->createdAt;
        $end = $room->endedAt ?? $this->now();
        $reports = [];

        foreach ($stays as ['participant' => $participant, 'stays' => $intervals]) {
            $reports[] = new AttendeeReport(
                $participant,
                $intervals,
                AttendanceSheet::seconds($intervals, $start, $end),
                AttendanceSheet::percent($intervals, $start, $end),
            );
        }

        return $reports;
    }

    /**
     * @return list<Recording>
     */
    public function recordings(RoomId $id): array
    {
        return $this->store->recordings($id);
    }

    private function apply(Room $room, VirtualClassroom $server, RoomEvent $event): bool
    {
        return match (true) {
            $event instanceof ParticipantJoined => $this->arrived($room, $event->participant, $event->at),
            $event instanceof ParticipantLeft => $this->departed($room, $event->participant, $event->at),
            $event instanceof RoomEnded => $this->ended($room, $event->at),
            $event instanceof RecordingReady => $event->recording instanceof Recording
                ? $this->recorded($event->recording)
                : $server->capabilities()->recordings && $this->fetchRecordings($room, $server),
            default => false,
        };
    }

    private function reconcile(Room $room, VirtualClassroom $provider): void
    {
        $presences = $provider->presences($room->id);
        $now = $this->now();

        if (RoomState::Gone === $presences->state) {
            $this->ended($room, $now);

            return;
        }

        $present = [];

        foreach ($presences->participants as $participant) {
            $present[$participant->value] = true;
            $this->arrived($room, $participant, $now);
        }

        foreach ($this->store->present($room->id) as $participant) {
            if (!($present[$participant->value] ?? false)) {
                $this->departed($room, $participant, $now);
            }
        }
    }

    private function fetchRecordings(Room $room, VirtualClassroom $provider): bool
    {
        $known = false;

        foreach ($provider->recordings($room->id) as $recording) {
            $known = $this->recorded($recording) || $known;
        }

        return $known;
    }

    private function recorded(Recording $recording): bool
    {
        if (!$this->store->saveRecording($recording)) {
            return false;
        }

        $this->events?->dispatch(new RecordingAvailable($recording->room->value, $recording->id));

        return true;
    }

    private function arrived(Room $room, ParticipantId $participant, DateTimeImmutable $at): bool
    {
        if (!$this->store->arrive($room->id, $participant, $at)) {
            return false;
        }

        $this->events?->dispatch(new ParticipantArrived($room->id->value, $participant->value));

        return true;
    }

    private function departed(Room $room, ParticipantId $participant, DateTimeImmutable $at): bool
    {
        if (!$this->store->leave($room->id, $participant, $at)) {
            return false;
        }

        $this->events?->dispatch(new ParticipantDeparted($room->id->value, $participant->value));

        return true;
    }

    private function ended(Room $room, DateTimeImmutable $at): bool
    {
        if (!$room->end($at)) {
            return false;
        }

        $this->store->leaveAll($room->id, $at);
        $this->store->saveRoom($room);
        $this->events?->dispatch(new ClassroomEnded($room->id->value));
        $this->events?->dispatch(new AttendanceUpdated($room->id->value));

        return true;
    }

    /**
     * @param list<array{participant: ParticipantId, stays: list<Interval>}> $stays
     */
    private function firstArrival(array $stays): ?DateTimeImmutable
    {
        $first = null;

        foreach ($stays as ['stays' => $intervals]) {
            foreach ($intervals as $interval) {
                if (null === $first || $interval->joinedAt < $first) {
                    $first = $interval->joinedAt;
                }
            }
        }

        return $first;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone(self::UTC));
    }
}
