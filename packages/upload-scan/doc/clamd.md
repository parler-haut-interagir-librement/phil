# clamd

`ClamdScanner` scans through clamd, the daemon of ClamAV, over the protocol clamd documents. The library speaks it
itself: no client package, no `clamdscan` process.

## The protocol

Each command is one connection, in the null-terminated form (`z` prefix):

| Command | Sent | Answered |
|---|---|---|
| `INSTREAM` | `zINSTREAM\0`, then each chunk preceded by its length in four bytes in network order, then four zero bytes | `stream: OK\0`, `stream: <signature> FOUND\0`, or `<message> ERROR\0` |
| `PING` | `zPING\0` | `PONG\0` |
| `VERSION` | `zVERSION\0` | `ClamAV <version>/<signatures>/<date>\0` |

The file travels inside the connection, so clamd needs no access to the application's disk and can run in its own
container or host.

`ClamdResponse` reads the answers. Whatever the bytes, it answers a result: an answer ending in `FOUND` is an
infection even when its signature is unreadable (the signature is then cut to its printable ASCII, or `Unnamed`); an
answer ending in `ERROR` is an error result with clamd's message; anything else is an error result naming what clamd
answered.

`ClamdScanner` hands `ClamdResponse` only a whole answer: up to its null byte, at most 4 096 bytes. An answer cut
short is an error result, never read for what it holds, so that `stream: OK` without its null byte is not a clean file:

| What clamd did | Result |
|---|---|
| Closed the connection without a byte | `clamd closed the connection without an answer.` |
| Closed the connection before the null byte | `clamd closed the connection before the end of its answer.` |
| Sent 4 096 bytes without a null byte | `clamd answered more than 4096 bytes without ending its answer.` |
| Sent nothing more within the timeout | `clamd did not answer within N seconds.` |

`ping()` answers `false` and `version()` `null` for each of them.

## Addresses

| Address | Where |
|---|---|
| `unix:///run/clamav/clamd.ctl` | The Unix socket of clamd on the same host (`LocalSocket` in `clamd.conf`). |
| `tcp://clamav:3310` | The TCP port of clamd (`TCPSocket`), such as a `clamav` service of the same Compose project. |

`SocketConnector` refuses any other form when it is built. Expose the TCP port only on a private network: clamd has
no authentication.

## clamd on a local Unix socket

When clamd runs on the same host as the application, prefer its Unix socket to a TCP port: no port is open, and the
file system decides who may scan. In `clamd.conf`:

```
LocalSocket /run/clamav/clamd.ctl
LocalSocketGroup clamav
LocalSocketMode 660
FixStaleSocket yes
# No TCPSocket line: clamd listens on the Unix socket only.
StreamMaxLength 25M
```

- `LocalSocket` is the path given to the scanner: `ClamdScanner::at('unix:///run/clamav/clamd.ctl')`. Distributions
  use `/run/clamav/clamd.ctl` or `/var/run/clamav/clamd.sock`; read `LocalSocket` in the installed `clamd.conf`.
- `LocalSocketGroup` and `LocalSocketMode 660` let only the user clamd runs as and the members of that group connect.
  Add the user PHP runs as (such as `www-data`, or the user of PHP-FPM's pool) to that group, then restart PHP-FPM or
  the worker so that it takes the group: `usermod -aG clamav www-data`. Never make the socket world-writable (`666`):
  any local user could then make clamd read files or exhaust it.
- The directory of the socket must be searchable by that user too (`/run/clamav` is `755` or owned by the group).
- Under systemd, a `clamav-daemon.socket` unit may create the socket instead of clamd: set its `SocketGroup=` and
  `SocketMode=` the same way.
- In containers, share the socket's directory as a volume between the clamd container and the application container,
  and give both the same group id, since names are not shared across images.

A scan through a socket the application cannot open answers an error result naming the address, such as
`clamd cannot be reached at unix:///run/clamav/clamd.ctl (Permission denied).`, which the policy rejects or flags.
`ping()` answers `false`: a health check shows the missing permission before the first upload.

## The size limit

`maxBytes`, 25 MiB by default, is the largest file sent. The scanner counts what it reads; past the limit it stops
sending, closes the connection and answers an error result, which the policy rejects or flags. clamd has its own limit,
`StreamMaxLength` in `clamd.conf`, also 25 MiB by default: keep `maxBytes` at or below it, or clamd answers
`INSTREAM size limit exceeded. ERROR` itself, which reads as an error result too. Raise both together to scan larger
files.

## The timeouts

| Setting | Default | Bounds |
|---|---|---|
| `SocketConnector::$connectTimeoutSeconds` | 5 s | How long the connection may take. |
| `timeoutSeconds` of `ClamdScanner` | 30 s | How long each read or write on the connection may wait. |

A scan of a large archive can take clamd several seconds once the last chunk is sent: the timeout bounds the wait for
its answer. When it passes, the result is `clamd did not answer within N seconds.`.

The stream of the file has its own timeout, such as that of a socket or a remote stream. When it passes, or when the
stream gives nothing more without having ended, the scanner stops before the last chunk, so clamd never answers for the
start of a file alone, and the result is an error: `The file stopped coming: its stream timed out before its end.` or
`The file stopped before its end: its stream gave nothing more and did not end.`

## The chunks

`chunkBytes`, 64 KiB by default, is the size of each chunk read from the file and sent. Each one is written whole
before the next is read, so the memory a scan holds stays near one chunk, whatever the size of the file.

## The probe

`ping()` answers whether clamd answers `PONG`; `version()` answers what clamd states — the engine version, the version
of its signatures and their date — or `null` when it does not answer. A health check calls `version()`: old signatures
show there before they let a threat through.
