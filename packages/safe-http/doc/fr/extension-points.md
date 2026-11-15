<!-- phil-dev source-sha256=c83d08f3824047595565003e94682fe1d7553add77a3b2137beeb4199fd61b50 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Lire par un autre transport — un mandataire, un client à portée limitée, un enregistrement des tests | Passez-le comme `client` : la garde l'enveloppe |
| Associer un hôte à une adresse, ou faire que les tests ne résolvent aucun nom | Passez `resolve` : l'adresse est quand même vérifiée contre les réseaux privés |
| Changer ce qu'une lecture peut coûter | Passez d'autres `Limits` |
| Lire une autre partie d'un corps | Passez une autre `ReadRule` |
| Appeler le service Web d'une autre plateforme — un point de terminaison de jeton, un webhook — avec des identifiants de connexion | `send()` avec la méthode, les en-têtes et le corps : les en-têtes restent sur l'origine de la première adresse |

Ce qui reste fermé : la garde elle-même. `SafeHttpClient`, `AddressGuard` et les objets-valeurs sont `final` ; un site
qui a besoin d'une adresse que la garde refuse — un service de son propre réseau — la lit avec son propre client, non
avec celui-ci, son adresse lue par le `ServiceUrl` de `phil/url` pour les hôtes qu'il déclare.
