<!-- phil-dev source-sha256=15fc6c1bdaf7e868c300ffdcf9f4a02a87b4d132c2b6f5ac83b4b1c881038256 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\Checkin\Exception\CheckinException`. Aucun de leurs messages ne
nomme un code.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidCheckin` | `InvalidArgumentException` | Un identifiant, un code, un titre d'accès, une fréquentation, un enregistrement, une politique ou une borne reçoit une valeur dont il ne peut rien faire. |
| `InvalidOfflineBatch` | `InvalidArgumentException` | Un journal envoyé par un appareil n'est pas une liste, contient trop de lectures, ou l'une d'elles est mal formée ; le message donne sa position. |
| `LedgerContention` | `RuntimeException` | Chaque tentative d'inscrire une lecture a rencontré une autre lecture du titre inscrite d'abord ; la lecture peut être renvoyée. |
| `ManifestTooLarge` | `RuntimeException` | Le catalogue contient plus de titres d'accès pour un point de contrôle qu'un manifeste ne peut en porter. |
| `WeakManifestCodes` | `RuntimeException` | Le catalogue contient un code pour un point de contrôle sous la force qu'exige un manifeste, 64 bits par défaut. |

Une application répond à `InvalidCheckin` et `InvalidOfflineBatch` venus d'un appareil par une erreur client, et à
`LedgerContention` par une erreur serveur que l'appareil retente.
