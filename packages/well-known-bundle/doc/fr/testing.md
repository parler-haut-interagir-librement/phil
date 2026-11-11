<!-- phil-dev source-sha256=76a59b7504b6cdb2239f726d5b946968ad337a272c68d77df93dded34a9c76de -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `WellKnownPath` | Les noms, les chemins absolus, les noms de route, les bornes, chaque chemin refusé |
| `SecurityTxt` | Chaque champ et son ordre, les champs obligatoires, chaque valeur refusée, les dates `Expires` et les jours qui manquent au calendrier |
| `WebFingerQuery`, `WebFingerResource` | `acct:`, `https:` et les autres schémas, la normalisation, les `rel` répétés, les bornes, chaque requête refusée |
| `WebFingerDescriptor`, `WebFingerLink`, `WebFinger`, `StaticWebFingerResolver` | Le JRD écrit, la fusion, le filtrage par relation, les résolveurs dans l'ordre |
| `NodeInfo`, `NodeInfoSoftware`, `NodeInfoUsage`, `ConfiguredNodeInfoProvider` | Le document 2.1, les objets vides, chaque valeur refusée |
| `Did` | Les DID, les méthodes, les bornes |
| Les documents | Les corps, les types de média, CORS, les redirections, la signature, les hôtes, `400` et `404` |
| `WellKnownController` | Les en-têtes de cache, `ETag` et `304`, le cache propre à un document, les erreurs et les redirections, un document inconnu |
| `WellKnownRouteLoader` | Les routes, les méthodes, les valeurs par défaut, la ressource de cache, le type |
| `PhilWellKnownBundle`, `WellKnownServices`, `WellKnownPass` | Les valeurs par défaut, chaque document, les fichiers, chaque configuration, fichier et étiquette refusés |
| `SecurityTxtExpiryCheck` | En service, dégradée dès l'avertissement, hors service dès `Expires`, les jours arrondis vers le haut, la date en UTC, l'avertissement refusé, son enregistrement |
| Une application entière | Chaque document servi avec ses en-têtes, `304` et `HEAD`, les corps, le signataire, WebFinger avec un résolveur, `change-password`, `405`, chaque adresse en `404` quand son document est désactivé, et l'expiration parmi les vérifications de phil/monitoring-bundle |

```sh
vendor/bin/phpunit packages/well-known-bundle/tests
```

## Fuzzing

La cible `webfinger-query` lit comme une requête WebFinger du texte que personne n'a écrit : elle retourne une requête
dont la ressource se relit en la même URI, ou lance `InvalidValue`, et rien d'autre. Depuis la racine du monodépôt :

```sh
PHIL_DEV_PACKAGES=well-known-bundle composer fuzz
```

## Bancs d'essai

`WebFingerBench` sert, par le contrôleur, une requête WebFinger parmi 1 000 comptes, un compte inconnu et un document
fixe :

```sh
composer bench -- packages/well-known-bundle/tests/Benchmark
```
