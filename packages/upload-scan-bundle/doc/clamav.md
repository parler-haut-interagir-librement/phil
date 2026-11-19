# ClamAV

The bundle needs a clamd it can reach. In development and in production, the simplest is a `clamav` service beside the
application, from the official image.

## Docker Compose

```yaml
# compose.yaml
services:
    clamav:
        image: clamav/clamav:stable
        restart: unless-stopped
        volumes:
            - clamav-signatures:/var/lib/clamav   # keeps the signatures between restarts
        healthcheck:
            test: ["CMD", "clamdcheck.sh"]
            interval: 60s
            start_period: 120s

    php:
        # …
        depends_on:
            clamav:
                condition: service_healthy

volumes:
    clamav-signatures:
```

```yaml
# config/packages/phil_upload_scan.yaml
phil_upload_scan:
    clamd:
        address: tcp://clamav:3310
```

- The port 3310 stays on the Compose network: clamd has no authentication, never publish it.
- The image updates its signatures with `freshclam` on its own. The first start downloads them and takes a minute or
  two; the health check waits for clamd to answer.
- clamd holds its signatures in memory: plan about 1.5 GiB of memory for the service, more while it reloads them.
- clamd refuses a stream larger than `StreamMaxLength`, 25 MiB by default. To scan larger files, raise it in the
  service's `clamd.conf` and `phil_upload_scan.max_bytes` together.

## On the same host

With the `clamav-daemon` package of the distribution, clamd listens on its Unix socket:

```yaml
phil_upload_scan:
    clamd:
        address: unix:///run/clamav/clamd.ctl
```

The user PHP runs as must be allowed to open the socket: add it to the `clamav` group, or set `LocalSocketGroup` in
`clamd.conf`, with `LocalSocketMode 660`, and restart PHP-FPM or the worker so that it takes the group. Never make the
socket world-writable. The whole configuration — `clamd.conf`, the directory of the socket, systemd's socket unit and a
socket shared between containers — is in the documentation of `phil/upload-scan`, under "clamd on a local Unix
socket".

## Checking it answers

With `phil/monitoring-bundle`, the health check `upload_scan` shows the version of clamd and of its signatures. Without
it, `printf 'zVERSION\0' | nc clamav 3310` from the PHP container answers the same.
