<!-- phil-dev source-sha256=3aca198cb76864b3f2262252d47c1474a8d1f3e497ce5d141f6a3b05849cb280 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\UploadScan\Contract\Scanner` | interface | Un antivirus : `scanStream()`, `scanPath()` |
| `Phil\UploadScan\Contract\ScannerProbe` | interface | Si le moteur répond : `ping()`, `version()` |
| `Phil\UploadScan\Contract\ClamdConnector` | interface | Une connexion à clamd : `connect()` |
| `Phil\UploadScan\ScanResult` | `final readonly class` | Sain, infecté ou une erreur : `clean()`, `infected()`, `error()`, `isClean()`, `isInfected()`, `isError()`, `MAX_TEXT_BYTES` |
| `Phil\UploadScan\ScanStatus` | enum | `clean`, `infected`, `error` |
| `Phil\UploadScan\ScanPolicy` | `final readonly class` | D'un résultat à une décision : `decide()` |
| `Phil\UploadScan\OnScanError` | enum | `reject`, `accept_and_flag` |
| `Phil\UploadScan\Decision` | enum | `accept`, `flag`, `reject` ; `keeps()` |
| `Phil\UploadScan\Clamd\ClamdScanner` | `final readonly class` | clamd selon son protocole : `at()`, `scanStream()`, `scanPath()`, `ping()`, `version()`, `DEFAULT_MAX_BYTES`, `DEFAULT_CHUNK_BYTES` |
| `Phil\UploadScan\Clamd\SocketConnector` | `final readonly class` | Un socket Unix ou TCP vers clamd |
| `Phil\UploadScan\Clamd\ClamdResponse` | `final class` | Le lecteur des réponses de clamd : `scan()`, `isPong()`, `version()`, `MAX_BYTES` |
| `Phil\UploadScan\NullScanner` | `final readonly class` | Chaque fichier sain |
| `Phil\UploadScan\FakeScanner` | `final readonly class` | La chaîne EICAR et les aiguilles données, infectées : `eicar()`, `EICAR_SIGNATURE` |
| `Phil\UploadScan\Exception\UploadScanException` | interface | Chaque exception de la bibliothèque |
| `Phil\UploadScan\Exception\InvalidArgument` | `final class` | Une valeur que la bibliothèque refuse |
| `Phil\UploadScan\Exception\ScannerUnavailable` | `final class` | clamd ne peut pas être joint, lancée par un connecteur |
