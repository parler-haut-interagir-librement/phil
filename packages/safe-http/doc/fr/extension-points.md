<!-- phil-dev source-sha256=cf8335ce438451731de9694fa225167b85c9a2c7f72e75021b0c2b82669cb5a9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Lire par un autre transport — un mandataire, un client à portée limitée, un enregistrement des tests | Passez-le comme `client` : la garde l'enveloppe |
| Associer un hôte à une adresse, ou faire que les tests ne résolvent aucun nom | Passez `resolve` : l'adresse est quand même vérifiée contre les réseaux privés |
| Changer ce qu'une lecture peut coûter | Passez d'autres `Limits` |
| Lire une autre partie d'un corps | Passez une autre `ReadRule` |

Ce qui reste fermé : la garde elle-même. `SafeHttpClient`, `AddressGuard` et les objets-valeurs sont `final` ; un site
qui a besoin d'une adresse que la garde refuse — un service de son propre réseau — la lit avec son propre client, non
avec celui-ci.
