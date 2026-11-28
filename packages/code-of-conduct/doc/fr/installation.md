<!-- phil-dev source-sha256=c827dd2400976f1eaac5f72be7488f9ddf9c437d4ee764a5fcfe6961f8a58d2a -->
# Installation

```sh
composer require phil/code-of-conduct
```

Le paquet exige PHP 8.5 ou une version plus récente avec les extensions `mbstring` et `sodium`, `symfony/key-management`
8.2 pour le chiffrement des signalements, `symfony/uid` 8.2 pour leurs identifiants, `psr/clock` pour l'heure et
`psr/event-dispatcher` pour les événements.

Il n'enregistre rien : une application construit `CodeOfConduct` et `Reports` avec ses propres implémentations des
ports, par exemple comme services de son conteneur. Voir [Points d'extension](extension-points.md).

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

Le KMS est n'importe quel client du composant KeyManagement de Symfony qui génère des clés de données : une clé locale
Sodium ou OpenSSL pour un seul serveur, un KMS infonuagique autrement. La clé maîtresse n'atteint jamais le paquet.
