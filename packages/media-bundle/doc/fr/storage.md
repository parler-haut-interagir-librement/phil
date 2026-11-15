<!-- phil-dev source-sha256=c6c6bec3d396aafafb53518ea236a9aef9d89fcb8565b70fc4d2222a12129515 -->
# Stockage

Les fichiers sont gardés par clé : un original sous `originals/{id}/{version}/{safe name}`, un rendu sous
`formats/{id}/{version}/…`. Le port est `MediaStorage` (`write()`, `read()`, `deletePrefix()`), avec deux adaptateurs.

## Un répertoire

Par défaut, `FilesystemStorage` garde les fichiers dans `storage_dir`, `%kernel.project_dir%/var/media`. Une clé qui
contient `..`, ou qui commence par une barre oblique, est refusée avant d'atteindre le disque.

## Flysystem

Avec `filesystem`, les fichiers sont plutôt gardés sur un système de fichiers Flysystem, par `FlysystemStorage` : le
service nommé doit être un `League\Flysystem\FilesystemOperator`. Les mêmes clés sont refusées, et un échec du système
de fichiers est une `InvalidMedia` qui porte l'exception de Flysystem comme exception précédente.

```yaml
# config/packages/phil_media.yaml
phil_media:
    filesystem: media.storage
```

Un répertoire local, avec `league/flysystem-local` :

```yaml
# config/services.yaml
services:
    media.storage.adapter:
        class: League\Flysystem\Local\LocalFilesystemAdapter
        arguments: ['%kernel.project_dir%/var/media']
    media.storage:
        class: League\Flysystem\Filesystem
        arguments: ['@media.storage.adapter']
```

Un compartiment S3, avec `league/flysystem-aws-s3-v3` :

```yaml
services:
    media.s3:
        class: Aws\S3\S3Client
        arguments:
            - version: latest
              region: '%env(AWS_REGION)%'
    media.storage.adapter:
        class: League\Flysystem\AwsS3V3\AwsS3V3Adapter
        arguments: ['@media.s3', '%env(MEDIA_BUCKET)%', 'media']
    media.storage:
        class: League\Flysystem\Filesystem
        arguments: ['@media.storage.adapter']
```

Les fichiers sont toujours servis par `MediaController`, à des URL signées : le compartiment reste privé, et un CDN
devant l'application garde les réponses un an, puisque leurs URL portent la version. Un système de fichiers défini par
`league/flysystem-bundle` fonctionne de la même façon : nommez son service.

## Lecture en flux

Les deux adaptateurs implémentent aussi `StreamingMediaStorage`, qui ajoute `readStream()` : le fichier comme un flux
lisible, ou `null` s'il n'y en a pas. `FilesystemStorage` ouvre le fichier sur le disque, `FlysystemStorage` demande son
`readStream()` au système de fichiers : un fichier dans un seau S3 est donc lu à mesure qu'il arrive.
`MediaLibrary::readOriginal()` et `MediaLibrary::copyOriginalTo()` lisent par ce moyen, et la mémoire qu'utilise un
*worker* reste constante, quelle que soit la taille du fichier : voir [La médiathèque](library.md#lire-loriginal).

## Un stockage propre à l'application

Un service `MediaStorage` propre à l'application, aliasé à l'interface, remplace les deux adaptateurs. Implémenter aussi
`StreamingMediaStorage` est facultatif : sans cela, la médiathèque lit un fichier en entier, puis le remet comme un
flux.
