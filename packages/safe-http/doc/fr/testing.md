<!-- phil-dev source-sha256=928481c5b4b47708d3ec2f419ba6f986d852328da06927dac88df06329ced798 -->
# Tests

```sh
vendor/bin/phpunit packages/safe-http/tests
```

| Domaine | Ce qui est couvert |
|---|---|
| `Limits`, `ReadRule` | Les valeurs par défaut, chaque borne et la valeur au-delà, des en-têtes sur deux lignes ou avec un caractère de contrôle |
| `AddressGuard` | Adresses normalisées ; adresses IP sous toutes leurs écritures, identifiants, port 0, autres schémas, étiquettes seules et longueurs refusés ; redirections absolues, relatives, sans schéma et avec `..` ; redirections vers d'autres schémas refusées |
| `SafeHttpClient`, `SafeResponse` | Les limites dans chaque requête, le corps coupé à ses octets ou après sa marque — même coupée entre deux morceaux —, un morceau plus grand que les octets restants coupé avant de rejoindre le corps, le pic de mémoire proche des octets permis, les en-têtes bornés en octets et en nombre, comptés à mesure qu'ils arrivent et une fois tous arrivés, corps d'autres types non lus, statuts d'erreur comme réponses, `HEAD`, chaque sorte de redirection suivie et vérifiée, le compte des redirections, hôtes qui se résolvent vers la boucle locale, un réseau privé ou le point de terminaison de métadonnées refusés au départ et à une redirection, erreurs de transport, la durée totale à travers les redirections, `reset()`, le type de média et le jeu de caractères lus de la même façon sous une locale UTF-8 |
| `send()` | Méthodes en majuscules, `CONNECT`, `TRACE` et autres textes refusés avant toute requête ; noms d'en-têtes, noms faits de chiffres seulement, en-têtes réservés et valeurs refusés, les en-têtes bornés en octets et en nombre et le corps en octets, à la borne et au-delà ; adresses privées refusées ; chaque sorte de redirection refusée à une requête avec un corps ou d'une autre méthode ; redirections suivies par `GET`, `HEAD` et `OPTIONS` ; en-têtes conservés sur la même origine et retirés sur un autre hôte, schéma ou port |

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
