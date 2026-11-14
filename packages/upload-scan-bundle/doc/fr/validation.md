<!-- phil-dev source-sha256=5e6a4641262c87a8a948445e9d54b127e59d740164c4b9a128bf7ced022ce4c5 -->
# Validation

## `#[CleanFile]`

```php
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class CoverUpload
{
    #[Assert\NotNull]
    #[Assert\Image(maxSize: '10M')]
    #[CleanFile(context: 'cover')]
    public ?UploadedFile $image = null;
}
```

La contrainte accepte un `UploadedFile`, un `File` ou un chemin local. Le fichier est analysé une seule fois, par le
`UploadScanner`. Ainsi, quand la contrainte refuse un fichier infecté, celui-ci est mis en quarantaine et
`FileInfected` est réparti.

| Résultat | Violation | Code |
|---|---|---|
| Sain | aucune | |
| Infecté | `infectedMessage` : « This file contains a threat and was refused. » | `CleanFile::INFECTED_ERROR` |
| Non analysé, `on_error: reject` | `notScannedMessage` : « This file could not be checked for threats. Please try again later. » | `CleanFile::NOT_SCANNED_ERROR` |
| Non analysé, `on_error: accept_and_flag` | aucune ; `FileFlagged` est réparti | |

`null` et `''` passent, comme pour toute contrainte sauf `NotNull`. Un téléversement arrivé incomplet passe aussi : la
contrainte `File` le signale, et un fichier partiel n'est jamais analysé. Toute autre valeur est une violation de type.

| Option | Valeur par défaut | Signification |
|---|---|---|
| `context` | `upload` | La provenance du fichier, transmise aux événements : `manuscript`, `cover`, `attachment`. |
| `infectedMessage` | ci-dessus | Le message d'un fichier infecté. |
| `notScannedMessage` | ci-dessus | Le message d'un fichier qui n'a pas pu être analysé. |
| `groups`, `payload` | | Comme pour toute contrainte. |

Les messages ne nomment jamais la signature : la personne qui a envoyé le fichier apprend qu'il a été refusé, pas ce
que l'antivirus a trouvé. La signature parvient à l'opérateur par l'événement et par la fiche de la quarantaine.

Placez `#[CleanFile]` après les contraintes qui vérifient le type et la taille. Ainsi, un fichier refusé pour ces
raisons n'est pas envoyé à l'antivirus : Symfony valide les contraintes d'une propriété dans leur ordre, et une
`GroupSequence` s'arrête au premier groupe qui a une violation.

## Le `UploadScanner`

Hors d'un objet validé, injectez `Phil\UploadScanBundle\Application\UploadScanner` :

| Méthode | Analyse |
|---|---|
| `inspectPath($path, $name, $context)` | Un fichier local. Un chemin qui n'est pas un fichier lisible donne un résultat d'erreur. |
| `inspectContents($contents, $name, $context)` | Une chaîne, par un flux temporaire. |
| `inspectStream($stream, $name, $context)` | Un flux entier, rembobiné d'abord s'il a déjà été lu ; un fichier infecté n'est mis en quarantaine que depuis un flux où l'on peut se repositionner. |

Chacune répond une `Inspection` :

| Membre | Signification |
|---|---|
| `result` | Le `ScanResult` : sain, infecté avec sa signature, ou une erreur avec sa raison. |
| `decision` | La `Decision` de la politique : `Accept`, `Flag` ou `Reject`. |
| `quarantineId` | L'endroit où le fichier infecté a été conservé, ou `null`. |
| `accepted()` | Si le fichier est conservé, signalé ou non. |
| `flagged()` | S'il est conservé sans avoir été analysé. |
| `infected()` | Si l'antivirus a trouvé une menace. |

`$name` est le nom que l'expéditeur a donné au fichier : il parvient aux événements et à la fiche de la quarantaine,
jamais à un chemin.
