# Extension points

The library has three ports, interfaces of `Phil\UploadScan\Contract`.

## `Scanner`

Another antivirus — a cloud scanning API, another daemon — is a class implementing `Scanner`:

```php
use Phil\UploadScan\Contract\Scanner;
use Phil\UploadScan\ScanResult;

final readonly class CloudScanner implements Scanner
{
    public function __construct(
        private CloudClient $client,
    ) {
    }

    public function scanStream(mixed $stream): ScanResult
    {
        try {
            $verdict = $this->client->scan($stream);
        } catch (CloudUnavailable) {
            return ScanResult::error('The scanning service does not answer.');
        }

        return $verdict->threat === null ? ScanResult::clean() : ScanResult::infected($verdict->threat);
    }

    public function scanPath(string $path): ScanResult
    {
        $stream = fopen($path, 'rb');

        if (false === $stream) {
            return ScanResult::error('The file cannot be read.');
        }

        try {
            return $this->scanStream($stream);
        } finally {
            fclose($stream);
        }
    }
}
```

Keep to the contract: the whole stream scanned, rewound first when it is seekable, an error result for anything that
keeps the file from being scanned, an exception only for a value that is not a stream, and nothing kept between two
files.

## `ScannerProbe`

A scanner that can say whether its engine answers implements `ScannerProbe` too: `ping()` and `version()`, `null` when
the engine does not answer. `ClamdScanner` and `FakeScanner` implement it.

## `ClamdConnector`

How `ClamdScanner` reaches clamd: `connect()` answers a connected, blocking stream, or throws `ScannerUnavailable`.
`SocketConnector` covers Unix and TCP sockets; another connector can reach clamd through a TLS tunnel
(`stream_socket_client('tls://…')`), or pick one of several daemons:

```php
use Phil\UploadScan\Clamd\SocketConnector;
use Phil\UploadScan\Contract\ClamdConnector;
use Phil\UploadScan\Exception\ScannerUnavailable;

final readonly class FirstAnswering implements ClamdConnector
{
    /**
     * @param list<SocketConnector> $connectors
     */
    public function __construct(
        private array $connectors,
    ) {
    }

    public function connect(): mixed
    {
        foreach ($this->connectors as $connector) {
            try {
                return $connector->connect();
            } catch (ScannerUnavailable) {
            }
        }

        throw ScannerUnavailable::because('No clamd answers.');
    }
}
```

The scanner closes each stream once answered.
