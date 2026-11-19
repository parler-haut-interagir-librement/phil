# Extension points

The library is in its `0.x` releases: the port, the value objects and the exceptions may change in a minor release,
said in the changelog. They are meant to be stable from `1.0`.

## Another provider

An adapter implements `Contract\VirtualClassroom`:

- `capabilities()` says what it can do; every call it cannot do throws `Exception\Unsupported`;
- `open()` is idempotent for a running room, and gives the key of the session when the server has one;
- `joinUrl()` calls no server, and signs what the server needs to trust the role;
- `receive()` checks the provider's own signature before anything, and gives the events in the order they happened;
- every failure is a `VirtualClassroomException`, its message free of participants and of call addresses;
- the adapter keeps no state between calls, so that one instance serves every request of a worker;
- a call to a server goes through `Http\HttpBudget`'s bounds.

A Zoom or Microsoft Teams adapter, the ones planned next, is written so: an OAuth client of the provider's, its
meetings as rooms, its webhooks as notifications.

## Events

`Contract\RoomEvent` has `room` and `at`; the four events of the library implement it. An adapter that reads other
events of its server maps them to these, or leaves them out.
