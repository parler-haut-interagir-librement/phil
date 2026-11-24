# Configuration

The bundle's configuration lives under `phil_survey`; every option has a default:

```yaml
# config/packages/phil_survey.yaml
phil_survey:
    connection: default
    secret: '%kernel.secret%'
    redirect_origins: ['https://example.org']
    allow_localhost: false
    respondent: null
    sessions: null
    pages: true
    schedule:
        enabled: true
        at: '03:45'
        timezone: UTC
        lock_factory: lock.factory
        state_pool: cache.app
```

| Option | Default | What it does |
|---|---|---|
| `connection` | `default` | The Doctrine connection of the tables. |
| `secret` | `%kernel.secret%` | The key of the digests that mark participations, 16 bytes or more. |
| `redirect_origins` | none | The origins an ending may redirect to; with none, no ending redirects. |
| `allow_localhost` | `false` | Whether `localhost` is a host of a redirect, for development and tests. |
| `respondent` | `null` | The service giving the signed-in member, a `CurrentSubject` of `phil/forms-bundle`; the member of Symfony Security when null, nobody without it. |
| `sessions` | `null` | The `FeedbackSessions` service giving the start and the end of each session; no session feedback is open when null. |
| `pages` | `true` | Whether the bundle registers its pages, with Twig. |
| `schedule.enabled` | `true` | Whether the schedule `phil_survey` purges the responses past their retention every day, with Symfony Scheduler. |
| `schedule.at`, `schedule.timezone` | `03:45`, `UTC` | The time of the purge. |
| `schedule.lock_factory` | `lock.factory` | The lock factory that keeps the schedule to one worker, when it exists. |
| `schedule.state_pool` | `cache.app` | The cache pool that keeps the last run of the schedule, when it exists. |

The pages need Symfony's CSRF protection, `framework.csrf_protection: true`; its token is stateless and needs no
session. The [pages](pages.md) say how to import the routes.

`phil_survey.respondent` and `phil_survey.sessions` name services of the application: see the
[extension points](extension-points.md).

The pages need Symfony's CSRF protection, `framework.csrf_protection: true`; its token is stateless and needs no
session. The [pages](pages.md) say how to import the routes.

`phil_survey.respondent` and `phil_survey.sessions` name services of the application: see the
[extension points](extension-points.md).
