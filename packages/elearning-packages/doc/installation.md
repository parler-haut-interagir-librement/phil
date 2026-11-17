# Installation

## Requirements

- PHP 8.5 or later.
- The `dom` and `libxml` extensions, which parse the manifests.
- The `mbstring` extension, which bounds texts and checks that paths are UTF-8.
- The `zip` extension, which reads the archives.

The library depends on no other package. It never writes to the disk: a package is read where it lies, and its files
are handed to the caller as strings.

## Installing

```sh
composer require phil/elearning-packages
```

## A first reading

```php
use Phil\ElearningPackages\PackageReader;

$package = new PackageReader()->readFile('/path/to/course.zip');
```

`readFile()` opens the archive, reads the manifest at its root, checks the files it names, and closes the archive. It
throws `PackageRefused` when the file is not a package it can read safely; see [Exceptions](exceptions.md).

The default limits suit a course package of up to 512 MiB; [Limits and security](limits-and-security.md) explains each
and how to narrow them, for an upload form for instance:

```php
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;

$reader = new PackageReader(new PackageLimits(archiveBytes: 50 * 1024 * 1024, entries: 2_000));
```

## In a Symfony application

The library needs no bundle. Register `PackageReader` as a service with the limits of the application:

```yaml
# config/services.yaml
services:
    Phil\ElearningPackages\PackageLimits:
        arguments:
            $archiveBytes: 104857600
    Phil\ElearningPackages\PackageReader:
        arguments:
            $limits: '@Phil\ElearningPackages\PackageLimits'
            $language: 'fr'
```

Both are immutable and keep no state between two readings: they are safe in a long-running worker.
