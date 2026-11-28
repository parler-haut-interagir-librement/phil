# Installation

```sh
composer require phil/participant-safety-info
```

The package needs PHP 8.5 or later with the `mbstring` and `sodium` extensions, `symfony/key-management`
8.2 for the encryption, `psr/clock` for the time and `psr/event-dispatcher` for the events.

It registers nothing: an application builds `SafetyInfoVault` with its own implementations of the ports, as a service
of its container for instance. See [Extension points](extension-points.md).

```php
use Phil\ParticipantSafetyInfo\KeyManagement\KeyManagementSealer;
use Phil\ParticipantSafetyInfo\SafetyInfoVault;

$vault = new SafetyInfoVault(
    $store,                                      // Contract\SafetyInfoStore
    new KeyManagementSealer($kms, 'safety-info'), // a DataKeyGeneratorInterface and the name of its master key
    $roles,                                      // Contract\EventRoles
    $windows,                                    // Contract\AccessWindows
    $journal,                                    // Contract\AccessJournal
    $clock,                                      // Psr\Clock\ClockInterface
    $dispatcher,                                 // Psr\EventDispatcher\EventDispatcherInterface, optional
);
```

The KMS is any client of Symfony's KeyManagement component that generates data keys: a local Sodium or OpenSSL key for
a single server, a cloud KMS otherwise. The master key never reaches the package.

Schedule `$vault->purge()` — every hour, for instance — so that the information of an event is erased once its window
has closed.
