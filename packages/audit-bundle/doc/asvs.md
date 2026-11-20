# ASVS 5.0 mapping

The requirements of [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) chapter V16 the bundle meets,
with the test that shows each one, and those it leaves to the application. The guide of the monorepo,
[ASVS 5.0: what the application keeps](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md), gathers what every package leaves.

## Met by the bundle

| Requirement | Level | How | Shown by |
|---|---|---|---|
| 16.2.2 Timestamps in UTC or with their offset | 2 | Every instant is turned to UTC, to the microsecond, before it is hashed and stored, whatever the zone of the clock; text order is time order | `AsvsLogIntegrityTest::testAClockInAnotherZoneWritesUtcInstantsThatVerifyAfterStorage`, `testTheEndOfDaylightSavingTimeKeepsTimeOrder` |
| 16.4.1 Log data encoded against injection | 2 | Facts are identifiers: an action, an actor, a subject or a tenant with a carriage return, a NUL byte, a terminal escape or any control character is refused, and a stored row holding one is not intact | `AsvsLogIntegrityTest::testNoFactCarriesAControlCharacter`, `testARowWhoseStoredFactsCarryAControlCharacterIsNotIntact` |
| 16.4.2 Logs protected from modification | 2 | Rows chained by hash, segment heads anchored outside the database, `phil:audit:verify` | [The hash chain](hash-chain.md), [anchoring](anchoring.md) and their tests |
| 16.4.3 Logs sent to a separate system | 2 | Anchors written elsewhere; the feed exports facts as JSON Lines or OpenTelemetry logs | [Feeding dashboards](dashboards.md) and its tests |
| 16.2.5 Sensitive data logged by its protection level | 2 | Personal fields kept as salted commitments, erased by forgetting their salt | [Salted commitments and erasure](commitments-and-erasure.md) and its tests |
| 16.2.1 When, who, what | 2 | The instant, the actor and the action of each row, and its subject; where is left to the application | [Reading the log](reading.md) and its tests |
| 8.4.1 No operation reaches another tenant | 2 | A tenant reads and verifies only its own rows | `TenantIsolationTest` |

## Left to the application

| Requirement | Level | Why it is the application's |
|---|---|---|
| 16.1.1 An inventory of what is logged, where and for how long | 2 | The application's documentation, which lists the segments it records |
| 16.2.1 Where an event happened | 2 | The application adds its host, route or process to the payload |
| 16.3.1 to 16.3.4 Authentication, authorization failures, bypass attempts, unexpected errors | 2 | The events to record are the application's; the bundle records what it is given, and its Workflow integration records changes of state |
| 16.4.2 for the database role | 2 | The role the application connects with must not hold `UPDATE` or `DELETE` on the audit table |
