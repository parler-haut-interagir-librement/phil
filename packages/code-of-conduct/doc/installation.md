# Installation

```sh
composer require phil/code-of-conduct
```

The package needs PHP 8.5 or later with the `mbstring` and `sodium` extensions, `symfony/key-management`
8.2 for the encryption of the reports, `symfony/uid` 8.2 for their identifiers, `psr/clock` for the time and
`psr/event-dispatcher` for the events.

It registers nothing: an application builds `CodeOfConduct` and `Reports` with its own implementations of the ports,
as services of its container for instance. See [Extension points](extension-points.md).

```php
use Phil\CodeOfConduct\CodeOfConduct;
use Phil\CodeOfConduct\KeyManagement\KeyManagementTextSealer;
use Phil\CodeOfConduct\Reports;

$code = new CodeOfConduct($versions, $acceptances, $clock, $dispatcher);
$reports = new Reports(
    $reportStore,                                  // Contract\ReportStore
    $versions,                                     // Contract\CodeVersionStore
    $responsiblePeople,                            // Contract\ResponsiblePeople
    new KeyManagementTextSealer($kms, 'reports'),  // a DataKeyGeneratorInterface and the name of its master key
    $clock,                                        // Psr\Clock\ClockInterface
    $dispatcher,                                   // Psr\EventDispatcher\EventDispatcherInterface, optional
);
```

The KMS is any client of Symfony's KeyManagement component that generates data keys: a local Sodium or OpenSSL key for
a single server, a cloud KMS otherwise. The master key never reaches the package.
