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
answered. It reads at most 4 096 bytes of an answer, up to its first null byte.

## Addresses

| Address | Where |
|---|---|
| `unix:///run/clamav/clamd.ctl` | The Unix socket of clamd on the same host (`LocalSocket` in `clamd.conf`). |
| `tcp://clamav:3310` | The TCP port of clamd (`TCPSocket`), such as a `clamav` service of the same Compose project. |

`SocketConnector` refuses any other form when it is built. Expose the TCP port only on a private network: clamd has
no authentication.

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

## The chunks

`chunkBytes`, 64 KiB by default, is the size of each chunk read from the file and sent. Each one is written whole
before the next is read, so the memory a scan holds stays near one chunk, whatever the size of the file.

## The probe

`ping()` answers whether clamd answers `PONG`; `version()` answers what clamd states — the engine version, the version
of its signatures and their date — or `null` when it does not answer. A health check calls `version()`: old signatures
show there before they let a threat through.
