<!-- phil-dev source-sha256=ad53a6c8db992903c35c9a333088f5528c8d2078c762550839bd0d9a53438b1a -->
# Installation

```sh
composer require phil/participant-safety-info
```

Le paquet exige PHP 8.5 ou une version plus récente avec les extensions `mbstring` et `sodium`, `symfony/key-management`
8.2 pour le chiffrement, `psr/clock` pour l'heure et `psr/event-dispatcher` pour les événements.

Il n'enregistre rien : une application construit `SafetyInfoVault` avec ses propres implémentations des ports, par
exemple comme service de son conteneur. Voir [Points d'extension](extension-points.md).

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

Le KMS est n'importe quel client du composant KeyManagement de Symfony qui génère des clés de données : une clé locale
Sodium ou OpenSSL pour un seul serveur, un KMS infonuagique autrement. La clé maîtresse n'atteint jamais le paquet.

Planifiez `$vault->purge()` — toutes les heures, par exemple — afin que l'information d'un événement soit effacée une
fois sa fenêtre fermée.
