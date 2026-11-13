# Configuration

The bundle's configuration lives under `phil_access`:

```yaml
# config/packages/phil_access.yaml
phil_access:
    store: dbal
    connection: default
    table: phil_relation_tuple
    space: main
    item_kinds: [page, document]
    sharing_levels: [manage, edit, comment, view]
    default_visibility: owner
    groups:
        supporters:
            entry_points: [purchase, invitation]
            duration: P1M
        newcomers:
            entry_points: [default]
    budget:
        max_depth: 32
        max_reads: 500
        max_fan_out: 200
        max_candidates: 1000
    current_member: ~
    view_as:
        redirect: /
    easyadmin:
        actions: { detail: view, edit: edit, delete: manage }
    kit_subdirectory: ~
```

| Option | Default | What it does |
|---|---|---|
| `store` | `dbal` | Where the tuples are kept: `dbal`, or `memory` for tests. |
| `connection` | `default` | The Doctrine DBAL connection of the tuple table. |
| `table` | `phil_relation_tuple` | The name of the tuple table. |
| `space` | `main` | The identifier of the site's space, whose members and admins the visibilities name. |
| `item_kinds` | none | The kinds of items whose access the bundle decides. `member`, `visitor`, `visibility`, `space` and `group` are reserved. |
| `sharing_levels` | `[manage, edit, comment, view]` | The levels, the widest first; each includes those after it, and the last is what a visibility opens. 1 to 16 names of 1 to 32 lowercase letters, digits or `_`; `owner`, `space` and `visibility` are reserved. |
| `default_visibility` | `owner` | The visibility of an item registered without one: `everyone`, `members`, `admins` or `owner`. |
| `groups` | none | The access groups, by name: their `entry_points` — `purchase`, `invitation`, `bulk`, `automation`, `default` — and a `duration`, an ISO 8601 duration after which a membership ends on its own. |
| `budget` | as shown | The limits of one check or listing: depth, reads of the store, sets behind one relation, candidates of a listing; 1 to 100,000 each. |
| `current_member` | none | The service that names the member making the request; the user identifier of the security token with SecurityBundle, nobody without it. |
| `view_as.redirect` | `/` | The path of the site the forms of "view as this member" go back to; a path, never an address. |
| `easyadmin.actions` | as shown | Each EasyAdmin action and the level it needs. Without it, the default levels map `detail`, `edit` and `delete`; other levels map `detail` to the narrowest. |
| `kit_subdirectory` | none | Where the recipes of the kit are, under the components directory; the subdirectory of `ux_toolkit` when null. |
