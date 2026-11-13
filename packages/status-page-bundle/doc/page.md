# The page

`config/routes.php` declares three routes, each answering `GET` and `HEAD`:

| Route | Path | Answer |
|---|---|---|
| `phil_status_page` | `/status` | The page, `text/html` |
| `phil_status_page_json` | `/status.json` | The JSON, `application/json` |
| `phil_status_page_atom` | `/status.atom` | The Atom feed, `application/atom+xml` |

Each answer carries `Cache-Control: public, max-age=<cache_max_age>` — `no-cache` with 0 — and
`X-Content-Type-Options: nosniff`; the JSON and the feed carry `Access-Control-Allow-Origin: *`, so a page of another
site may read them. No answer reads the session or sets a cookie, and the page loads nothing from another site.

## What the page shows

1. The title and the overall state: the worst state of the components, operational without any.
2. The incidents going on, newest first: title, impact, state, the components they affect, and their updates, newest
   first, each with its state, its moment in UTC and its message.
3. The maintenances in progress, then upcoming, soonest first: title, window in UTC, components and description.
4. The components, grouped under their `group` in the order the first member of each was declared, each with its
   state.
5. The incidents resolved within the last `history_days` days, newest first.
6. The moment the page was put together, and links to the feed and the JSON.

Each part is a `<section>` labelled by its heading; incidents, maintenances and components are list items with the ids
`incident-<id>`, `maintenance-<id>` and `component-<id>`, the fragments the feed links to. A state is a `Badge` with its
name written: `outline` when operational, `secondary` under maintenance or degraded, `destructive` partly down or down.
The page carries no script and no style of its own; the stylesheet of the kit, loaded by the application's layout,
gives the recipes their look.

The templates are `@PhilStatusPage/page.html.twig` and its partials `_badge`, `_moment`, `_incident` and
`_maintenance`, within `layout`. The texts come from the translation domain `phil_status_page`, in English and French;
another language is a file `translations/phil_status_page.<locale>.php` (or `.yaml`, `.xlf`) of the application, with
the keys of the English file.

## The state of a component

1. The source gives a state: the declared `status`, or the checks of `phil/monitoring-bundle` (below). A component the
   source leaves out is operational.
2. While a maintenance in progress names the component, it is under maintenance, whatever the source said.
3. Each incident going on that names it makes its state the worse of the two, in the order operational, under
   maintenance, degraded, partly down, down.

### From phil/monitoring-bundle

`MonitoringStatuses` reads `MonitoringStatus`, the read model of `phil/monitoring-bundle`:

| What the surveillance says | The component |
|---|---|
| Every check it names is up, every task it names on time | Operational |
| The worst of its checks is degraded, or one of its tasks is late | Degraded |
| The worst of its checks is down | Down |
| The maintenance mode of the site is on | Under maintenance, every component |
| It names no check nor task the surveillance knows | Its declared `status` |

A task that never beat changes nothing. The checks run once per `monitoring.cache_ttl` seconds at most: the states are
kept in `monitoring.cache_pool` as plain values under one key, so the public page never runs the checks on each view.

## The JSON

```json
{
    "version": "1",
    "page": { "title": "Example status", "url": "https://example.org/status", "generated_at": "2026-11-12T12:00:00+00:00" },
    "status": "partial_outage",
    "components": [
        { "id": "api", "name": "API", "description": null, "group": "Platform", "status": "partial_outage" }
    ],
    "incidents": [
        {
            "id": "api-errors", "title": "Errors on the API", "impact": "partial_outage", "state": "identified",
            "components": ["api"], "started_at": "…", "updated_at": "…", "resolved_at": null,
            "updates": [{ "at": "…", "state": "identified", "message": "…" }]
        }
    ],
    "maintenances": [
        { "id": "upgrade", "title": "Upgrade", "description": null, "state": "upcoming", "components": ["api"], "starts_at": "…", "ends_at": "…" }
    ],
    "history": []
}
```

States are `operational`, `maintenance`, `degraded`, `partial_outage` and `major_outage`; incident states
`investigating`, `identified`, `monitoring` and `resolved`; a maintenance is `in_progress` or `upcoming`. Dates are
ISO 8601 in UTC. `incidents` holds those going on, `history` those resolved within the history. The shape is kept
within a `version`.

## The Atom feed

The feed (RFC 4287) has one entry per incident going on or in the history, then per maintenance in progress or upcoming.
An entry's id and link are the address of its part of the page, `…/status#incident-<id>`, so a reader sees an update as
a change of the same entry; its `category` is the incident's state, or `maintenance`; its content is plain text, every
update with its moment and state. The feed is dated by its latest entry, else by the page.
