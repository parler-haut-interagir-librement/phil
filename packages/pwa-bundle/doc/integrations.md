# Integrations

What a bundle built on this one relies on: `phil/digital-signage`, whose screens show a timetable and announcements,
`phil/schedule-app`, the programme of an event installed with its personal timetable, or a kiosk of the application's
own.

## Precached paths

A service implementing `Phil\PwaBundle\Application\Contract\PrecacheProvider` is tagged `phil_pwa.precache_provider`
by autoconfiguration, and its paths join the precache list:

```php
final readonly class ScreenPages implements PrecacheProvider
{
    public function urls(): iterable
    {
        yield '/screens/hall';
        yield '/screens/hall/data.json';
    }
}
```

The paths are checked like the configuration's. They are part of the version: a provider answers the same paths on
every call while the deployment does not change, or the worker reinstalls on every visit. A list of more than 200
paths is refused.

## Data that changes

A cache rule decides how a path is answered. Data a screen polls — a timetable, announcements — is `network-first`, so
the screen shows the last answer it had while the network is down:

```yaml
phil_pwa:
    service_worker:
        cache_rules:
            - { prefix: /assets/, strategy: cache-first }
            - { prefix: /screens/, strategy: network-first }
            - { prefix: /schedule, strategy: network-first }
```

## A screen, for phil/digital-signage

`display: fullscreen` and an `orientation` make the installed site a screen without the browser's interface; the
`fullscreen` action does it for a page in a browser. A page shown on a second screen with the presentation controller
receives its messages as events, so the screen changes without a server round trip
([JavaScript](javascript.md)).

## A programme in the pocket, for phil/schedule-app

`start_url` and `shortcuts` open the programme and its parts; the programme's pages and data in the precache list or
under a `network-first` rule are read offline; the personal timetable of a visitor stays in the browser, never in the
worker's caches, which hold no page marked `private`.
