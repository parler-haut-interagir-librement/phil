<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Fixture;

use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\IncidentRestrictions;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\DurationInput;
use Symfony\Component\Clock\MockClock;

/**
 * The incident mode in memory, on a clock the test moves, with a checkpoint for the member it chooses; `ada` is an
 * established member, spared from new posts.
 */
final readonly class Stage
{
    public InMemoryActivationStore $store;

    public MockClock $clock;

    public IncidentMode $incidents;

    public Member $member;

    public Established $established;

    public Checkpoint $checkpoint;

    /**
     * @param list<string> $spared
     */
    public function __construct(array $spared = [Restriction::NEW_POSTS], int $status = 503, ?Presets $presets = null)
    {
        $this->store = new InMemoryActivationStore();
        $this->clock = new MockClock('2026-11-17 10:00:00 UTC');
        $this->incidents = new IncidentMode($presets ?? Presets::defaults(), $this->store, $this->clock);
        $this->member = new Member();
        $this->established = new Established(['ada']);
        $this->checkpoint = new Checkpoint($this->incidents, new IncidentRestrictions($this->incidents, $this->established, $spared), $this->member, $this->clock, $status);
    }

    public function start(string $preset, string $duration = '2h'): Activation
    {
        return $this->incidents->start($preset, DurationInput::parse($duration), Actor::of('admin-1'), 'Spam wave');
    }
}
