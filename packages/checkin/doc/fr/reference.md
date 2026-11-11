<!-- phil-dev source-sha256=fe805cf26ae2c9c42792e4fbf5069c9f217e1e4beb363c6d65de823b9a6363bc -->
# Référence

## `Phil\Checkin`

| Type | Rôle |
|---|---|
| `CheckpointId` | L'endroit où un titre d'accès est scanné. |
| `DeviceId` | L'appareil sur lequel une lecture a été faite. |
| `ScanId` | L'identifiant d'une lecture, tiré par son appareil ; `generate()`. |
| `CredentialCode` | Ce qu'un appareil a lu ; `fingerprint()`, `estimatedBits()`. |
| `Credential` | Ce que représente un code : identifiant, libellé, points de contrôle, ouverture, fermeture, entrées, état ; `opens()`. |
| `CredentialStatus` | `Active` ou `Revoked`. |
| `CatalogEntry` | Un titre d'accès avec l'un de ses codes. |
| `Direction` | `In` ou `Out`. |
| `ScanOutcome` | Ce qu'une lecture a décidé ; `changesAttendance()`. |
| `AdmissionPolicy` | La fenêtre anticipée et l'anti-retour ; `decide()`. |
| `Attendance` | Entrées, à l'intérieur, dernière entrée et version ; `none()`, `restore()`, `after()`. |
| `CheckIn` | Une lecture d'un titre connu avec sa décision ; `decided()`, `restore()`. |
| `CheckInDesk` | L'enregistrement en ligne ; `scan()`. |
| `ScanResult` | Ce que répond le guichet ; `collided()` pour un identifiant de lecture inscrit pour une autre lecture. |
| `CredentialResolvers` | Plusieurs résolveurs interrogés dans leur ordre. |
| `InMemoryCheckInLedger` | Un registre pour les tests ; `all()`. |

## `Phil\Checkin\Contract`

| Type | Rôle |
|---|---|
| `CredentialResolver` | Trouve le titre d'accès d'un code. |
| `CheckInLedger` | Garde les enregistrements et les fréquentations. |
| `CredentialCatalog` | Liste les titres d'accès d'un point de contrôle, en trouve un par identifiant. |

## `Phil\Checkin\Offline`

| Type | Rôle |
|---|---|
| `ManifestIssuer` | Émet le manifeste d'un point de contrôle pour un appareil ; `issue()`. |
| `OfflineManifest` | Ce que télécharge un appareil ; `toArray()`. |
| `ManifestEntry` | Un titre d'accès d'un manifeste ; `toArray()`. |
| `OfflineScan` | Une lecture décidée hors ligne ; `of()`, `fromArray()`. |
| `OfflineBatch` | Lit un journal ; `read()`. |
| `OfflineSync` | Inscrit un journal ; `apply()`. |
| `SyncStatus` | `Recorded`, `Conflict`, `Duplicate`, `Collision` ou `Rejected`. |
| `SyncReport` | L'état de chaque lecture ; `count()`, `toArray()`. |

## `Phil\Checkin\Exception`

| Type | Rôle |
|---|---|
| `CheckinException` | Chaque exception de la bibliothèque. |
| `InvalidCheckin` | Une valeur dont la bibliothèque ne peut rien faire. |
| `InvalidOfflineBatch` | Un journal mal formé. |
| `LedgerContention` | Une lecture qui en rencontrait toujours d'autres. |
| `ManifestTooLarge` | Un catalogue au-delà de la limite du manifeste. |
| `WeakManifestCodes` | Un code trop faible pour un manifeste. |
