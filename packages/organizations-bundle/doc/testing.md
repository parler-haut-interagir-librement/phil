# Testing

## The bundle's tests

The suite `tests/Unit` tests the identifiers, names, roles, domains and unit paths and their rules, the model and the
states of an invitation, the catalogues of roles, `Organizations`, `Members`, `Teams`, `Units`, `UnitAccess`,
`UnitTree`, `Invitations`, `DomainJoin`, `OnBehalf`, `OwnershipCheck` and `MemberErasure` on a store in memory at a
fixed time — the roles inherited down the tree, cycles and depth refused, a subtree archived and restored together, one
unit per member when configured, the bound on grants, the last owner, the read-only archive, the reasons an
invitation is refused, a use lost to another member, the events dispatched after commit and none after a rollback —, one
contract for every store, run in memory and on Doctrine DBAL with SQLite in memory, the tables of each platform and
their collations, the schema listener and the migration of the tables, the voter, the four commands, the administration
pages with a template engine stand-in, the Flex recipes, the exporter and eraser of `phil/privacy-bundle`, the relations
and tuples of `phil/access-bundle` — partitions included —, and the configuration and wiring.

The suite `tests/Integration` runs the same contract on MySQL 8.4 and PostgreSQL 17, with the byte-for-byte comparison
of the key columns, the last use of an invitation taken by two connections, the paths of units moved in one statement,
and the tables of the units added to a database of the first version. It reads the servers from
`PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`, which `vendor/bin/phil-dev services up` starts in Docker, and
skips without them.

From the monorepo root:

```sh
vendor/bin/phpunit packages/organizations-bundle/tests
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)" && vendor/bin/phpunit packages/organizations-bundle/tests/Integration
```

The bundle is held to 100 % covered mutation score by Infection.

The suite `tests/Functional` runs the administration pages of the units in a small application, drawn with the recipes
of the kit.

## The benchmarks

`tests/Benchmark/PermissionCheckBench.php` measures what a page pays for a check: the permissions of a set of roles,
`OrganizationAccess::can()` on a store in memory, and the vote of `OrganizationVoter` from a security token — a few
microseconds each on a laptop. `tests/Benchmark/UnitTreeBench.php` measures, on the DBAL store over SQLite and a tree
of 5 850 units, the permissions inherited four levels down, the units and the members below a unit, and a move of 73
units: from about 80 µs for a check to under a millisecond for a move.

## Testing an application

`store: memory` keeps the organizations in memory, emptied between requests by `kernel.reset`:

```yaml
# config/packages/test/phil_organizations.yaml
phil_organizations:
    store: memory
    invitations:
        hash_key: 'a-test-key-of-thirty-two-bytes-at-least'
```

With `symfony/clock`'s `MockClock` as the `clock` service, the moments of invitations and their expiries are under
the test's control. A test listens to the events of `Domain\Event` through the event dispatcher to see what changed.
