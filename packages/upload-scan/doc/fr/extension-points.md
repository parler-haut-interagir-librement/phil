<!-- phil-dev source-sha256=b6140e0f7d571881edab9baf3ef1d18bc375ce547a004a6601f55aecf08bd8aa -->
# Points d'extension

La bibliothèque a trois ports, des interfaces de `Phil\UploadScan\Contract`.

## `Scanner`

Un autre antivirus (une API d'analyse infonuagique, un autre démon) est une classe qui implémente `Scanner` :

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

Respectez le contrat : le flux analysé en entier, rembobiné d'abord si l'on peut s'y repositionner, un résultat
d'erreur pour tout ce qui empêche l'analyse du fichier, une exception seulement pour une valeur qui n'est pas un flux,
et rien de gardé entre deux fichiers.

## `ScannerProbe`

Un analyseur qui peut dire si son moteur répond implémente aussi `ScannerProbe` : `ping()` et `version()`, `null` quand
le moteur ne répond pas. `ClamdScanner` et `FakeScanner` l'implémentent.

## `ClamdConnector`

La façon dont `ClamdScanner` joint clamd : `connect()` répond par un flux connecté et bloquant, ou lance
`ScannerUnavailable`. `SocketConnector` couvre les sockets Unix et TCP ; un autre connecteur peut joindre clamd par un
tunnel TLS (`stream_socket_client('tls://…')`), ou choisir parmi plusieurs démons :

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

L'analyseur ferme chaque flux une fois la réponse reçue.
