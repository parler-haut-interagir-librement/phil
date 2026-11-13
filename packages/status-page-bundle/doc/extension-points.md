# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Give the states of the components from elsewhere | Implement `Application\Contract\ComponentStatuses` and name the service in `source_service`. |
| Keep incidents and maintenances elsewhere — a table, a back office | Implement `Application\Contract\StatusRecords` and name the service in `records.service`. |
| Publish the snapshot to a hosting service | Implement `Application\Contract\SnapshotPublisher` and declare a target `{ type: service, service: <id> }`. |
| Draw the page differently | Replace `Application\Contract\PageRenderer`, or override the templates `@PhilStatusPage/page.html.twig` and its partials in `templates/bundles/PhilStatusPageBundle/`. |
| Put the page in the site's layout | Set `layout` to a template with the blocks `title` and `content`. |
| Push from the application | Call `Application\SnapshotPush::push()` (public), or dispatch `Application\Message\PushStatusSnapshot`. |
| Build the page for another use | Call `Application\StatusPageBuilder::build()` (public), then `JsonDocument` or `AtomFeed`. |
| Add a language | Add `translations/phil_status_page.<locale>.php` with the keys of the English file. |

A `ComponentStatuses` returns the state of the components it knows, by id: those it leaves out are operational. A
`StatusRecords` returns every record it holds; the page sorts and filters them by the clock. A `SnapshotPublisher`
writes every file of the snapshot or throws: `SnapshotPush` reports and logs the failure, and goes on with the other
targets. None of them keeps state between two requests.
