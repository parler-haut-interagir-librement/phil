<!-- phil-dev source-sha256=aba1928f25c945bd775ea5e881d9bf3945c9e7e6ae198a50cdf6004b84a9d214 -->
# Exceptions

Chaque exception du bundle implémente `Phil\EditLockBundle\Domain\Exception\EditLockException`.

| Exception | Levée par | Quand |
|---|---|---|
| `Domain\Exception\InvalidLease` | `LeaseKey::of()`, `Holder::of()`, `Lease`, `EditLocks`, `DoctrineLeaseRepository` | Une ressource, une partie, un détenteur ou une durée dont on ne peut pas faire un bail ; une ligne stockée qui n'est pas un bail |
| `Application\Exception\LeaseHeld` | `acquire()`, `takeOver()` | Un autre détenteur détient la clé ou ce qui la chevauche ; `$lease` est son bail, `null` lorsque les requêtes ont continué de se faire concurrence |
| `Application\Exception\LeaseLost` | `heartbeat()`, `BlockSaves` | Le détenteur ne détient plus la clé, ou ne détient pas le bloc enregistré |
| `Application\Exception\EditRefused` | `acquire()`, `takeOver()`, `heartbeat()`, `BlockSaves`, le point de terminaison de présence | La politique refuse de laisser le détenteur modifier la ressource, ou reprendre un bail |
| `Application\Exception\InvalidBlock` | `BlockSaves::save()` | `phil/ujo` refuse le bloc, ou la liste ou le bloc précédent n'en est pas un ; `getPrevious()` contient l'exception de `phil/ujo` |

Les points de terminaison y répondent par un statut : [points de terminaison](endpoints.md#erreurs). Un hub injoignable
ne lève rien : [Mercure](mercure.md#un-hub-injoignable).
