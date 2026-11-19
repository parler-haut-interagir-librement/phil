<!-- phil-dev source-sha256=69ec593bf0514b3d2653a821f0c2c6c64b2eef581962b305a69a1475add01fe8 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\ErrorTracking\ErrorEvent` | `final readonly class` | Une erreur : `title()`, `culprit()`, `with()`, `toArray()`, `fromArray()` |
| `Phil\ErrorTracking\ExceptionRecord` | `final readonly class` | Une exception d'une chaîne : type, message, cadres, `culprit()` |
| `Phil\ErrorTracking\Frame` | `final readonly class` | Un cadre : fichier, ligne, fonction, appartenance à l'application, `label()` |
| `Phil\ErrorTracking\RequestSummary` | `final readonly class` | La requête réduite, les en-têtes de transfert de `CLIENT_ADDRESS_HEADERS` retirés : `anonymized()` |
| `Phil\ErrorTracking\CaptureContext` | `final readonly class` | Source, gravité, requête et étiquettes d'une capture |
| `Phil\ErrorTracking\Source` | enum | `http`, `messenger`, `console`, `log`, `browser`, `manual` |
| `Phil\ErrorTracking\Severity` | enum | `warning`, `error`, `fatal` |
| `Phil\ErrorTracking\EventFactory` | `final readonly class` | Les événements d'une application : `fromThrowable()`, `fromMessage()`, `frame()` |
| `Phil\ErrorTracking\MessageNormalizer` | `final readonly class` | Un message sans ses valeurs variables : `normalize()` |
| `Phil\ErrorTracking\Fingerprinter` | `final readonly class` | L'empreinte d'un événement : `fingerprint()` |
| `Phil\ErrorTracking\Scrubber` | `final readonly class` | Les renseignements personnels et les secrets filtrés, les jetons des préfixes de chemin déclarés aussi : `scrub()`, `text()`, `url()`, `isSensitive()` |
| `Phil\ErrorTracking\Sampler` | `final readonly class` | Une part des événements : `keep()` |
| `Phil\ErrorTracking\GroupStatus` | enum | `open`, `resolved`, `ignored`, `regressed` ; `isUnresolved()` |
| `Phil\ErrorTracking\Occurrence` | enum | `first`, `regression`, `repeat` ; `alerts()` |
| `Phil\ErrorTracking\ErrorGroup` | `final readonly class` | Un groupe : `first()`, `seen()`, `resolve()`, `ignore()`, `reopen()` |
| `Phil\ErrorTracking\ErrorTracker` | `final readonly class` | La capture : `captureThrowable()`, `capture()` |
| `Phil\ErrorTracking\Capture` | `final readonly class` | Ce qu'est devenu un événement gardé |
| `Phil\ErrorTracking\Contract\ErrorStore` | interface | L'endroit où sont gardés les groupes et les événements : `find()`, `save()` |
| `Phil\ErrorTracking\Contract\CaptureThrottle` | interface | Les événements d'une clé, bornés : `allow()` |
| `Phil\ErrorTracking\Contract\GroupAlerter` | interface | Un premier événement ou une régression, signalé : `alert()` |
| `Phil\ErrorTracking\Exception\ErrorTrackingException` | interface | Chaque exception de la bibliothèque |
| `Phil\ErrorTracking\Exception\InvalidEvent` | `final class` | Un événement construit ou lu avec une valeur qu'il ne peut pas contenir |
| `Phil\ErrorTracking\Exception\InvalidOption` | `final class` | Un taux d'échantillonnage ou une borne hors limites, un préfixe de chemin à jeton mal formé |
