<!-- phil-dev source-sha256=082d09720bfec8276450b1203d7773acb71b049be45c67b71f86a1c491b397d9 -->
# Analyse

## Le port

```php
interface Scanner
{
    public function scanStream(mixed $stream): ScanResult;

    public function scanPath(string $path): ScanResult;
}
```

`scanStream()` lit un flux ouvert en entier et le laisse ouvert. Un flux où l'on peut se repositionner est d'abord
rembobiné : un flux lu avant l'analyse, pour en détecter le type, en calculer l'empreinte ou le mesurer, est analysé
depuis son début, jamais depuis l'endroit où cette lecture s'est arrêtée, ce qui n'enverrait à clamd rien ou seulement
une fin de fichier et obtiendrait une réponse saine pour un fichier infecté. Un flux qu'on ne peut pas rembobiner, comme
un tube ou un socket, est analysé tel qu'il arrive si rien n'en a été lu, et donne un résultat d'erreur une fois lu,
`The stream was read before the scan and cannot be rewound: give it to the scanner before reading it.`
`scanPath()` ouvre un fichier local et le ferme. Une valeur qui n'est pas un flux ouvert est refusée avec
`InvalidArgument` : c'est une erreur dans le code appelant. Tout le reste qui empêche l'analyse d'un fichier (le moteur injoignable ou trop lent, le
fichier illisible ou plus gros que la limite) est un résultat d'erreur, jamais une exception, pour que la politique
décide.

## Les résultats

| Résultat | `status` | `signature` | `reason` |
|---|---|---|---|
| `ScanResult::clean()` | `clean` | `null` | `null` |
| `ScanResult::infected('Win.Test.EICAR_HDB-1')` | `infected` | la signature qui a correspondu | `null` |
| `ScanResult::error('clamd did not answer within 30 seconds.')` | `error` | `null` | pourquoi le fichier n'a pas été analysé |

`isClean()`, `isInfected()` et `isError()` interrogent le statut. Une signature et une raison font de 1 à 255 octets
d'ASCII imprimable, sans espace autour : on peut donc les journaliser et les montrer telles quelles à un opérateur ;
l'adaptateur clamd réduit ce que clamd envoie à cette forme.

## La politique

`ScanPolicy` transforme un résultat en `Decision` :

| Résultat | `OnScanError::Reject` (par défaut) | `OnScanError::AcceptAndFlag` |
|---|---|---|
| sain | `Accept` | `Accept` |
| infecté | `Reject` | `Reject` |
| erreur | `Reject` | `Flag` |

`Decision::keeps()` dit si le fichier est gardé, signalé ou non. Rejeter ce qui n'a pas pu être analysé est la valeur
par défaut sûre : un attaquant capable de ralentir l'antivirus ne peut pas lui faire passer un fichier en douce.
Accepter et signaler garde les téléversements fonctionnels pendant que l'antivirus est arrêté ; l'application garde
alors un fichier signalé hors de vue (ni publié, ni partagé) jusqu'à ce qu'une analyse ultérieure le blanchisse.

```php
use Phil\UploadScan\Decision;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;

$decision = new ScanPolicy(OnScanError::AcceptAndFlag)->decide($scanner->scanPath($path));

match ($decision) {
    Decision::Accept => $library->publish($path),
    Decision::Flag => $library->holdForReview($path),
    Decision::Reject => $library->refuse($path),
};
```
