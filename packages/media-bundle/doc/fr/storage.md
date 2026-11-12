<!-- phil-dev source-sha256=b31ee981ae451d5d24ef70aac244651de31452753cfd36696506546985ecd708 -->
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

## Un stockage propre à l'application

Un service `MediaStorage` propre à l'application, aliasé à l'interface, remplace les deux adaptateurs.
