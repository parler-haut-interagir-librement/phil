# Health checks

## The checks of the bundle

| Check | Up | Degraded | Down |
|---|---|---|---|
| `DatabaseCheck` | The connection answers the platform's `SELECT 1`. | | The connection or the query throws. |
| `CachePoolCheck` | A probe is saved, read back and deleted. | | The pool refuses to save, loses the value, or throws. |
| `MessengerTransportCheck` | The transport holds its threshold of messages or fewer, or cannot count them. | It holds more than its threshold. | The count throws. |
| `FilesystemCheck` | The directory exists and is writable, with its free space when a threshold is set. | Its disk has less free space than the threshold. | The directory is missing or not writable. |
| `FlysystemCheck` | A probe file of its own, `.phil-monitoring-probe-` and 16 random hexadecimal digits, is written, read back and deleted at the root of the storage, deleted even when it cannot be read back, so that checks run at the same time never disturb one another. | | The storage loses the file, or throws. |
| `HeartbeatsCheck` | No declared task is late. | A task is late; the summary names it. | |

A check that throws counts as down: its exception is logged at the error level, with the name of the check, and the
summary names only the class of the exception — its message could carry a host, a path or a credential. A check that
loses no data but slows the site is degraded, not down: readiness answers `503` only when a check is down, since a
monitor then takes the site out of service.

The checks run one after the other, in the order they are registered — the bundle's, in the order of the
configuration, then the application's —, on every readiness probe and every call to the detailed view. Each is timed on
the application's clock. A check cannot be interrupted once started: keep each one fast, and give the clients it uses
their own timeouts, such as the connection timeout of a database or a Redis client.

## The endpoints of the monitors

| Route | Path under the prefix | Answer |
|---|---|---|
| `phil_monitoring_live` | `GET /live` | `200 {"status":"up"}` while the application answers; no check runs. |
| `phil_monitoring_ready` | `GET /ready` | `{"status":"up"}`, `"degraded"` or `"down"`; `503` when down, `200` otherwise. |
| `phil_monitoring_details` | `GET /details` | Every check, task and the maintenance; for the bearer of the token only. |

Liveness tells an orchestrator whether to restart the process; readiness tells a load balancer or an uptime monitor
whether to send traffic. Every answer is JSON with `Cache-Control: no-store` and `X-Robots-Tag: noindex`, so that no
proxy serves a stale status.

## The detailed view

```sh
curl -H 'Authorization: Bearer <token>' https://example.com/_health/details
```

```json
{
    "status": "degraded",
    "checked_at": "2026-11-08T10:00:00+00:00",
    "checks": [
        {"name": "database.default", "status": "up", "summary": "Answers.", "duration_ms": 2},
        {"name": "messenger.failed", "status": "degraded", "summary": "3 messages waiting, over 0.", "duration_ms": 1}
    ],
    "heartbeats": [
        {"task": "backup", "state": "on_time", "last_beat_at": "2026-11-08T03:00:00+00:00", "due_by": "2026-11-09T04:00:00+00:00"}
    ],
    "maintenance": null
}
```

The token is `health.token`, compared in constant time with the whole `Authorization` header. Without a token
configured, the view answers `404`; with the wrong one, `401` with `WWW-Authenticate: Bearer`. The token travels in a
header only, never in the address, where logs and proxies would keep it. Keep it in an environment variable or a
secret, and give each monitor that needs the details the same token.

The status code of the view follows readiness: `503` while a check is down.

## A check from the command line

```sh
bin/console phil:monitoring:health
```

prints one line per check and the overall status, and fails while a check is down.
