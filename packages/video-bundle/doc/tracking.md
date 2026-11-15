# Viewing progress

## What is counted

A viewing is a hundred parts of a video, each watched or not. The player marks a part when the video plays in it, and
sends the hundred marks — `{"video": "talk-12", "parts": "0111…"}` — with `navigator.sendBeacon()` when the page is
hidden or the video ends. Nothing says who watched.

It sends them only when `PlayerComponent::isTracking()` was true as the page was drawn: a hosted video, tracking on, an
endpoint, and the consent of the viewer, which the application's `ViewingConsent` gives. Otherwise the player sends
nothing, and a linked video never sends anything: its player is the platform's.

## What the server does

`ProgressController`, the route `POST /_phil/video/progress`, accepts a body of a kilobyte at most from a page of the
site — `Sec-Fetch-Site: same-origin`, or an `Origin` of the site where the browser sends no `Sec-Fetch-Site` — and reads
it as a `ViewingProgress`. It answers `204` whether the viewing is counted or not, so that the answer says nothing of the
viewer's consent; `400` for a body that is no viewing, `403` for another origin, `413` for a body too large; never
cached.

`ViewingTracker::record()` counts a viewing only when tracking is on and the viewer agreed — the consent is asked again
on the server —: it adds the parts to the heat map of the video and dispatches `VideoProgressed`.

## The heat map

`ViewingStore::heatmap($videoId)` gives a `Heatmap`: the number of viewings counted and, for each part, how many watched
it. `percentages()` gives the share of the viewings that watched each part. The heat map is the only thing kept: no
viewing can be told from another.

| Store | Keeps |
|---|---|
| `memory` | The heat maps of one request, emptied between requests: tests and development |
| `cache` | One item per video in a PSR-6 pool, never expiring; two viewings saved at the same instant may count as one |
| A service | Any `ViewingStore`: an atomic increment in a database |

## The share watched

`VideoProgressed` carries the `ViewingProgress`: `percent()` is the share of the video watched, `isComplete()` whether
every part was. A course listens to it to mark a lesson seen; the viewer, when it needs one, is the security token of
the request, which the listener reads itself.

```php
#[AsEventListener]
final readonly class LessonSeen
{
    public function __invoke(VideoProgressed $event): void
    {
        if ($event->progress->percent() >= 90) {
            // mark the lesson of $event->progress->videoId seen for the signed-in member
        }
    }
}
```
