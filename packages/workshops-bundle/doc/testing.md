# Testing

| Area | What is covered |
|---|---|
| `Workshop`, `WorkshopSession`, `Registration` | Every value and its refusal, the window and its bounds, the states and their changes, moments on the session's clock |
| `Workshops` | Each method on repositories in memory: the lock, each refusal and its order, registering twice, the seats a waiting list claims never taken, the capacity never below the seats taken and offered, the events and their order |
| Doctrine repositories | SQLite in memory: what is kept, moments stored and read in UTC in a process of another zone, each query's filters and order, the cascade of a workshop removed, the lock committed, rolled back and reading the session again |
| Messages | Each refusal and status in English and French, both languages with every message |
| The waiting lists | A kernel with phil/waitlist-bundle on SQLite: joining and its refusals, freed seats offered in turn and nobody skipping the line, offers accepted into registrations, places held for nobody given back, more and less capacity, a session cancelled closing its list, lists of other subjects left alone |
| The bundle | Its services and options, their bounds, the integration added and removed, the mapping, the recipe, and a kernel on SQLite with a listener of the events |

Infection holds the bundle at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/workshops-bundle/tests
```

The tests need no server: SQLite runs in memory.
