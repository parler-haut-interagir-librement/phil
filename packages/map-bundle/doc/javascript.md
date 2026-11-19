# JavaScript

The bundle's Stimulus controller, `phil--map-bundle--map`, sits on the map's element beside Symfony UX Map's Leaflet
controller, which `phil_map()` names after it. It connects first and listens to the events Symfony UX Map dispatches on
the element: it decorates the map, it does not make one.

| Event | What the controller does |
|---|---|
| `ux:map:pre-connect` | For a floor plan, gives Leaflet the flat reference system and keeps the map within the plan. For a PMTiles archive, takes the tile layer away and keeps it as the fallback. |
| `ux:map:rectangle:before-create` | Gives a booth the class of its state, and an area of the neighbourhood the class `phil-map-area`. |
| `ux:map:connect` | Draws the plan — its image as an overlay, or its tiles — under the booths and fits the map to it; reads the archive with `pmtiles` or `protomaps-leaflet`, or adds the fallback tiles back if the package fails to load. |

It reads what the server describes under the map's `extra.phil` — `plan` and `pmtiles` —, checks it, and ignores what
is not whole. It adds the class `hasScript` to the element as it connects: without JavaScript, the element stays hidden
and the list under it says what the map shows. It sets `data-plan-drawn` (`image` or `tiles`) and `data-tiles`
(`pmtiles` or `fallback`) when it is done.

## The stylesheet

`phil-map/map.css` sizes the map, hides it without JavaScript, styles the list's title, and colours the areas and the
booths with the tokens of PHIL UX's stylesheet — `--phil-color-success-*` for free booths, `--phil-color-danger-*` for
taken ones, `--phil-color-warning-*` for closed ones, `--phil-color-border` dashed for unknown ones —, with plain colours
when it is not loaded.

## Modules

| Module | Role |
|---|---|
| `assets/src/map_controller.js` | The controller. |
| `assets/src/lib/plan.js` | What it decides without Leaflet: the class of a shape, the plan and the archive read from `extra.phil`. |
