<!-- phil-dev source-sha256=cd17635557b584f6b477a3e29ddf791f1ed41af65e4d8766465ac7288d22d1d1 -->
# Nouvelles analyses

Un fichier accepté et signalé pendant une panne de l'antivirus est analysé de nouveau une fois celui-ci rétabli. Tout
fichier peut aussi être analysé de nouveau après une mise à jour des signatures. Le bundle le fait sur un *worker* de
Messenger, avec un message qui ne transporte que des identifiants.

## Le message

```php
use Phil\UploadScanBundle\Application\Message\RescanFile;

$bus->dispatch(new RescanFile('manuscripts', $manuscript->id()));
```

`RescanFile` contient le nom d'un `RescanTarget` et l'identifiant du fichier, chacun de 1 à 255 octets : jamais le
fichier, jamais rien sur la personne qui l'a envoyé. Le *worker* lit lui-même le fichier, par la cible : le message est
un *claim check*, et le fichier reste là où l'application le conserve. Routez le message vers un transport asynchrone :

```yaml
# config/packages/phil_upload_scan.yaml
phil_upload_scan:
    rescan:
        transport: async
```

La même route peut plutôt être donnée dans la configuration de Messenger, comme pour tout message.

## Analyser après le téléversement

Un gros fichier, ou un site qui doit répondre vite, peut conserver le téléversement d'abord et l'analyser sur le
*worker* : l'application range le fichier hors de vue, marque sa fiche en attente et répartit `RescanFile` pour lui.
Tant que `resolve()` ne l'a pas blanchi, un fichier en attente n'est servi à personne d'autre que la personne qui l'a
envoyé — ou à personne —, exactement comme un fichier signalé.

## La cible

L'application indique au bundle où sont ses fichiers, et ce que signifie une nouvelle inspection, avec un
`RescanTarget` :

```php
use Phil\UploadScanBundle\Application\Contract\RescanTarget;
use Phil\UploadScanBundle\Application\Inspection;

final readonly class ManuscriptFiles implements RescanTarget
{
    public function name(): string
    {
        return 'manuscripts';
    }

    public function open(string $fileId): mixed
    {
        $manuscript = $this->manuscripts->find($fileId);

        return null === $manuscript ? null : $this->storage->readStream($manuscript->path());
    }

    public function resolve(string $fileId, Inspection $inspection): void
    {
        match (true) {
            $inspection->infected() => $this->manuscripts->takeDown($fileId),
            $inspection->flagged() => $this->bus->dispatch(new RescanFile('manuscripts', $fileId), [new DelayStamp(600_000)]),
            default => $this->manuscripts->clear($fileId),
        };
    }
}
```

Un service qui implémente `RescanTarget` reçoit l'étiquette `phil_upload_scan.rescan_target` par configuration
automatique. Le *handler*, `RescanFileHandler`, est enregistré quand Messenger est installé :

1. il trouve la cible par son nom ; un nom inconnu lève `UnknownRescanTarget` ;
2. il ouvre le fichier ; `null`, un fichier qui n'existe plus, termine le message ;
3. il inspecte le fichier sous le contexte `rescan.<target>`, si bien qu'un fichier infecté est mis en quarantaine et
   `FileInfected` réparti, comme pour un téléversement ;
4. il ferme le flux et remet l'inspection à `resolve()` ;
5. il répartit `FileRescanned`, avec le nom de la cible, l'identifiant du fichier et l'inspection.

Un échec de `resolve()` arrête le message avant `FileRescanned` : Messenger le retente comme il retente tout message.

## `FileRescanned`

Un paquet qui conserve des fichiers pour l'application écoute `FileRescanned` pour suivre les nouvelles analyses de sa
cible sans implémenter ce que la cible décide :

| Membre | Valeur |
|---|---|
| `target` | Le nom de la cible. |
| `fileId` | L'identifiant du fichier. |
| `inspection` | La nouvelle `Inspection`. |
| `cleared()` | Si le fichier a été analysé et jugé sain : un signalement posé pendant la panne de l'analyseur peut être levé. |

```php
use Phil\UploadScanBundle\Application\Event\FileRescanned;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class PublishClearedCovers
{
    public function __invoke(FileRescanned $event): void
    {
        if ('covers' === $event->target && $event->cleared()) {
            $this->covers->publish($event->fileId);
        }
    }
}
```

Le *handler* ne garde rien entre deux messages : un *worker* peut donc en traiter autant qu'il en faut.
