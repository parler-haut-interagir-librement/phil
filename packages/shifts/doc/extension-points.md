# Extension points

The library is in its `0.x` releases: the ports, the value objects and the exceptions may change in a minor release,
said in the changelog. They are meant to be stable from `1.0`.

| Port | Implemented by | Does |
|---|---|---|
| `Allocation\InterestAllocator` | `FairAllocator`, or the application's own | chooses who gets the free places among the interested |
| `Event\ShiftEvent` | the eight events | says what changed, by identifiers |

## An allocator of one's own

An allocator receives the interested in the order they expressed their interest, the number of free places and the
minutes each volunteer holds elsewhere, and returns the volunteers it chooses. It may weigh what the library does not
know — seniority, a training, a wish to work with a friend. The roster keeps only the volunteers it returns that are
interested, once each, and no more than the places, so that an allocator cannot overfill a shift.

```php
use Phil\Shifts\Allocation\InterestAllocator;

final readonly class TrainedFirstAllocator implements InterestAllocator
{
    public function __construct(private TrainingRecords $records) {}

    public function choose(array $interested, int $places, array $minutesHeld): array
    {
        $volunteers = array_map(static fn ($place) => $place->volunteer, $interested);
        usort($volunteers, fn (string $a, string $b): int => $this->records->isTrained($b) <=> $this->records->isTrained($a));

        return array_slice($volunteers, 0, $places);
    }
}
```

## The storage

The library keeps nothing. An application stores a shift, its places and the offers of its swap board with the
formats — `ShiftFormat`, `RosterFormat`, `SwapFormat` — and the columns it searches by: the window of the shift, the
volunteers of its places, the shift and the status of an offer. Rosters and offers are immutable; two requests that
change the same roster at once are told apart by the storage, with a revision compared on each write.

## The events

The events of a change are in the `$changes` of the roster an operation returns. They carry the identifiers of the
shift and of the volunteer only — never a name, an address or a note —, so that an application may put them in a queue
as they are; a listener reads the rest from the store. An application dispatches them after the roster is stored.

## The conflicts and the roles

The roster does not see the volunteer's other shifts nor where the roles come from: an application gives the roles of
the volunteer on each operation, and checks `Conflicts` before a sign-up, an assignment or a swap.
