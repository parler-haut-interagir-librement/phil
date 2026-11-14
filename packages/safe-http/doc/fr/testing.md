<!-- phil-dev source-sha256=c643aa134f5988eb3e8e3b87953be069e557f5614340cb2219f4f7ae8a33d8de -->
# Tests

```sh
vendor/bin/phpunit packages/safe-http/tests
```

| Domaine | Ce qui est couvert |
|---|---|
| `Limits`, `ReadRule` | Les valeurs par défaut, chaque borne et la valeur au-delà, des en-têtes sur deux lignes ou avec un caractère de contrôle |
| `AddressGuard` | Adresses normalisées ; adresses IP sous toutes leurs écritures, identifiants, port 0, autres schémas, étiquettes seules et longueurs refusés ; redirections absolues, relatives, sans schéma et avec `..` ; redirections vers d'autres schémas refusées |
| `SafeHttpClient`, `SafeResponse` | Les limites dans chaque requête, le corps coupé à ses octets ou après sa marque — même coupée entre deux morceaux —, un morceau plus grand que les octets restants coupé avant de rejoindre le corps, le pic de mémoire proche des octets permis, les en-têtes bornés en octets et en nombre, comptés à mesure qu'ils arrivent et une fois tous arrivés, corps d'autres types non lus, statuts d'erreur comme réponses, `HEAD`, chaque sorte de redirection suivie et vérifiée, le compte des redirections, hôtes qui se résolvent vers la boucle locale, un réseau privé ou le point de terminaison de métadonnées refusés au départ et à une redirection, erreurs de transport, la durée totale à travers les redirections, `reset()` |

Les tests utilisent le `MockHttpClient` de Symfony et des adresses fixes : aucun test ne résout de nom ni n'ouvre de
connexion.

## Fuzzing

La cible `safe-http-addresses` donne à la garde des adresses et des redirections que personne n'a écrites, à partir des
germes de `tests/Fuzz/corpus/safe-http-addresses` : la première ligne comme adresse, la deuxième comme redirection à
partir d'une adresse connue. Chacune donne une adresse qui se relit comme elle-même, ou `RefusedAddress`, jamais une
autre exception.

```sh
composer fuzz -- safe-http-addresses
```
