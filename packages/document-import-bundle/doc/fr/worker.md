<!-- phil-dev source-sha256=38f78d62fde98a4d501c50660045ab9753423affc489c95b7e35503062432e12 -->
# Importations sur un worker

Une grande archive ou un grand PDF est lu en mémoire et ses images sont stockées une par une : importer un livre est une
tâche pour un *worker* de Messenger plutôt que pour une requête. Lorsque `async` est activé, `DocumentImportQueue` prend
le fichier dans la requête et le *worker* l'importe.

```yaml
# config/packages/phil_document_import.yaml
phil_document_import:
    async:
        transport: async                                  # ImportDocument routed to this transport
        directory: '%kernel.project_dir%/var/document-imports'
```

```php
use Phil\DocumentImport\Exception\DocumentImportException;
use Phil\DocumentImportBundle\Application\DocumentImportQueue;

#[Route('/manuscripts/import', methods: ['POST'])]
public function import(Request $request, DocumentImportQueue $queue): Response
{
    $file = $request->files->get('manuscript');

    try {
        $claim = $queue->submit((string) file_get_contents($file->getPathname()), filename: $file->getClientOriginalName());
    } catch (DocumentImportException $exception) {
        return $this->render('manuscripts/refused.html.twig', ['reason' => $exception->getMessage()], new Response(status: 422));
    }

    $manuscript->awaitImport($claim);

    return $this->redirectToRoute('manuscript_show', ['id' => $manuscript->id()]);
}
```

## Le chemin d'un fichier

1. `submit()` refuse sur-le-champ ce que `DocumentImports` refuserait sans lire le fichier : des octets plus grands que
   `limits.total_bytes`, un format qui n'est pas dans `formats.import`, un format qu'il ne peut pas détecter. Il lève
   `LimitExceeded` ou `UnsupportedFormat`.
2. Le fichier, son nom et son format vont à `PendingImports`, qui rend une référence.
3. `ImportDocument` est réparti avec la référence et rien d'autre : aucun octet du fichier, aucun nom de fichier, rien
   sur la personne qui l'a envoyé. Un transport, ses journaux et sa file des échecs ne voient qu'un identifiant.
4. Sur le *worker*, `ImportDocumentHandler` reprend le fichier par sa référence et l'importe avec `DocumentImports` :
   d'abord l'analyse préalable — l'antivirus, lorsque `phil/upload-scan-bundle` est enregistré —, puis le lecteur.
5. Un document importé répartit `DocumentImported` avec la référence dans `$claim`. Un fichier que la bibliothèque
   refuse — qui n'est pas ce que dit son format, plus grand que les limites une fois décodé, refusé par l'antivirus —
   répartit `DocumentImportFailed` avec la référence et l'exception dans `$reason`. Dans les deux cas, la référence est
   libérée.
6. Toute autre erreur — le magasin de médias est injoignable, un écouteur échoue — laisse la référence en place, et
   Messenger essaie de nouveau le message.

Une référence déjà libérée, par un message traité deux fois, est laissée telle quelle.

```php
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class ManuscriptImports
{
    #[AsEventListener]
    public function imported(DocumentImported $event): void
    {
        if (null === $event->claim) {
            return; // an import of the request, not of the worker
        }

        $manuscript = $this->manuscripts->awaitingImport($event->claim);
        $manuscript->replaceText(new Ujo()->encode($event->imported->document), $event->imported->report->toArray());
    }

    #[AsEventListener]
    public function failed(DocumentImportFailed $event): void
    {
        $this->manuscripts->awaitingImport($event->claim)->importRefused($event->reason->getMessage());
    }
}
```

## Où attendent les fichiers

Le magasin par défaut, `FilesystemPendingImports`, écrit chaque fichier dans `async.directory` : ses octets dans
`<claim>.document`, son nom et son format dans `<claim>.json`, lisibles seulement par leur propriétaire, dans un
répertoire qu'il crée avec les mêmes permissions. Une référence est 32 chiffres hexadécimaux aléatoires ; toute autre
référence ne désigne aucun fichier, si bien qu'une référence lue dans un message n'atteint jamais l'extérieur du
répertoire. Choisissez un répertoire que le serveur web ne sert pas et que le *worker* peut lire : sur plusieurs machines,
un volume partagé, ou un magasin propre à l'application.

Un magasin propre à l'application implémente `Phil\DocumentImportBundle\Application\Contract\PendingImports` — `keep()`,
`take()`, `release()` — et est nommé dans `async.store` : un stockage d'objets, une table de base de données. Ses
références sont de 1 à 255 lettres, chiffres, `.`, `_` ou `-`, la forme qu'accepte `ImportDocument`.

Un fichier dont le message est perdu reste dans le magasin : une tâche planifiée de l'application supprime les fichiers
plus vieux que la plus longue importation qu'elle attend.

## Acheminement

`async.transport` achemine `ImportDocument` vers un transport de Messenger, avant l'acheminement propre à l'application,
qui l'emporte lorsqu'il nomme aussi le message. Sans lui, l'application achemine le message, ou Messenger le traite aussitôt
dans la requête.
