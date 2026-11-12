# Storage

Files are kept by key: an original under `originals/{id}/{version}/{safe name}`, a rendering under
`formats/{id}/{version}/…`. The port is `MediaStorage` — `write()`, `read()`, `deletePrefix()` —, with two adapters.

## A directory

By default, `FilesystemStorage` keeps files in `storage_dir`, `%kernel.project_dir%/var/media`. A key with `..`, or
one that starts with a slash, is refused before it reaches the disk.

## Flysystem

With `filesystem`, files are kept on a Flysystem filesystem instead, through `FlysystemStorage`: the service named
must be a `League\Flysystem\FilesystemOperator`. The same keys are refused, and a failure of the filesystem is an
`InvalidMedia` that carries Flysystem's exception as its previous one.

```yaml
# config/packages/phil_media.yaml
phil_media:
    filesystem: media.storage
```

A local directory, with `league/flysystem-local`:

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

An S3 bucket, with `league/flysystem-aws-s3-v3`:

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

The files are still served by `MediaController`, at signed URLs: the bucket stays private, and a CDN in front of the
application keeps the responses a year, since their URLs carry the version. A filesystem defined by
`league/flysystem-bundle` works the same way: name its service.

## A storage of the application's own

A `MediaStorage` service of the application's own, aliased to the interface, replaces both adapters.
