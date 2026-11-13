<!-- phil-dev source-sha256=114ded1a96339a2585747ac16d6b71fc3c6b5b615c3f4321d7a20815be790786 -->
# Messenger

`PagedMediaQueue` fait dans un *worker* ce que `PagedMedia` fait tout de suite, puis garde le fichier et l'annonce.
Chaque appel garde une tâche dans le stockage des tâches et répartit un message qui ne transporte que l'identifiant de
la tâche. Les documents, les paramètres du gabarit, les options et les étapes ne passent jamais par la file d'attente :
un message est donc court et ne contient aucun renseignement personnel.

| Appel | Tâche gardée | Message réparti |
|---|---|---|
| `render($template, $parameters, $path, $pipeline, $sign, $timestamp, $filename)` | `RenderJob` | `RenderPdf($job)` |
| `run($files, $pipeline, $path, $sign, $timestamp)` | `PipelineJob` | `RunPipeline($job)` |
| `convert($documents, $path, $options, $pipeline, $sign, $timestamp)` | `ConversionJob` | `ConvertDocuments($job)` |

Chaque appel retourne l'identifiant de la tâche, que l'événement `PdfStored` redonne. L'impression est annoncée, par
`PrintStarting` ou `UnnamedPrintStarting`, au moment de sa mise en file d'attente ; le worker la produit avec
`PagedMedia::alreadyAnnounced()`, qui n'annonce rien de plus.

```php
$job = $queue->render('receipt', ['order' => 'R-42'], 'receipts/R-42.pdf', new Pipeline(new Remediate(Profile::PdfA3b)), sign: true);
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\PagedMediaBundle\Application\Message\RenderPdf: async
            Phil\PagedMediaBundle\Application\Message\RunPipeline: async
            Phil\PagedMediaBundle\Application\Message\ConvertDocuments: async
```

`sign` et `timestamp` terminent la liste par la signature et l'horodatage du document de la configuration, dans le
*worker* : aucune clé n'est donc écrite dans la tâche. Préférez-les à une étape `Sign` de votre cru, dont la tâche
contiendrait le certificat et le mot de passe.

## Le stockage des tâches

`JobStore` est le *claim check* : `keep()` garde une tâche sous un identifiant opaque, `find()` la redonne au *worker*
et `remove()` l'oublie une fois son fichier gardé. `DirectoryJobStore` écrit chaque tâche dans son propre fichier JSON
sous `jobs.directory`, nommé d'après 32 chiffres hexadécimaux aléatoires. Le répertoire et les fichiers ne sont
lisibles que par leur propriétaire, puisqu'une tâche contient les documents et les paramètres d'un fichier à venir.

`JobCodec` écrit une tâche en JSON et la relit. Rien n'est désérialisé, et un JSON qu'il n'a pas écrit est refusé.

| Partie d'une tâche | Gardée sous forme de |
|---|---|
| Un fichier lu à partir d'un chemin | Son nom et son chemin, que le *worker* doit pouvoir lire |
| Un fichier gardé en mémoire | Son nom et ses octets en base64 |
| Un téléchargement | Son URL et ses en-têtes |
| Une étape | Son opération, ses options telles que Gotenberg les nomme, et les fichiers qu'elles nomment ; relue sous forme de `StoredStage` |
| Les options de LibreOffice | Les options définies, une énumération par sa valeur |
| Les paramètres du gabarit | Tels quels : des scalaires et des tableaux |

Des *workers* sur plusieurs hôtes partagent un stockage : `jobs.cache_pool` nomme un pool de cache PSR-6 — Redis, une
base de données, un pool sur système de fichiers — et `CacheJobStore` y garde chaque tâche sous le même genre
d'identifiant, pendant `jobs.lifetime` secondes ou la durée de vie par défaut du pool. Donnez-lui un pool à lui, dont la
durée de vie dépasse le plus long temps qu'un message peut attendre et faire l'objet de nouvelles tentatives.

```yaml
framework:
    cache:
        pools:
            paged_media.jobs:
                adapter: cache.adapter.redis
                default_lifetime: 604800

phil_paged_media:
    jobs:
        cache_pool: paged_media.jobs
```

Faites du port l'alias d'un de vos services — une table de base de données, un stockage Flysystem — pour garder les
tâches ailleurs ; `JobCodec` lui donne le texte à garder.

## Le *claim check* de Messenger

Le stockage des tâches garde les messages courts par conception. Le *claim check* propre à Messenger 8.2, l'option
`claim_check` d'un transport, déplace tout message encodé plus grand que son `max_size` dans un pool de cache et envoie
une référence à la place ; réglez-la sur le transport des messages du bundle comme garde-fou — aucun ne devrait jamais
l'atteindre — et donnez-lui le même genre de pool dédié, gardé pendant toute la vie d'un message :

```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                claim_check:
                    cache_pool: messenger.claims
                    max_size: 4096
```

Avec un sérialiseur signataire sur le transport, Messenger signe l'enveloppe réclamée, et pas seulement sa référence.

Une tâche reste dans le stockage jusqu'à ce que son fichier soit gardé, ou jusqu'à ce que son message échoue
définitivement : un message qui fait l'objet d'une nouvelle tentative l'y retrouve. Un échec que Messenger ne retentera
pas — un échec irrécupérable, ou la dernière tentative épuisée — fait oublier la tâche par `FailedJobPurger`, dans un
*worker* (`WorkerMessageFailedEvent`) comme sur le transport `sync://` (`SyncMessageFailedEvent`). Les documents et les
paramètres qu'elle contient ne restent ainsi pas derrière. Il écoute après que Messenger a décidé de la nouvelle
tentative, et ignore tout autre message. Une tâche que le stockage ne peut pas retirer est laissée, et le *worker*
continue.

Un message envoyé à un transport d'échec et relancé de là avec `messenger:failed:retry` a besoin de sa tâche. Mettez
`jobs.remove_failed: false` pour la garder, puis retirez-la avec `JobStore::remove()` une fois le message en échec
réglé, ou supprimez les vieux fichiers du répertoire.

## Le stockage des fichiers et l'événement

Le fichier est gardé par `PdfStorage` sous le chemin de la tâche, relatif au stockage. `DirectoryStorage` l'écrit sous
`storage.directory` et refuse un chemin qui en sort. Faites du port l'alias d'un de vos services — un stockage
Flysystem, une médiathèque — pour le garder ailleurs.

Une fois le fichier gardé, un événement `PdfStored` donne l'identifiant de la tâche, le chemin, le nom du fichier, le
type de média et les comptes de la remédiation :

```php
#[AsEventListener]
public function __invoke(PdfStored $stored): void
{
    if (0 !== $stored->residual) { /* the PDF does not conform yet */ }
}
```

## Ce qui fait l'objet d'une nouvelle tentative

| Échec | Devient |
|---|---|
| Gotenberg hors d'atteinte, occupé (429) ou expiré (503) ; un coffre hors d'atteinte ; un stockage qui ne peut pas écrire ou une tâche qui ne peut pas être retirée | `RecoverableMessageHandlingException` : Messenger fait une nouvelle tentative, et la tâche attend |
| Une tâche que le stockage ne peut pas donner — aucune sous l'identifiant, une qu'il ne peut pas lire, ou une d'une autre sorte que le message | `UnrecoverableMessageHandlingException` : jamais retentée |
| Tout le reste de ce que lancent la bibliothèque ou le bundle : une requête que Gotenberg refuse, un gabarit inconnu, une étape mal construite, un fichier dont le chemin a disparu | `UnrecoverableMessageHandlingException` : jamais retentée |
