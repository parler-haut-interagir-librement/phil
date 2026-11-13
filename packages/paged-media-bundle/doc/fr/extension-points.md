<!-- phil-dev source-sha256=d335bc31647f1fa6bb9ab391bd4af2f808a05627cfe294d46a43de76786c9a43 -->
# Points d'extension

Ce qu'une application implémente, remplace, écoute ou configure. Les documents, les étapes et la signature sont ceux
de `phil/paged-media`.

## Ports

### `DocumentTemplate`

`Phil\PagedMediaBundle\Application\DocumentTemplate` dessine un document du moteur d'écriture natif à partir de
paramètres. Une classe qui l'implémente avec `#[AsDocumentTemplate('name')]` est étiquetée `phil_paged_media.template`
par la configuration automatique ; sans celle-ci, étiquetez-la à la main avec un `name`. Voyez
[Rendu](rendering.md#gabarits).

| Méthode | Contrat |
|---|---|
| `build(array $parameters): Document` | Le document pour ces paramètres — des scalaires et des tableaux, puisqu'une tâche en file d'attente les garde en JSON |

### `PrintEngine`

`Phil\PagedMediaBundle\Application\Contract\PrintEngine` est le moteur de médias paginés de l'application. Voyez
[Rendu](rendering.md#html-et-css). `phil/paged-media-weasyprint` l'implémente avec la commande `weasyprint`, et
l'application l'enregistre sous cette interface ; un autre moteur implémente la même méthode.

| Méthode | Contrat |
|---|---|
| `pdf(PrintableHtml $page): string` | Les octets du PDF que le moteur met en page à partir de la page |

### `JobStore`

`Phil\PagedMediaBundle\Application\Port\JobStore` est le *claim check* du travail en file d'attente : les messages
Messenger ne transportent que l'identifiant de la tâche. `DirectoryJobStore` écrit chaque tâche dans un fichier JSON
sous `jobs.directory`, et `CacheJobStore`, choisi par `jobs.cache_pool`, la garde dans un pool de cache PSR-6 ; une
application fait du port l'alias de son propre stockage — une table, un stockage Flysystem —, `JobCodec` lui donnant le
texte à garder. Voyez [Messenger](messenger.md#le-stockage-des-tâches).

| Méthode | Contrat |
|---|---|
| `keep(RenderJob\|PipelineJob\|ConversionJob $job): string` | Garde la tâche sous un identifiant opaque, lisible par l'application seule, puisqu'elle contient des documents et des paramètres |
| `find(string $id): RenderJob\|PipelineJob\|ConversionJob` | La redonne ; `JobUnavailable` quand elle a disparu |
| `remove(string $id): void` | L'oublie une fois son fichier gardé, ou quand son message a échoué définitivement |

### `PdfStorage`

`Phil\PagedMediaBundle\Application\Port\PdfStorage` est l'endroit où un PDF en file d'attente est gardé.
`DirectoryStorage` écrit sous `storage.directory` et refuse un chemin qui en sort ; une application fait du port
l'alias de son propre stockage, par exemple une médiathèque.

| Méthode | Contrat |
|---|---|
| `store(string $path, Output $output): void` | Garde le fichier sous le chemin de la tâche ; `StorageFailed` quand c'est impossible |

## Événements

| Événement | Quand | Ce que fait un écouteur |
|---|---|---|
| `Phil\PagedMediaBundle\Application\Event\PrintStarting` | Avant qu'une impression qui nomme son œuvre ne commence ou ne soit mise en file d'attente | Lit le `PrintSubject` ; lance une exception pour arrêter l'impression |
| `Phil\PagedMediaBundle\Application\Event\UnnamedPrintStarting` | Avant qu'une impression qui ne nomme aucune œuvre ne commence ou ne soit mise en file d'attente | Lance une exception pour arrêter l'impression |
| `Phil\PagedMediaBundle\Application\Event\PdfStored` | Une fois un PDF en file d'attente gardé | Lit l'identifiant de la tâche, le chemin, le nom du fichier, le type de média et les comptes de la remédiation : avertit, crée des liens, vérifie la conformité |

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `gotenberg` | Où se trouve Gotenberg, ses identifiants de connexion, son délai et son client HTTP |
| `signing.certificate` | D'où vient le certificat de signature : un fichier, en ligne ou un coffre |
| `signing.timestamp_url`, les options PAdES | L'autorité d'horodatage et ce que dit une signature |
| `storage.directory`, `jobs.directory`, `jobs.remove_failed` | Où les fichiers et les tâches sont gardés, et si les tâches en échec sont oubliées |

Voyez [Configuration](configuration.md). Un `CertificateSource` ou un `TimestampAuthority` propre à l'application,
enregistré sous l'interface, remplace celui qui est configuré.

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\PagedMediaBundle\Application\PagedMedia` | Produit le rendu, exécute les étapes et convertit tout de suite ; public |
| `Phil\PagedMediaBundle\Application\PagedMediaQueue` | La même chose, mise en file d'attente par Messenger ; public |
| `Phil\PagedMediaBundle\Infrastructure\Notes\PrintedNotes` | Un document UJO (Universal JSON Output) avec ses notes, pour le moteur d'impression ; public, avec `phil/footnotes-sidenotes` |
| `Phil\FootnotesSidenotes\NoteResolver` | La numérotation des notes ; redéfini par une application avec ses propres `NoteOptions` |
